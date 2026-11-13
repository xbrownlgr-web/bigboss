<?php

namespace App\Console\Commands;

use App\Support\AiProvider;
use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\PronosticRun;
use App\Services\ExactScoreGenerator;
use App\Services\FixtureService;
use App\Services\MatchDataService;
use App\Services\OddsService;
use App\Support\PronosticRules;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Offre Score exact : à chaque créneau (« ai_schedule_score », Admin > Paramètres), cherche les
 * matchs des 12 prochaines heures les plus prévisibles et publie leur score exact, chacun en
 * pronostic séparé (4 scores indépendants, jamais un combiné), notifié dès sa publication.
 * Au moins DAILY_MIN par jour si les données le permettent, au plus DAILY_MAX. Les créneaux du
 * jour ne publient qu'en confiance MOYENNE ; le dernier complète au besoin avec du FAIBLE (liste de
 * scores élargie) pour atteindre le minimum.
 */
class GenerateExactScores extends Command
{
    protected $signature = 'binho:generate-exact-scores
        {--slot= : Heure du créneau (HH:MM), par défaut l’heure actuelle}
        {--dry-run : Affiche les scores sans rien publier ni notifier}';

    protected $description = 'Cherche et publie les scores exacts (offre VIP Score exact).';

    public const DAILY_MIN = 4;

    public const DAILY_MAX = 6;

    public const PER_RUN = 2;

    public const DEFAULT_SCHEDULE = '08:00, 12:00, 16:00, 20:00';

    private const WINDOW_START_MINUTES = 45;

    private const WINDOW_HOURS = 12;

    private const COLOR = '#BE123C';

    public function __construct(
        private readonly FixtureService $fixtures,
        private readonly OddsService $odds,
        private readonly MatchDataService $matchData,
        private readonly ExactScoreGenerator $generator,
    ) {
        parent::__construct();
    }

    /**
     * @return array<int, string>
     */
    public static function schedule(): array
    {
        return PronosticRules::times(AppSetting::value('ai_schedule_score') ?? self::DEFAULT_SCHEDULE);
    }

    public function handle(): int
    {
        $timezone = PronosticRules::TIMEZONE;
        $now = now()->setTimezone($timezone);
        $slot = $this->option('slot') ?: $now->format('H:i');
        $published = Pronostic::query()
            ->where('niveau', ExactScoreGenerator::LEVEL)
            ->where('published_at', '>=', $now->copy()->startOfDay())
            ->count();

        if ($published >= self::DAILY_MAX) {
            return $this->finish($slot, PronosticRun::STATUS_EMPTY, 'Plafond du jour atteint (' . self::DAILY_MAX . ' scores exacts).');
        }

        $schedule = self::schedule();
        $lastSlot = $schedule === [] || $slot >= end($schedule);
        $quota = min(self::DAILY_MAX - $published, $lastSlot ? max(self::PER_RUN, self::DAILY_MIN - $published) : self::PER_RUN);

        // Un match n'est donné qu'une fois en score exact.
        $alreadyGiven = Pronostic::query()
            ->where('niveau', ExactScoreGenerator::LEVEL)
            ->where('published_at', '>=', now()->subDay())
            ->get(['evenements'])
            ->flatMap(fn (Pronostic $pronostic) => array_column($pronostic->evenements ?? [], 'match'))
            ->all();

        try {
            $fixtures = $this->fixtures->upcoming(now()->addMinutes(self::WINDOW_START_MINUTES), now()->addHours(self::WINDOW_HOURS));
        } catch (Throwable $exception) {
            return $this->finish($slot, PronosticRun::STATUS_ERROR, 'Matchs de football indisponibles : ' . $exception->getMessage());
        }

        $fixtures = array_values(array_filter($fixtures, fn ($fixture) => ! in_array($fixture['match'], $alreadyGiven, true)));

        try {
            $fixtures = $this->odds->attach($fixtures);
        } catch (Throwable $exception) {
            Log::warning('Score exact : cotes indisponibles.', ['error' => $exception->getMessage()]);
        }

        $analyses = collect($this->matchData->enrich($fixtures))
            ->map(fn ($fixture) => $this->generator->analyse($fixture))
            ->filter()
            ->sortByDesc(fn ($analysis) => ($analysis['confidence'] === ExactScoreGenerator::CONFIDENCE_MEDIUM ? 1 : 0) + $analysis['prediction']['official']['p'])
            ->values();

        $medium = $analyses->where('confidence', ExactScoreGenerator::CONFIDENCE_MEDIUM)->values();
        $picked = $medium->take($quota);

        if ($lastSlot && $published + $picked->count() < self::DAILY_MIN) {
            $missing = min($quota - $picked->count(), self::DAILY_MIN - $published - $picked->count());
            $picked = $picked->concat($analyses->where('confidence', ExactScoreGenerator::CONFIDENCE_LOW)->take(max(0, $missing)));
        }

        if ($picked->isEmpty()) {
            return $this->finish($slot, PronosticRun::STATUS_EMPTY, 'Aucun match assez documenté pour un score exact (' . count($fixtures) . ' match(s) examiné(s), ' . $analyses->count() . ' analysable(s)).');
        }

        $key = AiProvider::key();
        $created = [];

        foreach ($picked as $analysis) {
            $ai = $key ? $this->generator->askAi($key, $analysis) : null;
            $final = $this->generator->finalize($analysis, $ai['adjustments'] ?? []);
            $text = $this->generator->render($final, $ai);

            if ($this->option('dry-run')) {
                $this->line($text);
                $this->newLine();

                continue;
            }

            $created[] = $this->publish($this->generator->toEvent($final), $text);
        }

        if ($this->option('dry-run')) {
            $this->info('Test à blanc : rien n’a été publié ni notifié.');

            return self::SUCCESS;
        }

        $this->notify($created);
        $scores = collect($created)->map(fn (Pronostic $pronostic) => $pronostic->evenements[0]['match'] . ' ' . $pronostic->evenements[0]['score'])->implode(' ; ');

        return $this->finish($slot, PronosticRun::STATUS_PUBLISHED, count($created) . " score(s) exact(s) publié(s) : {$scores}.", $created[0]->id ?? null);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function publish(array $event, string $analysis): Pronostic
    {
        $kickoff = ! empty($event['kickoff_at'])
            ? Carbon::parse($event['kickoff_at'])->setTimezone(PronosticRules::TIMEZONE)
            : now()->setTimezone(PronosticRules::TIMEZONE);

        return Pronostic::create([
            'niveau' => ExactScoreGenerator::LEVEL,
            'couleur' => self::COLOR,
            // Pas de cote de score exact fournie par l'API : rien d'inventé (l'app affiche la probabilité).
            'cote_totale' => null,
            'probabilite' => $event['probabilite'],
            'evenements' => [$event],
            'code_1xbet' => null,
            'code_melbet' => null,
            'match_date' => $kickoff->toDateString(),
            'match_time' => $kickoff->format('H:i'),
            'team_home' => $event['home'],
            'team_away' => $event['away'],
            'competition' => 'Score exact',
            'prediction' => 'Score exact : ' . $event['score'],
            'odds' => 0,
            'confidence' => $event['probabilite'],
            'analysis' => $analysis,
            'is_vip' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Une notification par score publié, deux minutes d'écart (même file que les coupons).
     *
     * @param array<int, Pronostic> $created
     */
    private function notify(array $created): void
    {
        if ($created === []) {
            return;
        }

        $batchId = (string) Str::uuid();
        $base = now();

        foreach ($created as $sequence => $pronostic) {
            DB::table('scheduled_pronostic_notifications')->insert([
                'batch_id' => $batchId,
                'sequence' => $sequence,
                'pronostic_id' => $pronostic->id,
                'available_at' => $base->copy()->startOfMinute()->addMinutes($sequence * 2),
                'created_at' => $base,
                'updated_at' => $base,
            ]);
        }

        $this->call('binho:send-pronostic-notification', ['--batch' => $batchId]);
    }

    private function finish(string $slot, string $status, string $message, ?int $pronosticId = null): int
    {
        $this->info($message);

        if (! $this->option('dry-run')) {
            PronosticRun::query()->updateOrCreate(
                ['run_date' => now()->setTimezone(PronosticRules::TIMEZONE)->toDateString(), 'level' => ExactScoreGenerator::LEVEL, 'slot' => $slot],
                ['status' => $status, 'message' => mb_substr($message, 0, 1000), 'pronostic_id' => $pronosticId],
            );
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Exceptions\NoValueBetException;
use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\PronosticRun;
use App\Services\AiPronosticGenerator;
use App\Services\FixtureService;
use App\Services\MatchDataService;
use App\Services\OddsService;
use App\Services\WhatsAppNotifier;
use App\Support\LeagueCatalog;
use App\Support\PronosticRules;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Génère les coupons d'un ou plusieurs groupes : matchs des prochaines heures dans tous les
 * championnats (Highlightly), cotes réelles (The Odds API), collecte des données (classement,
 * forme, H2H), analyse, IA (OpenRouter), publication et notification.
 * Lancé par binho:run-pronostic-slots aux heures de chaque groupe (Admin > Paramètres).
 * Rien de fiable : rien n'est publié. Le football passe d'abord ; le basket et le handball
 * prennent le relais quand il ne donne rien.
 */
class GenerateDailyPronostics extends Command
{
    protected $signature = 'binho:generate-pronostics
        {--level=* : Groupe(s) à générer (Starter, Bronze, Silver, Gold, Platinum). Par défaut : tous.}
        {--slot= : Créneau « HH:MM » auquel la génération est rattachée (trace dans l’admin).}
        {--date= : Journée à analyser (YYYY-MM-DD) au lieu des prochaines heures.}
        {--dry-run : Affiche ce qui serait publié, sans rien publier ni notifier.}';

    protected $description = 'Génère, publie et notifie les coupons des groupes demandés (créneaux de Admin > Paramètres).';

    public const LAST_RUN_CACHE_KEY = 'pronostics:last_run';

    // Coups d'envoi analysés : à partir de 30 min (jamais un match commencé), jusqu'à 18 h plus tard.
    private const WINDOW_START_MINUTES = 30;

    private const WINDOW_HOURS = 18;

    private const LEVEL_COLORS = [
        'Starter' => '#6B7280',
        'Bronze' => '#92400E',
        'Silver' => '#9CA3AF',
        'Gold' => '#FFD700',
        'Platinum' => '#7C3AED',
    ];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $otherSports = null;

    public function __construct(
        private readonly FixtureService $fixtures,
        private readonly AiPronosticGenerator $generator,
        private readonly OddsService $odds,
        private readonly MatchDataService $matchData,
        private readonly WhatsAppNotifier $whatsapp,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $levels = array_values(array_intersect(PronosticRules::LEVELS, $this->option('level') ?: PronosticRules::LEVELS));
        $slot = $this->option('slot') ?: now()->setTimezone($this->timezone())->format('H:i');
        [$from, $to] = $this->window();

        $footballError = null;

        try {
            $matches = $this->fixtures->upcoming($from, $to);
        } catch (Throwable $exception) {
            // Le basket et le handball (The Odds API) restent possibles sans Highlightly.
            $footballError = 'Matchs de football indisponibles : ' . $exception->getMessage();
            $this->warn($footballError);
            $matches = [];
        }

        try {
            $matches = $this->odds->attach($matches);
        } catch (Throwable $exception) {
            Log::warning('Cotes réelles indisponibles.', ['error' => $exception->getMessage()]);
        }

        if ($oddsError = OddsService::usage()['error'] ?? null) {
            $this->warn($oddsError);
        }

        // Collecte des données (classement, forme, H2H, détails) AVANT toute analyse.
        $matches = $this->matchData->enrich($matches);
        $published = [];

        foreach ($levels as $level) {
            $result = $this->generateLevel($level, $slot, $matches, $from, $to, $footballError);

            if ($result instanceof Pronostic) {
                $published[] = $result;
            }
        }

        if ($published !== [] && ! $this->option('dry-run')) {
            $this->scheduleNotifications($published);
        }

        $this->info(count($published) . ' coupon(s) publié(s) sur ' . count($levels) . ' groupe(s).');

        return self::SUCCESS;
    }

    /**
     * Un groupe : football d'abord, puis basket / handball si rien n'est fiable.
     *
     * @param array<int, array<string, mixed>> $matches
     */
    private function generateLevel(string $level, string $slot, array $matches, Carbon $from, Carbon $to, ?string $footballError = null): ?Pronostic
    {
        $options = [...$this->exclusions($level), 'slot' => $slot];
        $attempts = [$matches];
        $message = null;

        foreach ([0, 1] as $attempt) {
            if ($attempt === 1) {
                $others = $this->otherSports($from, $to);

                if ($others === []) {
                    break;
                }

                $attempts[] = [...$matches, ...$others];
            }

            try {
                $drafts = $this->generator->generate($attempts[$attempt], [$level], $options);
                $draft = $drafts[0];

                if ($this->option('dry-run')) {
                    $this->line("[{$level}] cote {$draft['cote_totale']} · probabilité {$draft['probabilite']} %");
                    $this->line($draft['analysis']);
                    $this->newLine();
                    $this->info('Test à blanc : rien n’a été publié ni notifié.');

                    return null;
                }

                $pronostic = $this->publish($draft);
                $tier = (int) ($draft['palier'] ?? 0);
                $how = match (true) {
                    $tier === 0 => '',
                    $tier < 3 => ', seuil de fiabilité abaissé',
                    default => ', coupon de secours',
                };
                $this->record($level, $slot, PronosticRun::STATUS_PUBLISHED, "Coupon cote {$draft['cote_totale']} publié (" . count($draft['evenements']) . " sélection(s){$how}).", $pronostic->id);
                $this->remember('success', "Coupon {$level} publié (cote {$draft['cote_totale']}).");

                return $pronostic;
            } catch (NoValueBetException $exception) {
                $message = $exception->getMessage();
            } catch (Throwable $exception) {
                $this->failLevel($level, $slot, 'Génération IA impossible : ' . $exception->getMessage());

                return null;
            }
        }

        $searched = count($matches) . ' match(s) de football avec cotes dans ' . collect($matches)->pluck('competition')->unique()->count() . ' championnat(s)'
            . ($this->otherSports !== null ? ' + ' . count($this->otherSports) . ' rencontre(s) de basket / handball' : '');
        $message = "Rien de fiable pour {$level} ({$searched}). {$message}";

        if ($footballError !== null) {
            $this->failLevel($level, $slot, "{$footballError} {$message}");

            return null;
        }

        $this->info($message);

        if (! $this->option('dry-run')) {
            Log::info('Génération : aucun coupon fiable.', ['level' => $level, 'message' => $message]);
            $this->record($level, $slot, PronosticRun::STATUS_EMPTY, $message);
            $this->remember('empty', $message);
        }

        return null;
    }

    /**
     * Matchs déjà proposés aujourd'hui : un coupon gratuit ne reprend jamais un match d'un coupon
     * VIP (et inversement), ni un match déjà donné dans le même groupe ; entre deux groupes VIP,
     * on évite si possible.
     *
     * @return array{exclude: array<int, string>, avoid: array<int, string>}
     */
    private function exclusions(string $level): array
    {
        $free = PronosticRules::isFree($level);
        $exclude = [];
        $avoid = [];

        $today = Pronostic::query()
            ->where('published_at', '>=', now()->setTimezone($this->timezone())->startOfDay())
            ->get(['niveau', 'evenements']);

        foreach ($today as $pronostic) {
            $matches = array_column($pronostic->evenements ?? [], 'match');
            $sameKind = PronosticRules::isFree((string) $pronostic->niveau) === $free;

            if (! $sameKind || $pronostic->niveau === $level) {
                array_push($exclude, ...$matches);
            } else {
                array_push($avoid, ...$matches);
            }
        }

        return ['exclude' => array_values(array_unique($exclude)), 'avoid' => array_values(array_unique($avoid))];
    }

    /**
     * Rencontres de basket / handball des prochaines heures, relevées une fois par génération.
     *
     * @return array<int, array<string, mixed>>
     */
    private function otherSports(Carbon $from, Carbon $to): array
    {
        if ($this->otherSports === null) {
            $this->otherSports = [];

            foreach (array_keys(LeagueCatalog::OTHER_SPORTS) as $sport) {
                try {
                    $this->otherSports = [...$this->otherSports, ...$this->odds->otherSport($sport, $from, $to)];
                } catch (Throwable $exception) {
                    Log::warning('Cotes indisponibles.', ['sport' => $sport, 'error' => $exception->getMessage()]);
                }
            }
        }

        return $this->otherSports;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(): array
    {
        if ($this->option('date')) {
            $day = Carbon::parse($this->option('date'), $this->timezone());
            $from = $day->copy()->startOfDay()->max(now()->addMinutes(self::WINDOW_START_MINUTES));

            return [$from, $day->copy()->endOfDay()];
        }

        return [now()->addMinutes(self::WINDOW_START_MINUTES), now()->addHours(self::WINDOW_HOURS)];
    }

    /**
     * @param array{niveau: string, evenements: array<int, array<string, mixed>>, cote_totale: float, probabilite: int, analysis: string} $draft
     */
    private function publish(array $draft): Pronostic
    {
        $niveau = $draft['niveau'];
        $events = $draft['evenements'];
        $first = $events[0] ?? [];
        $kickoff = ! empty($first['kickoff_at']) ? Carbon::parse($first['kickoff_at'])->setTimezone($this->timezone()) : now()->setTimezone($this->timezone());
        $eventCount = count($events);
        $cote = round((float) ($draft['cote_totale'] ?? 1.0), 2);
        $probabilite = (int) ($draft['probabilite'] ?? 70);
        $isVip = in_array($niveau, AppSetting::VIP_LEVELS, true);

        return Pronostic::create([
            'niveau' => $niveau,
            'couleur' => self::LEVEL_COLORS[$niveau] ?? '#6B7280',
            'cote_totale' => $cote,
            'probabilite' => $probabilite,
            'evenements' => $events,
            // Codes coupon réels à saisir plus tard dans l'admin, si besoin.
            'code_1xbet' => null,
            'code_melbet' => null,
            'match_date' => $kickoff->toDateString(),
            'match_time' => $kickoff->format('H:i'),
            'team_home' => $first['home'] ?? $niveau,
            'team_away' => $first['away'] ?? 'Adversaire',
            // « VIP … » : compté dans les résultats VIP du niveau (Admin > VIP et application).
            'competition' => ($isVip ? 'VIP ' : 'Combiné ') . $niveau,
            'prediction' => 'Combiné ' . $eventCount . ' événement' . ($eventCount > 1 ? 's' : ''),
            'odds' => $cote,
            'confidence' => $probabilite,
            'analysis' => $draft['analysis'] ?? 'Combiné généré automatiquement pour Binho Flash.',
            'is_vip' => $isVip,
            'published_at' => now(),
        ]);
    }

    /**
     * Une notification par coupon publié : la première tout de suite, puis une toutes les deux
     * minutes, calées sur la minute pile (le planificateur passe chaque minute à hh:mm:00).
     *
     * @param array<int, Pronostic> $published
     */
    private function scheduleNotifications(array $published): void
    {
        $batchId = (string) Str::uuid();
        $baseTime = now();
        $firstSlot = $baseTime->copy()->startOfMinute();

        foreach ($published as $sequence => $pronostic) {
            DB::table('scheduled_pronostic_notifications')->insert([
                'batch_id' => $batchId,
                'sequence' => $sequence,
                'pronostic_id' => $pronostic->id,
                'available_at' => $firstSlot->copy()->addMinutes($sequence * 2),
                'created_at' => $baseTime,
                'updated_at' => $baseTime,
            ]);
        }

        $this->call('binho:send-pronostic-notification', ['--batch' => $batchId]);
    }

    private function record(string $level, string $slot, string $status, string $message, ?int $pronosticId = null): void
    {
        PronosticRun::query()->updateOrCreate(
            ['run_date' => now()->setTimezone($this->timezone())->toDateString(), 'level' => $level, 'slot' => $slot],
            ['status' => $status, 'message' => mb_substr($message, 0, 1000), 'pronostic_id' => $pronosticId],
        );
    }

    private function failLevel(string $level, string $slot, string $message): void
    {
        Log::error('Génération automatique des pronostics impossible.', ['level' => $level, 'message' => $message]);
        $this->error($message);

        if (! $this->option('dry-run')) {
            // Une alerte par problème et par jour, pas une à chaque créneau.
            if (Cache::add('pronostics:alert:' . now()->toDateString() . ':' . md5($level . $message), true, now()->addDay())) {
                $this->whatsapp->send("⚠️ Binho Flash — génération {$level} : {$message}");
            }

            $this->record($level, $slot, PronosticRun::STATUS_ERROR, $message);
            $this->remember('error', $message);
        }
    }

    private function remember(string $status, string $message): void
    {
        Cache::forever(self::LAST_RUN_CACHE_KEY, [
            'status' => $status,
            'message' => mb_substr($message, 0, 600),
            'at' => now()->toIso8601String(),
        ]);
    }

    // Même fuseau que les créneaux : la journée et les traces sont celles du Bénin.
    private function timezone(): string
    {
        return PronosticRules::TIMEZONE;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\PronosticRun;
use App\Services\FixtureService;
use App\Services\MatchDataService;
use App\Services\OddsService;
use App\Services\SpecialMarketsGenerator;
use App\Support\AiProvider;
use App\Support\PronosticRules;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Génère et publie les 3 catégories spécialisées selon le prompt du fondateur :
 *  - GrossesCotes : combinés Équilibré (~30), Ambitieux (~50), Très risqué (~60)
 *  - Cartons      : cartons jaunes (cote cible ~2,00 · 1,80 à 2,30)
 *  - Buteurs      : buteurs (avec niveau de confiance ajusté selon la composition)
 */
class GenerateSpecialMarkets extends Command
{
    protected $signature = 'binho:generate-special-markets
        {--slot= : Heure du créneau (HH:MM), par défaut l’heure actuelle}
        {--module=all : Module à exécuter : all, grosses_cotes, cartons ou buteurs}
        {--dry-run : Affiche le résultat sans rien publier ni notifier}';

    protected $description = 'Génère et publie les pronostics Grosses cotes, Cartons jaunes et Buteurs.';

    public const DEFAULT_SCHEDULE = '10:30, 15:30';

    public const RUN_LEVEL = 'SpecialMarkets';

    private const WINDOW_START_MINUTES = 45;

    private const WINDOW_HOURS = 18;

    private const COLORS = [
        SpecialMarketsGenerator::LEVEL_GROSSES_COTES => '#D97706',
        SpecialMarketsGenerator::LEVEL_CARTONS => '#CA8A04',
        SpecialMarketsGenerator::LEVEL_BUTEURS => '#0D9488',
    ];

    public function __construct(
        private readonly FixtureService $fixtures,
        private readonly OddsService $odds,
        private readonly MatchDataService $matchData,
        private readonly SpecialMarketsGenerator $generator,
    ) {
        parent::__construct();
    }

    /**
     * @return array<int, string>
     */
    public static function schedule(): array
    {
        return PronosticRules::times(AppSetting::value('ai_schedule_special') ?? self::DEFAULT_SCHEDULE);
    }

    public function handle(): int
    {
        $timezone = PronosticRules::TIMEZONE;
        $now = now()->setTimezone($timezone);
        $slot = $this->option('slot') ?: $now->format('H:i');
        $module = strtolower((string) ($this->option('module') ?: 'all'));

        try {
            $fixtures = $this->fixtures->upcoming(
                now()->addMinutes(self::WINDOW_START_MINUTES),
                now()->addHours(self::WINDOW_HOURS),
            );
        } catch (Throwable $exception) {
            return $this->finish($slot, PronosticRun::STATUS_ERROR, 'Matchs de football indisponibles : ' . $exception->getMessage());
        }

        try {
            $fixtures = $this->odds->attach($fixtures);
        } catch (Throwable $exception) {
            Log::warning('Marchés spécialisés : cotes indisponibles.', ['error' => $exception->getMessage()]);
        }

        $enriched = $this->matchData->enrich($fixtures);

        if ($enriched === []) {
            return $this->finish($slot, PronosticRun::STATUS_EMPTY, 'Aucun match documenté avec cotes réelles sur la fenêtre.');
        }

        $dossier = $this->generator->buildDossier($enriched, $now);
        $key = AiProvider::key();
        $aiPayload = $key ? $this->generator->askAi($key, $dossier) : null;
        $result = $this->generator->validateAndNormalize($aiPayload ?? [], $enriched);

        // Complément mathématique serveur pour les paliers Grosses cotes non fournis (cotes réelles uniquement).
        $todayStart = $now->copy()->startOfDay();
        $existingTiers = Pronostic::query()
            ->where('niveau', SpecialMarketsGenerator::LEVEL_GROSSES_COTES)
            ->where('published_at', '>=', $todayStart)
            ->get(['evenements'])
            ->map(fn (Pronostic $p) => $p->evenements[0]['tier'] ?? null)
            ->filter()
            ->values()
            ->all();

        $aiTiers = array_column($result['combines'], 'tier');
        $fallbackCombines = $this->generator->buildServerCombines($enriched, [...$existingTiers, ...$aiTiers]);
        $combines = array_values(array_filter(
            [...$result['combines'], ...$fallbackCombines],
            fn ($c) => ! in_array($c['tier'], $existingTiers, true),
        ));

        $existingCartons = Pronostic::query()
            ->where('niveau', SpecialMarketsGenerator::LEVEL_CARTONS)
            ->where('published_at', '>=', $todayStart)
            ->get(['evenements'])
            ->map(fn (Pronostic $p) => mb_strtolower((string) ($p->evenements[0]['match'] ?? '')))
            ->filter()
            ->all();

        $existingButeurs = Pronostic::query()
            ->where('niveau', SpecialMarketsGenerator::LEVEL_BUTEURS)
            ->where('published_at', '>=', $todayStart)
            ->get(['evenements'])
            ->map(fn (Pronostic $p) => mb_strtolower((string) ($p->evenements[0]['match'] ?? '') . '|' . ($p->evenements[0]['joueur'] ?? '')))
            ->filter()
            ->all();

        $cartons = array_values(array_filter(
            $result['cartons_jaunes'],
            fn ($c) => ! in_array(mb_strtolower($c['match']), $existingCartons, true),
        ));

        $buteurs = array_values(array_filter(
            $result['buteurs'],
            fn ($b) => ! in_array(mb_strtolower($b['match'] . '|' . $b['joueur']), $existingButeurs, true),
        ));

        $wantCombines = in_array($module, ['all', 'grosses_cotes'], true);
        $wantCartons = in_array($module, ['all', 'cartons'], true);
        $wantButeurs = in_array($module, ['all', 'buteurs'], true);

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Dry-run : %d grosse(s) cote(s), %d carton(s), %d buteur(s).',
                $wantCombines ? count($combines) : 0,
                $wantCartons ? count($cartons) : 0,
                $wantButeurs ? count($buteurs) : 0,
            ));

            return self::SUCCESS;
        }

        $created = [];
        $disclaimer = $result['avertissement'];

        if ($wantCombines) {
            foreach ($combines as $combine) {
                $created[] = $this->publishCombine($combine, $disclaimer);
            }
        }

        if ($wantCartons) {
            foreach (array_slice($cartons, 0, 4) as $carton) {
                $created[] = $this->publishCarton($carton, $disclaimer);
            }
        }

        if ($wantButeurs) {
            foreach (array_slice($buteurs, 0, 4) as $buteur) {
                $created[] = $this->publishButeur($buteur, $disclaimer);
            }
        }

        if ($created === []) {
            return $this->finish(
                $slot,
                PronosticRun::STATUS_EMPTY,
                'Aucun pronostic fiable validé pour Grosses cotes / Cartons / Buteurs (' . count($enriched) . ' match(s) analysé(s)).',
            );
        }

        $this->notify($created);

        $summary = collect($created)->countBy('niveau')->map(fn ($n, $lvl) => "{$n} {$lvl}")->implode(', ');

        return $this->finish($slot, PronosticRun::STATUS_PUBLISHED, "Publié : {$summary}.", $created[0]->id ?? null);
    }

    /**
     * @param array<string, mixed> $combine
     */
    private function publishCombine(array $combine, string $disclaimer): Pronostic
    {
        $events = array_map(function (array $sel, int $index) use ($combine) {
            return [
                ...$sel,
                // Métadonnées du palier portées par le premier événement pour lecture rapide & affichage verrouillé.
                ...($index === 0 ? [
                    'tier' => $combine['tier'],
                    'tier_label' => $combine['tier_label'],
                    'risque' => $combine['risque'],
                    'probabilite_globale' => $combine['probabilite_globale'],
                    'points_de_vigilance' => $combine['points_de_vigilance'],
                ] : []),
            ];
        }, $combine['selections'], array_keys($combine['selections']));

        $first = $events[0];
        $kickoff = ! empty($first['kickoff_at'])
            ? Carbon::parse($first['kickoff_at'])->setTimezone(PronosticRules::TIMEZONE)
            : now()->setTimezone(PronosticRules::TIMEZONE);

        $validUntil = collect($events)
            ->pluck('kickoff_at')
            ->filter()
            ->map(fn ($at) => Carbon::parse($at))
            ->sort()
            ->first();

        return Pronostic::create([
            'niveau' => SpecialMarketsGenerator::LEVEL_GROSSES_COTES,
            'couleur' => self::COLORS[SpecialMarketsGenerator::LEVEL_GROSSES_COTES],
            'cote_totale' => $combine['cote_totale'],
            'probabilite' => max(1, (int) round($combine['probabilite_globale'])),
            'evenements' => $events,
            'code_1xbet' => null,
            'code_melbet' => null,
            'match_date' => $kickoff->toDateString(),
            'match_time' => $kickoff->format('H:i'),
            'team_home' => $first['home'] ?: 'Combiné',
            'team_away' => $first['away'] ?: $combine['tier_label'],
            'competition' => 'Grosses cotes · ' . $combine['tier_label'],
            'prediction' => sprintf('Combiné %s · %d sélections', $combine['tier_label'], count($events)),
            'odds' => $combine['cote_totale'],
            'confidence' => max(1, (int) round($combine['probabilite_globale'])),
            'analysis' => $this->generator->formatCombineAnalysis($combine, $disclaimer),
            'is_vip' => true,
            'published_at' => now(),
            'valid_until' => $validUntil,
        ]);
    }

    /**
     * @param array<string, mixed> $carton
     */
    private function publishCarton(array $carton, string $disclaimer): Pronostic
    {
        $kickoff = ! empty($carton['kickoff_at'])
            ? Carbon::parse($carton['kickoff_at'])->setTimezone(PronosticRules::TIMEZONE)
            : now()->setTimezone(PronosticRules::TIMEZONE);

        $event = [
            'match' => $carton['match'],
            'competition' => $carton['competition'],
            'kickoff' => $carton['kickoff'],
            'kickoff_at' => $carton['kickoff_at'],
            'marche' => 'Cartons jaunes',
            'pronostic' => $carton['pronostic'],
            'cote' => $carton['cote'],
            'probabilite' => $carton['probabilite'],
            'valeur' => $carton['valeur'],
            'confiance' => $carton['confiance'],
            'justification' => $carton['justification'],
        ];

        return Pronostic::create([
            'niveau' => SpecialMarketsGenerator::LEVEL_CARTONS,
            'couleur' => self::COLORS[SpecialMarketsGenerator::LEVEL_CARTONS],
            'cote_totale' => $carton['cote'],
            'probabilite' => $carton['probabilite'],
            'evenements' => [$event],
            'code_1xbet' => null,
            'code_melbet' => null,
            'match_date' => $kickoff->toDateString(),
            'match_time' => $kickoff->format('H:i'),
            'team_home' => $carton['home'] ?: $carton['match'],
            'team_away' => $carton['away'] ?: 'Cartons',
            'competition' => $carton['competition'] ?: 'Cartons jaunes',
            'prediction' => $carton['pronostic'],
            'odds' => $carton['cote'],
            'confidence' => $carton['probabilite'],
            'analysis' => "BIG BOSS — Analyse cartons jaunes\n\n{$carton['justification']}\n\nValeur estimée : +" . round($carton['valeur'] * 100, 1) . " % · Confiance : {$carton['confiance']}.\n\n{$disclaimer}",
            'is_vip' => true,
            'published_at' => now(),
            'valid_until' => ! empty($carton['kickoff_at']) ? Carbon::parse($carton['kickoff_at']) : null,
        ]);
    }

    /**
     * @param array<string, mixed> $buteur
     */
    private function publishButeur(array $buteur, string $disclaimer): Pronostic
    {
        $kickoff = ! empty($buteur['kickoff_at'])
            ? Carbon::parse($buteur['kickoff_at'])->setTimezone(PronosticRules::TIMEZONE)
            : now()->setTimezone(PronosticRules::TIMEZONE);

        $event = [
            'match' => $buteur['match'],
            'competition' => $buteur['competition'],
            'kickoff' => $buteur['kickoff'],
            'kickoff_at' => $buteur['kickoff_at'],
            'joueur' => $buteur['joueur'],
            'marche' => $buteur['marche'],
            'pronostic' => $buteur['pronostic'],
            'cote' => $buteur['cote'],
            'probabilite' => $buteur['probabilite'],
            'valeur' => $buteur['valeur'],
            'confiance' => $buteur['confiance'],
            'composition_confirmee' => $buteur['composition_confirmee'],
            'justification' => $buteur['justification'],
        ];

        $compoNote = $buteur['composition_confirmee']
            ? 'Composition officielle confirmée.'
            : 'Composition officielle non confirmée : confiance ajustée à la baisse.';

        return Pronostic::create([
            'niveau' => SpecialMarketsGenerator::LEVEL_BUTEURS,
            'couleur' => self::COLORS[SpecialMarketsGenerator::LEVEL_BUTEURS],
            'cote_totale' => $buteur['cote'],
            'probabilite' => $buteur['probabilite'],
            'evenements' => [$event],
            'code_1xbet' => null,
            'code_melbet' => null,
            'match_date' => $kickoff->toDateString(),
            'match_time' => $kickoff->format('H:i'),
            'team_home' => $buteur['home'] ?: $buteur['match'],
            'team_away' => $buteur['away'] ?: 'Buteurs',
            'competition' => $buteur['competition'] ?: 'Buteurs',
            'prediction' => $buteur['pronostic'],
            'odds' => $buteur['cote'],
            'confidence' => $buteur['probabilite'],
            'analysis' => "BIG BOSS — Analyse buteur\n\n{$buteur['justification']}\n\n{$compoNote}\nValeur estimée : +" . round($buteur['valeur'] * 100, 1) . " % · Confiance : {$buteur['confiance']}.\n\n{$disclaimer}",
            'is_vip' => true,
            'published_at' => now(),
            'valid_until' => ! empty($buteur['kickoff_at']) ? Carbon::parse($buteur['kickoff_at']) : null,
        ]);
    }

    /**
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
                ['run_date' => now()->setTimezone(PronosticRules::TIMEZONE)->toDateString(), 'level' => self::RUN_LEVEL, 'slot' => $slot],
                ['status' => $status, 'message' => mb_substr($message, 0, 1000), 'pronostic_id' => $pronosticId],
            );
        }

        return self::SUCCESS;
    }
}

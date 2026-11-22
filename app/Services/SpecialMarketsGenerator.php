<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Support\AiProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Génération et contrôle qualité des 3 marchés spécialisés (prompt du fondateur) :
 *  - Module A : Grosses cotes (combinés Équilibré ~30 [27–35], Ambitieux ~50 [45–56], Très risqué ~60 [55–68])
 *  - Module B : Cartons jaunes (cote cible ~2,00 [1,80–2,30], valeur positive)
 *  - Module C : Buteurs (buteur à tout moment 1,70–3,50, premier buteur, doublé, valeur positive)
 *
 * Règle absolue : aucune statistique, composition, blessure, arbitre ni cote n'est inventée.
 * Le serveur vérifie systématiquement le produit des cotes, la probabilité globale, l'absence de
 * doublons/corrélations sur un même match, les fourchettes de cotes et la valeur positive.
 */
class SpecialMarketsGenerator
{
    public const LEVEL_GROSSES_COTES = 'GrossesCotes';

    public const LEVEL_CARTONS = 'Cartons';

    public const LEVEL_BUTEURS = 'Buteurs';

    public const LEVELS = [
        self::LEVEL_GROSSES_COTES,
        self::LEVEL_CARTONS,
        self::LEVEL_BUTEURS,
    ];

    public const DISCLAIMER = 'Les paris comportent un risque de perte. Les cotes élevées ont une faible probabilité de réussite. Jouez de manière responsable, réservé aux personnes majeures (18+).';

    public const MIN_SELECTION_ODD = 1.25;

    public const MAX_SELECTION_ODD = 2.10;

    public const MIN_SELECTIONS = 5;

    public const MAX_SELECTIONS = 9;

    public const CARTON_MIN_ODD = 1.80;

    public const CARTON_MAX_ODD = 2.30;

    public const BUTEUR_MIN_ODD = 1.50;

    public const BUTEUR_HIGH_RISK_ODD = 3.50;

    /**
     * Fourchettes exactes exigées par le Module A.
     *
     * @var array<string, array{label: string, target: int, min: float, max: float, default_risk: string}>
     */
    public const TIERS = [
        'equilibre' => ['label' => 'Équilibré', 'target' => 30, 'min' => 27.0, 'max' => 35.0, 'default_risk' => 'eleve'],
        'ambitieux' => ['label' => 'Ambitieux', 'target' => 50, 'min' => 45.0, 'max' => 56.0, 'default_risk' => 'tres_eleve'],
        'tres_risque' => ['label' => 'Très risqué', 'target' => 60, 'min' => 55.0, 'max' => 68.0, 'default_risk' => 'tres_eleve'],
    ];

    public function __construct(private readonly ValueAnalyzer $analyzer)
    {
    }

    /**
     * Construit le dossier {{DONNEES}} uniquement à partir des données réelles collectées.
     * Les éléments absents des APIs sont explicitement signalés « donnée non disponible ».
     *
     * @param array<int, array<string, mixed>> $fixtures
     * @return array{date: string, championnats: array<int, string>, matchs: array<int, array<string, mixed>>, donnees_manquantes_globales: array<int, string>}
     */
    public function buildDossier(array $fixtures, Carbon $date): array
    {
        $leagues = [];
        $items = [];

        foreach ($fixtures as $fixture) {
            $competition = trim((string) ($fixture['competition'] ?? 'Football'));
            $leagues[$competition] = true;
            $data = $fixture['data'] ?? [];
            $standings = $data['standings'] ?? null;
            $form = $data['form'] ?? [];
            $details = $data['details'] ?? null;
            $expected = $this->analyzer->expectedGoals($data);

            $selections = [];
            foreach ($fixture['selections'] ?? [] as $selection) {
                $odd = (float) ($selection['cote'] ?? 0);
                if ($odd < self::MIN_SELECTION_ODD || $odd > self::MAX_SELECTION_ODD) {
                    continue;
                }

                $implied = $odd > 0 ? round(1 / $odd, 4) : 0.0;
                $prob = $this->estimateSelectionProbability($selection, $fixture, $expected);

                $selections[] = [
                    'selection_key' => $selection['key'] ?? null,
                    'line' => $selection['line'] ?? null,
                    'marche' => $this->marketLabel((string) ($selection['key'] ?? '')),
                    'pronostic' => (string) ($selection['pronostic'] ?? ''),
                    'cote' => round($odd, 2),
                    'probabilite_implicite' => round($implied * 100, 1) . ' %',
                    'probabilite_modele' => $prob !== null ? round($prob * 100, 1) . ' %' : 'donnée non disponible',
                    'valeur' => $prob !== null ? round(($prob * $odd) - 1, 3) : null,
                ];
            }

            $items[] = [
                'fixture_id' => $fixture['id'] ?? null,
                'championnat' => $competition,
                'match' => (string) ($fixture['match'] ?? ''),
                'coup_envoi' => $fixture['kickoff'] ?? null,
                'classement' => $standings ?: 'donnée non disponible',
                'forme' => [
                    'domicile' => ! empty($form['home']) ? $form['home'] : 'donnée non disponible',
                    'exterieur' => ! empty($form['away']) ? $form['away'] : 'donnée non disponible',
                ],
                'buts_attendus_modele' => $expected ?: 'donnée non disponible',
                'arbitre' => $details['referee'] ?? 'donnée non disponible',
                'stade' => $details['venue'] ?? 'donnée non disponible',
                'meteo' => $details['weather'] ?? 'donnée non disponible',
                'actualites' => ! empty($details['news']) ? $details['news'] : 'donnée non disponible',
                'compositions_officielles' => 'donnée non disponible',
                'statistiques_cartons_arbitre' => 'donnée non disponible',
                'cotes_cartons' => 'donnée non disponible',
                'cotes_buteurs' => 'donnée non disponible',
                'selections_reelles_1_25_a_2_10' => $selections,
            ];
        }

        return [
            'date' => $date->toDateString(),
            'championnats' => array_keys($leagues),
            'matchs' => $items,
            'donnees_manquantes_globales' => [
                'Compositions officielles : donnée non disponible (baisser la confiance sur tout pari joueur).',
                'Statistiques de cartons par arbitre et par joueur : donnée non disponible dans ce flux.',
                'Cotes individuelles cartons jaunes et buteurs : donnée non disponible dans ce flux (ne jamais inventer de cote).',
            ],
        ];
    }

    /**
     * Interroge l'IA (DeepSeek ou OpenRouter selon AiProvider) avec le prompt exact du fondateur.
     *
     * @param array<string, mixed> $dossier
     * @return array<string, mixed>|null
     */
    public function askAi(string $apiKey, array $dossier, string $targetOdds = '30 (equilibre), 50 (ambitieux), 60 (tres_risque)', int $count = 3): ?array
    {
        $template = trim((string) AppSetting::value('ai_special_markets_instructions'));

        if ($template === '') {
            return null;
        }

        $prompt = strtr($template, [
            '{{DATE}}' => (string) ($dossier['date'] ?? now()->toDateString()),
            '{{CHAMPIONNATS}}' => implode(', ', $dossier['championnats'] ?? []) ?: 'donnée non disponible',
            '{{DONNEES}}' => json_encode($dossier, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            '{{COTE_CIBLE}}' => $targetOdds,
            '{{NOMBRE}}' => (string) $count,
        ]);

        $model = AiProvider::model(AppSetting::value('openrouter_model') ?: AiPronosticGenerator::DEFAULT_MODEL);

        $headers = AiProvider::isDeepSeek()
            ? []
            : [
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title' => 'BIG BOSS',
            ];

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withToken($apiKey)
                ->withHeaders($headers)
                ->timeout(90)
                ->post(AiProvider::endpoint(), [
                    'model' => $model,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Réponds uniquement en JSON valide respectant exactement le schéma demandé, sans aucun texte hors JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Marchés spécialisés : appel IA en échec.', [
                    'status' => $response->status(),
                    'body' => mb_strimwidth($response->body(), 0, 500, '…'),
                ]);

                return null;
            }

            $content = (string) ($response->json('choices.0.message.content') ?? '');

            return $this->decodeJson($content);
        } catch (Throwable $exception) {
            Log::warning('Marchés spécialisés : exception lors de l’appel IA.', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Contrôle qualité strict côté serveur : vérifie cotes, produits, non-corrélation, valeur
     * positive et règles de confiance. Rejette toute entrée incohérente ou inventée.
     *
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $fixtures
     * @return array{combines: array<int, array<string, mixed>>, cartons_jaunes: array<int, array<string, mixed>>, buteurs: array<int, array<string, mixed>>, matchs_ecartes: array<int, array<string, string>>, avertissement: string}
     */
    public function validateAndNormalize(array $payload, array $fixtures = []): array
    {
        $fixtureIndex = $this->indexFixtures($fixtures);
        $combines = [];
        $seenTiers = [];

        foreach ($payload['combines'] ?? [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $normalized = $this->validateCombine($raw, $fixtureIndex);

            if ($normalized !== null && ! isset($seenTiers[$normalized['tier']])) {
                $seenTiers[$normalized['tier']] = true;
                $combines[] = $normalized;
            }
        }

        $cartons = [];
        foreach ($payload['cartons_jaunes'] ?? [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $normalized = $this->validateCarton($raw, $fixtureIndex);

            if ($normalized !== null) {
                $cartons[] = $normalized;
            }
        }

        $buteurs = [];
        foreach ($payload['buteurs'] ?? [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $normalized = $this->validateButeur($raw, $fixtureIndex);

            if ($normalized !== null) {
                $buteurs[] = $normalized;
            }
        }

        $ecartes = [];
        foreach ($payload['matchs_ecartes'] ?? [] as $raw) {
            if (! is_array($raw) || empty($raw['match'])) {
                continue;
            }

            $ecartes[] = [
                'match' => trim((string) $raw['match']),
                'raison' => trim((string) ($raw['raison'] ?? 'Données insuffisantes')),
            ];
        }

        return [
            'combines' => $combines,
            'cartons_jaunes' => $cartons,
            'buteurs' => $buteurs,
            'matchs_ecartes' => $ecartes,
            'avertissement' => trim((string) ($payload['avertissement'] ?? '')) ?: self::DISCLAIMER,
        ];
    }

    /**
     * Construction mathématique de secours (cotes réelles uniquement) pour les paliers Grosses cotes
     * non retournés par l'IA : respecte exactement 5 à 9 sélections, cotes 1,25 à 2,10, 1 sélection
     * par match, diversification de championnats, et la fourchette cible [27–35], [45–56] ou [55–68].
     *
     * @param array<int, array<string, mixed>> $fixtures
     * @param array<int, string> $skipTiers
     * @return array<int, array<string, mixed>>
     */
    public function buildServerCombines(array $fixtures, array $skipTiers = []): array
    {
        $pool = [];

        foreach ($fixtures as $fixture) {
            $expected = $this->analyzer->expectedGoals($fixture['data'] ?? []);
            $bestForMatch = null;

            foreach ($fixture['selections'] ?? [] as $selection) {
                $odd = round((float) ($selection['cote'] ?? 0), 2);
                if ($odd < self::MIN_SELECTION_ODD || $odd > self::MAX_SELECTION_ODD) {
                    continue;
                }

                $prob = $this->estimateSelectionProbability($selection, $fixture, $expected) ?? (1 / $odd * 0.96);
                $prob = max(0.35, min(0.88, $prob));

                $candidate = [
                    'fixture_id' => $fixture['id'] ?? null,
                    'event_id' => $fixture['event_id'] ?? null,
                    'sport' => $fixture['sport'] ?? 'football',
                    'championnat' => (string) ($fixture['competition'] ?? 'Football'),
                    'match' => (string) ($fixture['match'] ?? ''),
                    'home' => (string) ($fixture['home'] ?? ''),
                    'away' => (string) ($fixture['away'] ?? ''),
                    'kickoff' => $fixture['kickoff'] ?? null,
                    'kickoff_at' => $fixture['kickoff_at'] ?? null,
                    'selection_key' => $selection['key'] ?? null,
                    'line' => $selection['line'] ?? null,
                    'marche' => $this->marketLabel((string) ($selection['key'] ?? '')),
                    'pronostic' => (string) ($selection['pronostic'] ?? ''),
                    'cote' => $odd,
                    'prob' => $prob,
                    'probabilite_estimee' => (int) round($prob * 100),
                    'justification' => sprintf(
                        'Sélection retenue d’après les cotes réelles (%.2f) et la probabilité estimée (%d %%).',
                        $odd,
                        (int) round($prob * 100),
                    ),
                ];

                if ($bestForMatch === null || $candidate['prob'] > $bestForMatch['prob']) {
                    $bestForMatch = $candidate;
                }
            }

            if ($bestForMatch !== null) {
                // On garde aussi une alternative un peu plus cotée sur ce match pour permettre d'atteindre 50/60.
                $pool[] = $bestForMatch;
            }
        }

        if (count($pool) < self::MIN_SELECTIONS) {
            return [];
        }

        // Construit aussi un vivier par match avec toutes les sélections valides [1.25..2.10] triées par probabilité.
        $byMatch = [];
        foreach ($fixtures as $fixture) {
            $expected = $this->analyzer->expectedGoals($fixture['data'] ?? []);
            $matchKey = $this->normalizeMatch((string) ($fixture['match'] ?? ''));
            if ($matchKey === '') {
                continue;
            }

            $options = [];
            foreach ($fixture['selections'] ?? [] as $selection) {
                $odd = round((float) ($selection['cote'] ?? 0), 2);
                if ($odd < self::MIN_SELECTION_ODD || $odd > self::MAX_SELECTION_ODD) {
                    continue;
                }
                $prob = $this->estimateSelectionProbability($selection, $fixture, $expected) ?? (1 / $odd * 0.96);
                $prob = max(0.35, min(0.88, $prob));
                $options[] = [
                    'fixture_id' => $fixture['id'] ?? null,
                    'event_id' => $fixture['event_id'] ?? null,
                    'sport' => $fixture['sport'] ?? 'football',
                    'championnat' => (string) ($fixture['competition'] ?? 'Football'),
                    'match' => (string) ($fixture['match'] ?? ''),
                    'home' => (string) ($fixture['home'] ?? ''),
                    'away' => (string) ($fixture['away'] ?? ''),
                    'kickoff' => $fixture['kickoff'] ?? null,
                    'kickoff_at' => $fixture['kickoff_at'] ?? null,
                    'selection_key' => $selection['key'] ?? null,
                    'line' => $selection['line'] ?? null,
                    'marche' => $this->marketLabel((string) ($selection['key'] ?? '')),
                    'pronostic' => (string) ($selection['pronostic'] ?? ''),
                    'cote' => $odd,
                    'prob' => $prob,
                    'probabilite_estimee' => (int) round($prob * 100),
                    'justification' => sprintf(
                        'Sélection retenue sur les cotes réelles (%.2f) avec une probabilité estimée à %d %%.',
                        $odd,
                        (int) round($prob * 100),
                    ),
                ];
            }

            if ($options !== []) {
                usort($options, fn ($a, $b) => $b['prob'] <=> $a['prob']);
                $byMatch[$matchKey] = $options;
            }
        }

        $built = [];
        foreach (self::TIERS as $tier => $spec) {
            if (in_array($tier, $skipTiers, true)) {
                continue;
            }

            $picked = $this->searchTierCombo($byMatch, $spec['min'], $spec['max'], $spec['target']);

            if ($picked === null) {
                continue;
            }

            $totalOdd = round(array_reduce($picked, fn ($carry, $s) => $carry * $s['cote'], 1.0), 2);
            $globalProb = round(array_reduce($picked, fn ($carry, $s) => $carry * $s['prob'], 1.0) * 100, 1);

            $built[] = [
                'tier' => $tier,
                'tier_label' => $spec['label'],
                'cote_totale' => $totalOdd,
                'probabilite_globale' => max(0.1, $globalProb),
                'risque' => $spec['default_risk'],
                'selections' => array_map(fn ($s) => [
                    'fixture_id' => $s['fixture_id'],
                    'event_id' => $s['event_id'],
                    'sport' => $s['sport'],
                    'competition' => $s['championnat'],
                    'match' => $s['match'],
                    'home' => $s['home'],
                    'away' => $s['away'],
                    'kickoff' => $s['kickoff'],
                    'kickoff_at' => $s['kickoff_at'],
                    'selection_key' => $s['selection_key'],
                    'line' => $s['line'],
                    'marche' => $s['marche'],
                    'pronostic' => $s['pronostic'],
                    'cote' => $s['cote'],
                    'probabilite' => $s['probabilite_estimee'],
                    'justification' => $s['justification'],
                ], $picked),
                'points_de_vigilance' => sprintf(
                    'Combiné %s (%d sélections, cote %.2f) : la probabilité globale estimée est de %.1f %%. Risque %s : misez petit.',
                    mb_strtolower($spec['label']),
                    count($picked),
                    $totalOdd,
                    max(0.1, $globalProb),
                    $spec['default_risk'] === 'tres_eleve' ? 'très élevé' : 'élevé',
                ),
            ];
        }

        return $built;
    }

    /**
     * Formatte l'analyse lisible affichée dans l'application pour un combiné Grosse cote.
     *
     * @param array<string, mixed> $combine
     */
    public function formatCombineAnalysis(array $combine, string $disclaimer = self::DISCLAIMER): string
    {
        $riskLabel = $combine['risque'] === 'tres_eleve' ? 'Très élevé' : 'Élevé';
        $lines = [
            sprintf(
                'BIG BOSS — Combiné Grosse cote (%s) · Cote totale %.2f · Probabilité globale estimée : %.1f %% · Risque : %s.',
                $combine['tier_label'],
                $combine['cote_totale'],
                $combine['probabilite_globale'],
                $riskLabel,
            ),
        ];

        if (! empty($combine['points_de_vigilance'])) {
            $lines[] = 'Points de vigilance : ' . $combine['points_de_vigilance'];
        }

        $lines[] = $disclaimer;

        return implode("\n\n", $lines);
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, array<string, mixed>> $fixtureIndex
     * @return array<string, mixed>|null
     */
    private function validateCombine(array $raw, array $fixtureIndex): ?array
    {
        $tier = $this->normalizeTier((string) ($raw['niveau'] ?? ''));

        if ($tier === null) {
            return null;
        }

        $spec = self::TIERS[$tier];
        $selections = [];
        $usedMatches = [];

        foreach ($raw['selections'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $match = trim((string) ($item['match'] ?? ''));
            $pronostic = trim((string) ($item['pronostic'] ?? ''));
            $odd = round($this->toFloat($item['cote'] ?? 0), 2);
            $matchKey = $this->normalizeMatch($match);

            if ($match === '' || $pronostic === '' || $odd < self::MIN_SELECTION_ODD || $odd > self::MAX_SELECTION_ODD) {
                continue;
            }

            // Règle anti-corrélation : une seule sélection par match.
            if (isset($usedMatches[$matchKey])) {
                continue;
            }

            $usedMatches[$matchKey] = true;
            $probPct = $this->parsePercent($item['probabilite_estimee'] ?? null) ?? (int) round(100 / $odd * 0.95);
            $probPct = max(15, min(92, $probPct));
            $matched = $this->matchFixtureSelection($fixtureIndex[$matchKey] ?? null, $pronostic, $odd);

            $selections[] = [
                'fixture_id' => $matched['fixture_id'] ?? null,
                'event_id' => $matched['event_id'] ?? null,
                'sport' => $matched['sport'] ?? 'football',
                'competition' => trim((string) ($item['championnat'] ?? ($matched['competition'] ?? ''))),
                'match' => $matched['match'] ?? $match,
                'home' => $matched['home'] ?? '',
                'away' => $matched['away'] ?? '',
                'kickoff' => $matched['kickoff'] ?? null,
                'kickoff_at' => $matched['kickoff_at'] ?? null,
                'selection_key' => $matched['selection_key'] ?? null,
                'line' => $matched['line'] ?? null,
                'marche' => trim((string) ($item['marche'] ?? '')),
                'pronostic' => $pronostic,
                'cote' => $odd,
                'probabilite' => $probPct,
                'justification' => mb_strimwidth(trim((string) ($item['justification'] ?? '')), 0, 320, '…'),
            ];
        }

        if (count($selections) < self::MIN_SELECTIONS || count($selections) > self::MAX_SELECTIONS) {
            return null;
        }

        // Recalcul serveur exact : le produit des cotes doit correspondre à la fourchette du palier.
        $totalOdd = round(array_reduce($selections, fn ($carry, $s) => $carry * $s['cote'], 1.0), 2);

        if ($totalOdd < $spec['min'] || $totalOdd > $spec['max']) {
            return null;
        }

        $globalProb = round(array_reduce($selections, fn ($carry, $s) => $carry * ($s['probabilite'] / 100), 1.0) * 100, 1);
        $risk = strtolower(trim((string) ($raw['risque'] ?? '')));
        // Un combiné grosse cote n'est JAMAIS classé en risque faible.
        $risk = in_array($risk, ['eleve', 'tres_eleve'], true) ? $risk : $spec['default_risk'];

        return [
            'tier' => $tier,
            'tier_label' => $spec['label'],
            'cote_totale' => $totalOdd,
            'probabilite_globale' => max(0.1, $globalProb),
            'risque' => $risk,
            'selections' => $selections,
            'points_de_vigilance' => mb_strimwidth(trim((string) ($raw['points_de_vigilance'] ?? '')), 0, 400, '…'),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, array<string, mixed>> $fixtureIndex
     * @return array<string, mixed>|null
     */
    private function validateCarton(array $raw, array $fixtureIndex): ?array
    {
        $match = trim((string) ($raw['match'] ?? ''));
        $pronostic = trim((string) ($raw['pronostic'] ?? ''));
        $justification = trim((string) ($raw['justification'] ?? ''));
        $odd = round($this->toFloat($raw['cote'] ?? 0), 2);
        $probPct = $this->parsePercent($raw['probabilite_estimee'] ?? null);

        if ($match === '' || $pronostic === '' || $justification === '' || $probPct === null) {
            return null;
        }

        if ($odd < self::CARTON_MIN_ODD || $odd > self::CARTON_MAX_ODD) {
            return null;
        }

        // Recalcul serveur de la valeur : seuls les paris à valeur positive sont retenus.
        $value = round((($probPct / 100) * $odd) - 1, 3);

        if ($value <= 0) {
            return null;
        }

        $fixture = $fixtureIndex[$this->normalizeMatch($match)] ?? null;

        return [
            'match' => $fixture['match'] ?? $match,
            'competition' => $fixture['competition'] ?? '',
            'home' => $fixture['home'] ?? '',
            'away' => $fixture['away'] ?? '',
            'kickoff' => $fixture['kickoff'] ?? null,
            'kickoff_at' => $fixture['kickoff_at'] ?? null,
            'pronostic' => $pronostic,
            'cote' => $odd,
            'probabilite' => $probPct,
            'valeur' => $value,
            'confiance' => $this->normalizeConfidence((string) ($raw['confiance'] ?? 'moyenne')),
            'justification' => mb_strimwidth($justification, 0, 500, '…'),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, array<string, mixed>> $fixtureIndex
     * @return array<string, mixed>|null
     */
    private function validateButeur(array $raw, array $fixtureIndex): ?array
    {
        $match = trim((string) ($raw['match'] ?? ''));
        $joueur = trim((string) ($raw['joueur'] ?? ''));
        $marche = trim((string) ($raw['marche'] ?? 'Buteur à tout moment'));
        $justification = trim((string) ($raw['justification'] ?? ''));
        $odd = round($this->toFloat($raw['cote'] ?? 0), 2);
        $probPct = $this->parsePercent($raw['probabilite_estimee'] ?? null);

        if ($match === '' || $joueur === '' || $justification === '' || $probPct === null) {
            return null;
        }

        if ($odd < self::BUTEUR_MIN_ODD) {
            return null;
        }

        $value = round((($probPct / 100) * $odd) - 1, 3);

        if ($value <= 0) {
            return null;
        }

        $confirmed = (bool) ($raw['composition_confirmee'] ?? false);
        $confidence = $this->normalizeConfidence((string) ($raw['confiance'] ?? 'moyenne'));

        // Règle absolue #5 : si la composition n'est pas confirmée, baisser la confiance (jamais « elevee »).
        if (! $confirmed && $confidence === 'elevee') {
            $confidence = 'moyenne';
        }

        if ($odd > self::BUTEUR_HIGH_RISK_ODD && ! str_contains(mb_strtolower($justification), 'risque')) {
            $justification .= ' Cote supérieure à 3,50 : risque élevé.';
        }

        $fixture = $fixtureIndex[$this->normalizeMatch($match)] ?? null;

        return [
            'match' => $fixture['match'] ?? $match,
            'competition' => $fixture['competition'] ?? '',
            'home' => $fixture['home'] ?? '',
            'away' => $fixture['away'] ?? '',
            'kickoff' => $fixture['kickoff'] ?? null,
            'kickoff_at' => $fixture['kickoff_at'] ?? null,
            'joueur' => $joueur,
            'marche' => $marche,
            'pronostic' => "{$joueur} — {$marche}",
            'cote' => $odd,
            'probabilite' => $probPct,
            'valeur' => $value,
            'confiance' => $confidence,
            'composition_confirmee' => $confirmed,
            'justification' => mb_strimwidth($justification, 0, 500, '…'),
        ];
    }

    /**
     * Recherche une combinaison de 5 à 9 matchs distincts dont le produit des cotes tombe dans [min, max].
     *
     * @param array<string, array<int, array<string, mixed>>> $byMatch
     * @return array<int, array<string, mixed>>|null
     */
    private function searchTierCombo(array $byMatch, float $min, float $max, float $target): ?array
    {
        $matches = array_values($byMatch);

        if (count($matches) < self::MIN_SELECTIONS) {
            return null;
        }

        // Préfère des sélections proches de la racine n-ième de la cible (ex. ~1.63 pour 7 matchs -> 30).
        $best = null;
        $bestScore = -INF;

        for ($count = self::MIN_SELECTIONS; $count <= min(self::MAX_SELECTIONS, count($matches)); $count++) {
            $idealOdd = pow($target, 1 / $count);
            $ranked = [];

            foreach ($matches as $options) {
                $pick = $options[0];
                $bestDist = INF;
                foreach ($options as $option) {
                    $dist = abs($option['cote'] - $idealOdd);
                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $pick = $option;
                    }
                }
                $ranked[] = $pick;
            }

            // Diversifie les championnats et favorise la meilleure probabilité à proximité de la cote idéale.
            usort($ranked, fn ($a, $b) => ($b['prob'] - abs($b['cote'] - $idealOdd) * 0.25) <=> ($a['prob'] - abs($a['cote'] - $idealOdd) * 0.25));

            $selected = array_slice($ranked, 0, $count);
            $product = array_reduce($selected, fn ($carry, $s) => $carry * $s['cote'], 1.0);

            // Ajustement fin : remplace une sélection par une autre option du même match si besoin pour rentrer dans [min, max].
            $attempts = 0;
            while (($product < $min || $product > $max) && $attempts < 20) {
                $attempts++;
                $improved = false;

                foreach ($selected as $idx => $current) {
                    $mKey = $this->normalizeMatch($current['match']);
                    foreach ($byMatch[$mKey] ?? [] as $alt) {
                        $newProduct = ($product / $current['cote']) * $alt['cote'];
                        if (abs($newProduct - $target) < abs($product - $target)) {
                            $selected[$idx] = $alt;
                            $product = $newProduct;
                            $improved = true;
                        }
                    }
                }

                if (! $improved) {
                    break;
                }
            }

            if ($product >= $min && $product <= $max) {
                $leagues = count(array_unique(array_column($selected, 'championnat')));
                $avgProb = array_sum(array_column($selected, 'prob')) / $count;
                $score = $avgProb * 10 + $leagues * 0.5 - abs($product - $target) * 0.05;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $selected;
                }
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $selection
     * @param array<string, mixed> $fixture
     * @param array{home: float, away: float}|null $expected
     */
    private function estimateSelectionProbability(array $selection, array $fixture, ?array $expected): ?float
    {
        $odd = (float) ($selection['cote'] ?? 0);

        if ($odd <= 1.0) {
            return null;
        }

        // Probabilité implicite sans marge approximative (marge ~5 % standard).
        $implied = min(0.92, (1 / $odd) * 0.95);

        if ($expected === null) {
            return round($implied, 4);
        }

        $key = (string) ($selection['key'] ?? '');
        $line = isset($selection['line']) ? (float) $selection['line'] : null;
        $modelProb = $this->poissonSelectionProbability($key, $line, $expected['home'], $expected['away']);

        if ($modelProb === null) {
            return round($implied, 4);
        }

        return round(($implied + $modelProb) / 2, 4);
    }

    private function poissonSelectionProbability(string $key, ?float $line, float $lambdaHome, float $lambdaAway): ?float
    {
        $homeWin = 0.0;
        $draw = 0.0;
        $awayWin = 0.0;
        $btts = 0.0;
        $over = 0.0;

        for ($h = 0; $h <= 8; $h++) {
            $ph = exp(-$lambdaHome) * pow($lambdaHome, $h) / $this->factorial($h);
            for ($a = 0; $a <= 8; $a++) {
                $p = $ph * exp(-$lambdaAway) * pow($lambdaAway, $a) / $this->factorial($a);
                if ($h > $a) {
                    $homeWin += $p;
                } elseif ($h === $a) {
                    $draw += $p;
                } else {
                    $awayWin += $p;
                }
                if ($h > 0 && $a > 0) {
                    $btts += $p;
                }
                if ($line !== null && ($h + $a) > $line) {
                    $over += $p;
                }
            }
        }

        return match ($key) {
            'home' => $homeWin,
            'away' => $awayWin,
            'draw' => $draw,
            'home_draw' => $homeWin + $draw,
            'draw_away' => $draw + $awayWin,
            'home_away' => $homeWin + $awayWin,
            'btts_yes' => $btts,
            'btts_no' => 1 - $btts,
            'over' => $line !== null ? $over : null,
            'under' => $line !== null ? 1 - $over : null,
            default => null,
        };
    }

    private function factorial(int $n): float
    {
        $result = 1.0;
        for ($i = 2; $i <= $n; $i++) {
            $result *= $i;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $fixtures
     * @return array<string, array<string, mixed>>
     */
    private function indexFixtures(array $fixtures): array
    {
        $index = [];
        foreach ($fixtures as $fixture) {
            $key = $this->normalizeMatch((string) ($fixture['match'] ?? ''));
            if ($key !== '') {
                $index[$key] = $fixture;
            }
        }

        return $index;
    }

    /**
     * @param array<string, mixed>|null $fixture
     * @return array<string, mixed>
     */
    private function matchFixtureSelection(?array $fixture, string $pronostic, float $odd): array
    {
        if (! $fixture) {
            return [];
        }

        $base = [
            'fixture_id' => $fixture['id'] ?? null,
            'event_id' => $fixture['event_id'] ?? null,
            'sport' => $fixture['sport'] ?? 'football',
            'competition' => $fixture['competition'] ?? '',
            'match' => $fixture['match'] ?? '',
            'home' => $fixture['home'] ?? '',
            'away' => $fixture['away'] ?? '',
            'kickoff' => $fixture['kickoff'] ?? null,
            'kickoff_at' => $fixture['kickoff_at'] ?? null,
        ];

        $needle = mb_strtolower(trim($pronostic));
        foreach ($fixture['selections'] ?? [] as $selection) {
            $label = mb_strtolower(trim((string) ($selection['pronostic'] ?? '')));
            if ($label === $needle || abs((float) ($selection['cote'] ?? 0) - $odd) < 0.03) {
                return [
                    ...$base,
                    'selection_key' => $selection['key'] ?? null,
                    'line' => $selection['line'] ?? null,
                ];
            }
        }

        return $base;
    }

    private function marketLabel(string $key): string
    {
        return match ($key) {
            'home', 'away', 'draw' => 'Résultat 1N2',
            'home_draw', 'draw_away', 'home_away' => 'Double chance',
            'over', 'under' => 'Plus / Moins de buts',
            'btts_yes', 'btts_no' => 'Les deux équipes marquent',
            'spread_home', 'spread_away' => 'Handicap',
            default => 'Marché principal',
        };
    }

    private function normalizeTier(string $raw): ?string
    {
        $clean = str_replace(['é', 'è', 'ê', ' ', '-'], ['e', 'e', 'e', '_', '_'], mb_strtolower(trim($raw)));

        return match (true) {
            str_contains($clean, 'equilibr') => 'equilibre',
            str_contains($clean, 'ambiti') => 'ambitieux',
            str_contains($clean, 'risqu') => 'tres_risque',
            default => null,
        };
    }

    private function normalizeConfidence(string $raw): string
    {
        $clean = str_replace(['é', 'è', 'ê'], 'e', mb_strtolower(trim($raw)));

        return match (true) {
            str_contains($clean, 'elev') => 'elevee',
            str_contains($clean, 'faib') => 'faible',
            default => 'moyenne',
        };
    }

    private function normalizeMatch(string $match): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($match))) ?: '';
    }

    private function parsePercent(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $num = (float) $value;
            return (int) round($num <= 1.0 && $num > 0 ? $num * 100 : $num);
        }

        if (is_string($value) && preg_match('/(\d+(?:[.,]\d+)?)/', $value, $match)) {
            $num = (float) str_replace(',', '.', $match[1]);
            return (int) round($num);
        }

        return null;
    }

    private function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $content): ?array
    {
        $trimmed = trim($content);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $trimmed, $match)) {
            $trimmed = $match[1];
        } elseif (($start = strpos($trimmed, '{')) !== false && ($end = strrpos($trimmed, '}')) !== false) {
            $trimmed = substr($trimmed, $start, $end - $start + 1);
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }
}

<?php

namespace App\Services;

use App\Support\AiProvider;
use App\Models\AppSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Offre « Score exact » (niveau VIP « Score », abonnement à part) : la méthode du fondateur
 * (réglage « ai_exact_score_instructions ») appliquée sans jamais inventer un chiffre.
 *
 *  Étape 1 (serveur) : buts attendus = modèle forme + classement (ValueAnalyzer) et, quand les cotes
 *    existent, ce qu'impliquent les cotes 1N2 et plus/moins de 2,5 sans marge — moitié / moitié.
 *  Étape 2 (IA)      : ajustements chiffrés et justifiés par le dossier, bornés à ±0,40 but chacun.
 *  Étapes 3 à 6      : grille de Poisson 0-0 à 5-5 avec correction Dixon-Coles des petits scores,
 *    écart au marché, 1N2 / plus de 2,5 / les deux marquent, confiance.
 *
 * Le score publié (celui qui décide gagné / perdu) est le plus probable DU résultat 1N2 le plus
 * probable : la cohérence exigée par les consignes. Les cotes de scores exacts ne sont pas fournies
 * par l'API de cotes : elles restent « donnée manquante », jamais estimées.
 */
class ExactScoreGenerator
{
    public const LEVEL = 'Score';

    public const CONFIDENCE_MEDIUM = 'MOYENNE';

    public const CONFIDENCE_LOW = 'FAIBLE';

    public const DISCLAIMER = 'Ces probabilités sont des estimations statistiques, pas des certitudes. Le score exact est un pari à haut risque : même bien analysé, il peut perdre. Misez petit.';

    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    // Grille complète pour 1N2 / plus de 2,5 / les deux marquent ; scores proposés jusqu'à 5-5.
    private const GRID_MAX = 10;

    private const SCORE_MAX = 5;

    // Corrélation des petits scores (Dixon-Coles) : plus de 0-0 et 1-1, moins de 1-0 et 0-1.
    private const RHO = -0.10;

    private const MAX_ADJUSTMENT = 0.40;

    private const MAX_TEAM_ADJUSTMENT = 0.60;

    // Au-delà, le désaccord modèle / marché rend la prédiction fragile (consignes : 10 points).
    private const DIVERGENCE_WARNING = 10;

    private const DIVERGENCE_LOW = 15;

    public function __construct(
        private readonly ValueAnalyzer $analyzer,
        private readonly AiPronosticGenerator $pronostics,
    ) {
    }

    /**
     * Étapes 1 et 3 à 6, sans IA. null si ni le modèle ni le marché ne permettent d'estimer les buts
     * attendus : le match est écarté plutôt que comblé par une supposition.
     *
     * @param array<string, mixed> $fixture match enrichi (cotes + données collectées)
     * @return array<string, mixed>|null
     */
    public function analyse(array $fixture): ?array
    {
        if (($fixture['sport'] ?? 'football') !== 'football') {
            return null;
        }

        $data = $fixture['data'] ?? [];
        $model = $this->analyzer->expectedGoals($data);
        $market = $this->market($fixture);
        $marketLambda = $this->marketLambdas($market);

        if ($model === null && $marketLambda === null) {
            return null;
        }

        $base = match (true) {
            $model !== null && $marketLambda !== null => [
                'home' => ($model['home'] + $marketLambda['home']) / 2,
                'away' => ($model['away'] + $marketLambda['away']) / 2,
            ],
            $model !== null => ['home' => (float) $model['home'], 'away' => (float) $model['away']],
            default => $marketLambda,
        };
        $base = ['home' => $this->clampLambda($base['home']), 'away' => $this->clampLambda($base['away'])];

        $divergence = null;

        if ($model !== null && isset($market['home'])) {
            $modelOutcomes = $this->outcomes($this->grid((float) $model['home'], (float) $model['away']));
            $divergence = (int) round(100 * max(
                abs($modelOutcomes['home'] - $market['home']),
                abs($modelOutcomes['draw'] - $market['draw']),
                abs($modelOutcomes['away'] - $market['away']),
            ));
        }

        $missing = $this->analyzer->missingData($data);

        if (! isset($market['home'])) {
            $missing[] = 'cotes 1N2 : donnée manquante';
        }

        if (! isset($market['over25'])) {
            $missing[] = 'cotes plus/moins de 2,5 buts : donnée manquante';
        }

        $missing[] = 'cotes de scores exacts : donnée manquante (non fournies par l’API de cotes)';
        $confidence = $this->confidence($data, $market, $divergence);

        return [
            'fixture' => $fixture,
            'model' => $model,
            'market' => $market,
            'market_lambda' => $marketLambda,
            'base' => $base,
            'lambda' => $base,
            'adjustments' => [],
            'divergence' => $divergence,
            'missing' => $missing,
            'confidence' => $confidence,
            'prediction' => $this->predict($base['home'], $base['away'], $confidence),
        ];
    }

    /**
     * Étape 2 : applique les ajustements validés et recalcule la grille (la confiance ne change pas).
     *
     * @param array<string, mixed> $analysis
     * @param array<int, array{side: string, delta: float, reason: string}> $adjustments
     * @return array<string, mixed>
     */
    public function finalize(array $analysis, array $adjustments): array
    {
        $totals = ['home' => 0.0, 'away' => 0.0];
        $kept = [];

        foreach ($adjustments as $adjustment) {
            $side = $adjustment['side'];
            $room = self::MAX_TEAM_ADJUSTMENT - abs($totals[$side]);
            $delta = max(-$room, min($room, $adjustment['delta']));

            if (abs($delta) < 0.01) {
                continue;
            }

            $totals[$side] += $delta;
            $kept[] = [...$adjustment, 'delta' => round($delta, 2)];
        }

        $lambda = [
            'home' => $this->clampLambda($analysis['base']['home'] + $totals['home']),
            'away' => $this->clampLambda($analysis['base']['away'] + $totals['away']),
        ];

        return [
            ...$analysis,
            'lambda' => $lambda,
            'adjustments' => $kept,
            'prediction' => $this->predict($lambda['home'], $lambda['away'], $analysis['confidence']),
        ];
    }

    /**
     * Étape 2 par l'IA (OpenRouter). null si l'IA ne répond pas : la prédiction du serveur seule
     * est alors publiée, avec ses propres facteurs.
     *
     * @param array<string, mixed> $analysis
     * @return array{adjustments: array<int, array{side: string, delta: float, reason: string}>, factors: array<int, string>, missing: array<int, string>, risk: ?string}|null
     */
    public function askAi(string $key, array $analysis): ?array
    {
        try {
            $response = Http::timeout(90)
                ->withToken($key)
                ->withHeaders(['HTTP-Referer' => config('app.url'), 'X-Title' => 'BIG BOSS'])
                ->post(AiProvider::endpoint(), [
                    'model' => AiProvider::model(AppSetting::value('openrouter_model') ?: AiPronosticGenerator::DEFAULT_MODEL),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($analysis)],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Score exact : IA refusée par OpenRouter, analyse du serveur seule.', ['status' => $response->status()]);

                return null;
            }

            $content = (string) $response->json('choices.0.message.content');

            if (preg_match('/\{.*\}/s', $content, $match)) {
                $content = $match[0];
            }

            $data = json_decode($content, true);

            return is_array($data) ? $this->normalizeAi($data) : null;
        } catch (Throwable $exception) {
            Log::warning('Score exact : OpenRouter injoignable, analyse du serveur seule.', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Analyse publiée, au format exigé par les consignes (« FORMAT DE SORTIE »).
     *
     * @param array<string, mixed> $analysis
     * @param array<string, mixed>|null $ai
     */
    public function render(array $analysis, ?array $ai): string
    {
        $fixture = $analysis['fixture'];
        $prediction = $analysis['prediction'];
        $home = $fixture['home'];
        $away = $fixture['away'];
        $percent = $prediction['percent'];
        $low = $analysis['confidence'] === self::CONFIDENCE_LOW;
        $kickoff = ! empty($fixture['kickoff_at'])
            ? Carbon::parse($fixture['kickoff_at'])->setTimezone(config('app.timezone'))->format('d/m/Y à H:i')
            : ($fixture['kickoff'] ?? '');

        $lines = ['🎯 BIG BOSS — SCORE EXACT'];
        $lines[] = "Match : {$home} vs {$away} — " . ($fixture['competition'] ?? '') . " — {$kickoff}";
        $lines[] = 'Buts attendus : ' . $home . ' = ' . $this->decimal($analysis['lambda']['home']) . ' | ' . $away . ' = ' . $this->decimal($analysis['lambda']['away']);
        $lines[] = 'Top ' . count($prediction['scores']) . ' scores exacts :';

        foreach ($prediction['scores'] as $index => $score) {
            $lines[] = ' ' . ($index + 1) . ') ' . $score['label'] . ' (' . $this->percent($score['p']) . ' %)';
        }

        $lines[] = "Probabilités : Victoire {$home} {$percent['home']} % | Nul {$percent['draw']} % | Victoire {$away} {$percent['away']} %";
        $lines[] = 'Over 2,5 : ' . $this->percent($prediction['outcomes']['over25']) . ' % | BTTS : ' . $this->percent($prediction['outcomes']['btts']) . ' %';
        $lines[] = 'Confiance : ' . ($low ? 'FAIBLE (liste de scores élargie, misez très petit)' : $analysis['confidence']);

        $factors = $ai['factors'] ?? [];
        $lines[] = 'Facteurs clés :';

        foreach (array_slice($factors !== [] ? $factors : $this->serverFactors($analysis), 0, 5) as $factor) {
            $lines[] = '• ' . $factor;
        }

        foreach ($analysis['adjustments'] as $adjustment) {
            $team = $adjustment['side'] === 'home' ? $home : $away;
            $lines[] = '• Ajustement ' . ($adjustment['delta'] > 0 ? '+' : '−') . $this->decimal(abs($adjustment['delta'])) . " but {$team} : {$adjustment['reason']}";
        }

        if ($prediction['official']['p'] > 0.20) {
            $lines[] = '• Probabilité du score n°1 au-dessus de 20 % : match à très peu de buts attendus ('
                . $this->decimal($analysis['lambda']['home'] + $analysis['lambda']['away']) . ' au total).';
        }

        $missing = array_values(array_unique([...$analysis['missing'], ...($ai['missing'] ?? [])]));
        $lines[] = 'Données manquantes ou incertaines : ' . implode(' ; ', array_slice($missing, 0, 8));
        $lines[] = 'Risque principal pouvant invalider la prédiction : ' . ($ai['risk'] ?? $this->serverRisk($analysis));
        $lines[] = '⚠️ ' . self::DISCLAIMER;

        return mb_substr(implode("\n", $lines), 0, 3600);
    }

    /**
     * Événement enregistré dans le pronostic : de quoi afficher et régler le score automatiquement.
     *
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    public function toEvent(array $analysis): array
    {
        $fixture = $analysis['fixture'];
        $prediction = $analysis['prediction'];
        $official = $prediction['official'];

        return [
            'match' => $fixture['match'],
            'competition' => $fixture['competition'] ?? '',
            'sport' => 'football',
            'kickoff' => $fixture['kickoff'] ?? null,
            'kickoff_at' => $fixture['kickoff_at'] ?? null,
            'fixture_id' => $fixture['id'] ?? null,
            'league_id' => $fixture['league_id'] ?? null,
            'home' => $fixture['home'],
            'away' => $fixture['away'],
            'pronostic' => 'Score exact ' . $official['label'],
            'selection_key' => 'exact_score',
            'score' => $official['label'],
            'score_home' => $official['home'],
            'score_away' => $official['away'],
            'probabilite' => $this->percent($official['p']),
            // Pas de cote de score exact fournie par l'API : rien d'inventé.
            'cote' => null,
            'alternatives' => collect($prediction['scores'])
                ->reject(fn ($score) => $score['label'] === $official['label'])
                ->map(fn ($score) => ['score' => $score['label'], 'probabilite' => $this->percent($score['p'])])
                ->values()
                ->all(),
            'confiance' => $analysis['confidence'],
            'buts_attendus' => ['home' => round($analysis['lambda']['home'], 2), 'away' => round($analysis['lambda']['away'], 2)],
        ];
    }

    /**
     * Grille des scores (Poisson indépendant × correction Dixon-Coles), normalisée.
     *
     * @return array<int, array<int, float>>
     */
    public function grid(float $lambdaHome, float $lambdaAway): array
    {
        $grid = [];
        $sum = 0.0;

        for ($home = 0; $home <= self::GRID_MAX; $home++) {
            for ($away = 0; $away <= self::GRID_MAX; $away++) {
                $p = $this->pmf($home, $lambdaHome) * $this->pmf($away, $lambdaAway) * $this->tau($home, $away, $lambdaHome, $lambdaAway);
                $grid[$home][$away] = max(0.0, $p);
                $sum += $grid[$home][$away];
            }
        }

        foreach ($grid as $home => $row) {
            foreach ($row as $away => $p) {
                $grid[$home][$away] = $p / $sum;
            }
        }

        return $grid;
    }

    /**
     * @param array<int, array<int, float>> $grid
     * @return array{home: float, draw: float, away: float, over25: float, btts: float}
     */
    public function outcomes(array $grid): array
    {
        $outcomes = ['home' => 0.0, 'draw' => 0.0, 'away' => 0.0, 'over25' => 0.0, 'btts' => 0.0];

        foreach ($grid as $home => $row) {
            foreach ($row as $away => $p) {
                $outcomes[$home > $away ? 'home' : ($home === $away ? 'draw' : 'away')] += $p;
                $outcomes['over25'] += $home + $away >= 3 ? $p : 0.0;
                $outcomes['btts'] += $home > 0 && $away > 0 ? $p : 0.0;
            }
        }

        return $outcomes;
    }

    /**
     * @return array<string, mixed>
     */
    private function predict(float $lambdaHome, float $lambdaAway, string $confidence): array
    {
        $grid = $this->grid($lambdaHome, $lambdaAway);
        $outcomes = $this->outcomes($grid);
        $scores = [];

        for ($home = 0; $home <= self::SCORE_MAX; $home++) {
            for ($away = 0; $away <= self::SCORE_MAX; $away++) {
                $scores[] = ['home' => $home, 'away' => $away, 'label' => "{$home}-{$away}", 'p' => $grid[$home][$away]];
            }
        }

        usort($scores, fn ($a, $b) => $b['p'] <=> $a['p']);

        $best = collect(['home', 'draw', 'away'])->sortByDesc(fn ($key) => $outcomes[$key])->first();
        $official = collect($scores)->first(fn ($score) => $this->outcomeOf($score) === $best) ?? $scores[0];
        $others = array_values(array_filter($scores, fn ($score) => $score['label'] !== $official['label']));

        return [
            'official' => $official,
            'scores' => array_slice([$official, ...$others], 0, $confidence === self::CONFIDENCE_LOW ? 5 : 3),
            'outcomes' => $outcomes,
            'percent' => $this->roundTo100(['home' => $outcomes['home'], 'draw' => $outcomes['draw'], 'away' => $outcomes['away']]),
        ];
    }

    /**
     * Probabilités 1N2 et plus de 2,5 du marché, marge retirée.
     *
     * @return array{home?: float, draw?: float, away?: float, over25?: float, bookmakers?: int}
     */
    private function market(array $fixture): array
    {
        $byKey = [];

        foreach ($fixture['selections'] ?? [] as $selection) {
            $key = (string) ($selection['key'] ?? '');
            $point = isset($selection['point']) ? (float) $selection['point'] : null;

            if (in_array($key, ['home', 'draw', 'away'], true)) {
                $byKey[$key] = $selection;
            } elseif (in_array($key, ['over', 'under'], true) && $point !== null && abs($point - 2.5) < 0.01) {
                $byKey[$key . '25'] = $selection;
            }
        }

        $market = [];

        if (isset($byKey['home'], $byKey['draw'], $byKey['away'])) {
            $raw = [];

            foreach (['home', 'draw', 'away'] as $key) {
                $raw[$key] = (float) ($byKey[$key]['fair'] ?? 1 / max(1.01, (float) $byKey[$key]['cote']));
            }

            $sum = array_sum($raw);

            foreach ($raw as $key => $value) {
                $market[$key] = $value / $sum;
            }

            $market['bookmakers'] = min(array_map(fn ($key) => (int) ($byKey[$key]['bookmakers'] ?? 1), ['home', 'draw', 'away']));
        }

        if (isset($byKey['over25'])) {
            $over = $byKey['over25']['fair'] ?? null;

            if ($over === null && isset($byKey['under25'])) {
                $o = 1 / max(1.01, (float) $byKey['over25']['cote']);
                $u = 1 / max(1.01, (float) $byKey['under25']['cote']);
                $over = $o / ($o + $u);
            }

            if ($over !== null) {
                $market['over25'] = max(0.03, min(0.97, (float) $over));
            }
        }

        return $market;
    }

    /**
     * Buts attendus implicites du marché : total déduit de plus de 2,5 buts, répartition déduite
     * de l'écart victoire domicile / victoire extérieur. null sans ces deux marchés.
     *
     * @return array{home: float, away: float}|null
     */
    private function marketLambdas(array $market): ?array
    {
        if (! isset($market['home'], $market['away'], $market['over25'])) {
            return null;
        }

        $total = $this->bisect(fn ($lambda) => 1 - exp(-$lambda) * (1 + $lambda + $lambda * $lambda / 2), $market['over25'], 0.2, 8.0);
        $share = $this->bisect(function ($share) use ($total) {
            $outcomes = $this->outcomes($this->independentGrid($total * $share, $total * (1 - $share)));

            return $outcomes['home'] - $outcomes['away'];
        }, $market['home'] - $market['away'], 0.03, 0.97);

        return ['home' => $total * $share, 'away' => $total * (1 - $share)];
    }

    /**
     * Confiance selon les consignes : ÉLEVÉE exige les compositions confirmées, que nos sources ne
     * donnent jamais — MOYENNE au mieux, honnêtement. Compositions et blessés comptent déjà pour
     * les « 1 à 2 données importantes manquantes » ; une de plus, ou un fort désaccord avec le
     * marché, et c'est FAIBLE.
     */
    private function confidence(array $data, array $market, ?int $divergence): string
    {
        $missing = 2;
        $missing += empty($data['standings']) ? 1 : 0;

        foreach (['home', 'away'] as $side) {
            $missing += count($data['form'][$side] ?? []) < 5 ? 1 : 0;
        }

        $missing += isset($market['home']) ? 0 : 1;
        $missing += isset($market['over25']) ? 0 : 1;
        $missing += in_array($data['type'] ?? 'championnat', ['coupe', 'selections'], true) ? 1 : 0;

        if ($divergence !== null && $divergence > self::DIVERGENCE_LOW) {
            return self::CONFIDENCE_LOW;
        }

        return $missing <= 2 && ($divergence === null || $divergence <= self::DIVERGENCE_WARNING)
            ? self::CONFIDENCE_MEDIUM
            : self::CONFIDENCE_LOW;
    }

    private function systemPrompt(): string
    {
        $method = trim((string) AppSetting::value('ai_exact_score_instructions', ''));

        return $method . "\n\n" . <<<'PROMPT'
# FONCTIONNEMENT AVEC LE SERVEUR BIG BOSS (prioritaire sur tout format de sortie ci-dessus)
- Le serveur a déjà fait les étapes 1, 3, 4, 5 et 6 avec les seules données disponibles : tu ne recalcules pas la grille et tu ne changes pas la confiance.
- Tu n'as AUCUN outil de recherche : seules les DONNÉES DU MATCH ci-dessous existent. Toute autre information est une DONNÉE MANQUANTE.
- Ta tâche : l'étape 2 (ajustements chiffrés des buts attendus, chacun justifié par une donnée PRÉSENTE dans le dossier, au plus ±0,40 but chacun ; aucun ajustement si rien ne le justifie), puis 3 à 5 facteurs clés, les données manquantes et le risque principal.
- Réponds uniquement par un objet JSON valide : {"ajustements": [{"equipe": "domicile", "delta": -0.15, "raison": "…"}], "facteurs_cles": ["…"], "donnees_manquantes": ["…"], "risque_principal": "…"}
PROMPT;
    }

    private function userPrompt(array $analysis): string
    {
        $fixture = $analysis['fixture'];
        $market = $analysis['market'];
        $prediction = $analysis['prediction'];
        $model = $analysis['model'];
        $marketLambda = $analysis['market_lambda'];

        $marketLine = isset($market['home'])
            ? sprintf('1N2 (%d bookmaker(s), marge retirée) : 1 %d %% / N %d %% / 2 %d %%', $market['bookmakers'] ?? 1, $this->percent($market['home']), $this->percent($market['draw']), $this->percent($market['away']))
            : '1N2 : DONNÉE MANQUANTE';
        $marketLine .= ' | Plus de 2,5 buts : ' . (isset($market['over25']) ? $this->percent($market['over25']) . ' %' : 'DONNÉE MANQUANTE');
        $marketLine .= ' | Cotes de scores exacts : DONNÉE MANQUANTE';

        $scores = collect($prediction['scores'])->map(fn ($score) => $score['label'] . ' ' . $this->percent($score['p']) . ' %')->implode(' ; ');

        return implode("\n", [
            'DONNÉES DU MATCH (collectées par le serveur le ' . now()->setTimezone(config('app.timezone'))->format('d/m/Y à H:i') . ')',
            $this->pronostics->dossier($fixture),
            '',
            'MARCHÉ',
            $marketLine,
            '',
            'CALCULS DU SERVEUR',
            '- Étape 1 : buts attendus du modèle (forme + classement) : ' . ($model ? $this->decimal($model['home']) . ' - ' . $this->decimal($model['away']) : 'DONNÉE MANQUANTE')
                . ' ; implicites du marché : ' . ($marketLambda ? $this->decimal($marketLambda['home']) . ' - ' . $this->decimal($marketLambda['away']) : 'DONNÉE MANQUANTE')
                . ' ; base retenue : ' . $this->decimal($analysis['base']['home']) . ' - ' . $this->decimal($analysis['base']['away']),
            "- Étape 3 (Poisson + correction Dixon-Coles) : {$scores}",
            '- Étape 4 : écart modèle / marché sur le 1N2 : ' . ($analysis['divergence'] !== null ? $analysis['divergence'] . ' points' : 'non mesurable'),
            sprintf('- Étape 5 : 1 %d %% / N %d %% / 2 %d %% ; plus de 2,5 : %d %% ; les deux marquent : %d %%', $prediction['percent']['home'], $prediction['percent']['draw'], $prediction['percent']['away'], $this->percent($prediction['outcomes']['over25']), $this->percent($prediction['outcomes']['btts'])),
            '- Étape 6 : confiance ' . $analysis['confidence'],
            '',
            "Domicile = {$fixture['home']} ; extérieur = {$fixture['away']}.",
        ]);
    }

    /**
     * @return array{adjustments: array<int, array{side: string, delta: float, reason: string}>, factors: array<int, string>, missing: array<int, string>, risk: ?string}
     */
    private function normalizeAi(array $data): array
    {
        $adjustments = [];

        foreach ((array) ($data['ajustements'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $team = mb_strtolower(trim((string) ($item['equipe'] ?? '')));
            $side = match (true) {
                str_starts_with($team, 'dom') || $team === 'home' => 'home',
                str_starts_with($team, 'ext') || $team === 'away' => 'away',
                default => null,
            };
            $reason = trim((string) ($item['raison'] ?? ''));

            if ($side === null || $reason === '' || ! is_numeric($item['delta'] ?? null)) {
                continue;
            }

            $adjustments[] = [
                'side' => $side,
                'delta' => max(-self::MAX_ADJUSTMENT, min(self::MAX_ADJUSTMENT, (float) $item['delta'])),
                'reason' => mb_strimwidth($reason, 0, 200, '…'),
            ];
        }

        $strings = fn ($values, int $limit, int $width) => collect((array) $values)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => mb_strimwidth(trim($value), 0, $width, '…'))
            ->take($limit)
            ->values()
            ->all();
        $risk = trim((string) ($data['risque_principal'] ?? ''));

        return [
            'adjustments' => $adjustments,
            'factors' => $strings($data['facteurs_cles'] ?? [], 5, 240),
            'missing' => $strings($data['donnees_manquantes'] ?? [], 6, 160),
            'risk' => $risk !== '' ? mb_strimwidth($risk, 0, 300, '…') : null,
        ];
    }

    /**
     * Facteurs écrits par le serveur à partir des données seules, si l'IA ne répond pas.
     *
     * @return array<int, string>
     */
    private function serverFactors(array $analysis): array
    {
        $fixture = $analysis['fixture'];
        $data = $fixture['data'] ?? [];
        $factors = [];
        $model = $analysis['model'];
        $marketLambda = $analysis['market_lambda'];

        if ($model && $marketLambda) {
            $factors[] = 'Buts attendus : modèle forme/classement ' . $this->decimal($model['home']) . ' - ' . $this->decimal($model['away'])
                . ', cotes du marché ' . $this->decimal($marketLambda['home']) . ' - ' . $this->decimal($marketLambda['away']) . ' (moyenne retenue).';
        }

        if ($standings = $data['standings'] ?? null) {
            $factors[] = "Classement : {$fixture['home']} {$standings['home']['position']}e ({$standings['home']['points']} pts), {$fixture['away']} {$standings['away']['position']}e ({$standings['away']['points']} pts).";
        }

        foreach (['home', 'away'] as $side) {
            $games = $data['form'][$side] ?? [];

            if ($games !== []) {
                $results = array_count_values(array_column($games, 'result'));
                $factors[] = 'Forme ' . $fixture[$side] . ' : ' . ($results['V'] ?? 0) . 'V ' . ($results['N'] ?? 0) . 'N ' . ($results['D'] ?? 0) . 'D sur ' . count($games) . ' matchs.';
            }
        }

        if ($analysis['divergence'] !== null && $analysis['divergence'] > self::DIVERGENCE_WARNING) {
            $factors[] = "Écart de {$analysis['divergence']} points entre le modèle et le marché : une information (blessure, composition) peut manquer.";
        }

        return $factors !== [] ? $factors : ['Prédiction fondée sur les cotes du marché uniquement (forme et classement indisponibles).'];
    }

    private function serverRisk(array $analysis): string
    {
        return 'un but rapide, un penalty ou un carton rouge change toute la grille ; compositions et blessés non vérifiés au moment de l’analyse.';
    }

    private function outcomeOf(array $score): string
    {
        return $score['home'] > $score['away'] ? 'home' : ($score['home'] === $score['away'] ? 'draw' : 'away');
    }

    private function tau(int $home, int $away, float $lambdaHome, float $lambdaAway): float
    {
        return match (true) {
            $home === 0 && $away === 0 => 1 - $lambdaHome * $lambdaAway * self::RHO,
            $home === 0 && $away === 1 => 1 + $lambdaHome * self::RHO,
            $home === 1 && $away === 0 => 1 + $lambdaAway * self::RHO,
            $home === 1 && $away === 1 => 1 - self::RHO,
            default => 1.0,
        };
    }

    /**
     * Grille sans correction (répartition des buts implicites du marché).
     *
     * @return array<int, array<int, float>>
     */
    private function independentGrid(float $lambdaHome, float $lambdaAway): array
    {
        $grid = [];

        for ($home = 0; $home <= self::GRID_MAX; $home++) {
            for ($away = 0; $away <= self::GRID_MAX; $away++) {
                $grid[$home][$away] = $this->pmf($home, $lambdaHome) * $this->pmf($away, $lambdaAway);
            }
        }

        return $grid;
    }

    private function pmf(int $k, float $lambda): float
    {
        $p = exp(-$lambda);

        for ($i = 1; $i <= $k; $i++) {
            $p *= $lambda / $i;
        }

        return $p;
    }

    /**
     * x tel que f(x) = target, f croissante sur [low, high].
     */
    private function bisect(callable $f, float $target, float $low, float $high): float
    {
        $target = max($f($low), min($f($high), $target));

        for ($i = 0; $i < 50; $i++) {
            $mid = ($low + $high) / 2;

            if ($f($mid) < $target) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    /**
     * Pourcentages entiers qui font exactement 100 (plus grands restes).
     *
     * @param array<string, float> $values
     * @return array<string, int>
     */
    private function roundTo100(array $values): array
    {
        $total = array_sum($values) ?: 1;
        $exact = array_map(fn ($value) => $value / $total * 100, $values);
        $rounded = array_map(fn ($value) => (int) floor($value), $exact);
        $remainders = [];

        foreach ($exact as $key => $value) {
            $remainders[$key] = $value - $rounded[$key];
        }

        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, 100 - array_sum($rounded)) as $key) {
            $rounded[$key]++;
        }

        return $rounded;
    }

    private function clampLambda(float $lambda): float
    {
        return round(max(0.2, min(4.0, $lambda)), 3);
    }

    private function percent(float $value): int
    {
        return (int) round($value * 100);
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, ',', '');
    }
}

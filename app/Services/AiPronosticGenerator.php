<?php

namespace App\Services;

use App\Exceptions\NoValueBetException;
use App\Models\AppSetting;
use App\Support\PronosticRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Compose les coupons à partir des données réellement collectées, groupe par groupe, selon les
 * consignes principales de Binho Flash (cote totale ≥ 2,00, 75 % / 80 % par sélection, marchés
 * autorisés, coupons différents entre gratuit et VIP).
 *
 * 1. ValueAnalyzer chiffre chaque sélection (probabilités, valeur, confiance) : « valeur »,
 *    « solide » (football confirmé par les données) ou « consensus » (basket, handball : cotes seules).
 * 2. Seules les sélections qui respectent les règles du groupe sont proposées à l'IA (OpenRouter),
 *    qui écarte les pièges du dossier, compose le coupon et rédige justification et risques.
 * 3. Le serveur revérifie tout et, si l'IA ne propose rien de valable, compose lui-même la
 *    combinaison la plus probable qui respecte les règles. Sinon : rien n'est publié.
 */
class AiPronosticGenerator
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public const DEFAULT_MODEL = 'openai/gpt-4o-mini';

    // Plafond absolu de sélections dans un coupon (Platinum) ; chaque groupe a le sien.
    public const MAX_SELECTIONS = 4;

    // Tous les groupes sont générés automatiquement (heures dans Admin > Paramètres).
    public const AI_LEVELS = PronosticRules::LEVELS;

    // Les VIP sont composés d'abord : ils prennent les sélections les plus sûres, que les gratuits
    // ne reprennent jamais.
    private const BUILD_ORDER = ['Platinum', 'Gold', 'Silver', 'Bronze', 'Starter'];

    // À partir de ce palier, la réserve de secours (tous les paris cotés) remplace les candidats normaux.
    private const FALLBACK_TIER = 3;

    // Victoire simple : exceptionnelle, réservée aux favoris très nets (football).
    private const STRAIGHT_WINS = ['home', 'away'];

    private const CLEAR_FAVOURITE_PROBABILITY = 0.80;

    private const CLEAR_FAVOURITE_MARKET = 0.75;

    // Taille du réservoir exploré par le serveur quand il compose lui-même un coupon.
    private const BUILDER_POOL = 16;

    private const CONFIDENCE_RANK = ['Faible' => 0, 'Moyenne' => 1, 'Élevée' => 2];

    public const DISCLAIMER = 'Les pronostics reposent sur des analyses statistiques et comportent toujours un risque de perte. Aucune sélection n\'est garantie. Pariez uniquement des sommes que vous pouvez vous permettre de perdre.';

    private const SPORT_LABELS = ['football' => 'Football', 'basketball' => 'Basketball', 'handball' => 'Handball', 'tennis' => 'Tennis'];

    public function __construct(private readonly ValueAnalyzer $analyzer)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $fixtures matchs enrichis (cotes + données collectées)
     * @param array<int, string> $levels
     * @param array{exclude?: array<int, string>, avoid?: array<int, string>, slot?: string} $options
     *        exclude : matchs interdits (déjà dans un coupon de l'autre catégorie aujourd'hui) ;
     *        avoid : matchs à éviter si possible (déjà dans un autre coupon VIP) ; slot : « 15:00 ».
     * @return array<int, array{niveau: string, evenements: array<int, array<string, mixed>>, cote_totale: float, probabilite: int, analysis: string}>
     */
    public function generate(array $fixtures, array $levels, array $options = []): array
    {
        $key = AppSetting::value('openrouter_api_key');

        if (! $key) {
            throw new RuntimeException('Clé OpenRouter manquante (Admin > Paramètres > Intelligence artificielle).');
        }

        $levels = array_values(array_intersect(self::BUILD_ORDER, $levels)) ?: ['Bronze'];
        $candidates = $this->analyzer->candidates($fixtures);
        $fallback = $this->analyzer->fallbackCandidates($fixtures);

        if ($candidates === [] && $fallback === []) {
            throw new NoValueBetException($this->noBetMessage($fixtures));
        }

        $drafts = [];
        $reasons = [];
        $usedByVip = [];
        $usedByFree = [];

        foreach ($levels as $level) {
            $rules = PronosticRules::for($level);
            $forbidden = [...($options['exclude'] ?? []), ...($rules['free'] ? $usedByVip : $usedByFree)];
            $avoid = [...($options['avoid'] ?? []), ...($rules['free'] ? $usedByFree : $usedByVip)];
            $draft = null;
            $vetoedMatches = [];

            // Un coupon doit sortir à chaque créneau : on essaie les règles du groupe, puis des
            // paliers de secours de plus en plus larges. Le coupon dit toujours honnêtement à quel
            // palier il a été obtenu, avec sa vraie probabilité.
            foreach ($this->tiers($rules) as $tier => $tierRules) {
                $source = $tier >= self::FALLBACK_TIER ? $fallback : $candidates;
                // Un match écarté par l'IA (derby, cote en baisse…) ne revient jamais par le secours.
                $pool = $this->eligible($source, $tierRules, [...$forbidden, ...$vetoedMatches]);

                if ($pool === []) {
                    continue;
                }

                // Paliers de secours : l'IA n'écarterait que des paris « faibles » ; la combinaison
                // la plus probable est construite directement, avec les chiffres du serveur.
                $answer = $tier >= self::FALLBACK_TIER ? null : $this->askAi($key, $fixtures, $pool, $tierRules);
                array_push($vetoedMatches, ...$this->vetoedMatches($answer, $pool));
                $draft = $this->compose($answer, $pool, $tierRules, $avoid, $options['slot'] ?? null);

                if ($draft !== null) {
                    $draft = $this->withTierNote($draft, $tier, $rules, $tierRules);

                    break;
                }
            }

            if ($draft === null) {
                $reasons[] = "{$level} : aucun pari coté disponible, même en secours";

                continue;
            }

            $drafts[$level] = $draft;
            $matches = array_column($draft['evenements'], 'match');
            $rules['free'] ? array_push($usedByFree, ...$matches) : array_push($usedByVip, ...$matches);
        }

        if ($drafts === []) {
            throw new NoValueBetException('Aucun coupon publié : ' . implode(' ; ', $reasons) . '.');
        }

        return array_values(array_filter(array_map(fn ($level) => $drafts[$level] ?? null, PronosticRules::LEVELS)));
    }

    /**
     * Paliers essayés dans l'ordre jusqu'à obtenir un coupon :
     * 0 règles du groupe ; 1 seuil −5 points ; 2 seuil −10 points et cote totale un peu plus basse ;
     * 3 secours (tous les paris cotés, 58 % minimum) ; 4 dernier recours (le pari le plus probable).
     *
     * @return array<int, array<string, mixed>>
     */
    private function tiers(array $rules): array
    {
        $floor = $rules['free'] ? 0.62 : 0.65;

        return [
            0 => $rules,
            1 => [...$rules, 'min_probability' => max($floor, $rules['min_probability'] - 0.05)],
            2 => [...$rules, 'min_probability' => max($floor, $rules['min_probability'] - 0.10), 'min_odds' => max(1.30, $rules['min_odds'] - 0.20), 'relaxed_wins' => true],
            3 => [...$rules, 'min_probability' => 0.58, 'min_odds' => 1.25, 'relaxed_wins' => true],
            4 => [...$rules, 'min_probability' => 0.50, 'min_odds' => PronosticRules::MIN_SELECTION_ODDS, 'max_selections' => 1, 'relaxed_wins' => true],
        ];
    }

    /**
     * Matchs des sélections que l'IA a écartées (« ecartees »).
     *
     * @param array<int, array<string, mixed>> $pool
     * @return array<int, string>
     */
    private function vetoedMatches(?array $answer, array $pool): array
    {
        $ids = collect((array) ($answer['ecartees'] ?? []))
            ->map(fn ($item) => strtoupper(trim((string) (is_array($item) ? ($item['id'] ?? '') : $item))))
            ->filter()
            ->all();

        return collect($pool)
            ->filter(fn ($candidate) => in_array($candidate['id'], $ids, true))
            ->pluck('match')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Mention en tête de l'analyse quand le coupon vient d'un palier de secours.
     */
    private function withTierNote(array $draft, int $tier, array $rules, array $tierRules): array
    {
        $draft['palier'] = $tier;

        if ($tier === 0) {
            return $draft;
        }

        $note = $tier >= self::FALLBACK_TIER
            ? sprintf('⚠️ Coupon de secours : peu de matchs fiables aujourd’hui. Probabilité réelle du coupon : %d %%. Misez petit.', $draft['probabilite'])
            : sprintf('ℹ️ Fiabilité ajustée : aucun pari à %d %% aujourd’hui, seuil abaissé à %d %%.', (int) round($rules['min_probability'] * 100), (int) round($tierRules['min_probability'] * 100));

        $draft['analysis'] = mb_substr($note . "\n" . $draft['analysis'], 0, 3600);

        return $draft;
    }

    /**
     * Sélections qui respectent les règles du groupe : probabilité minimale, cote ≥ 1,15, pas de
     * nul sec, victoire simple seulement pour un favori très net, match pas déjà pris ailleurs.
     *
     * @param array<int, array<string, mixed>> $candidates
     * @param array<int, string> $forbidden
     * @return array<int, array<string, mixed>>
     */
    private function eligible(array $candidates, array $rules, array $forbidden): array
    {
        return array_values(array_filter($candidates, function ($candidate) use ($rules, $forbidden) {
            $football = ($candidate['sport'] ?? 'football') === 'football';

            if (in_array($candidate['match'], $forbidden, true)
                || (float) $candidate['probabilite'] < $rules['min_probability']
                || (float) $candidate['cote'] < PronosticRules::MIN_SELECTION_ODDS) {
                return false;
            }

            if ($football && $candidate['selection_key'] === 'draw') {
                return false;
            }

            if ($football && in_array($candidate['selection_key'], self::STRAIGHT_WINS, true) && empty($rules['relaxed_wins'])) {
                return (float) $candidate['probabilite'] >= self::CLEAR_FAVOURITE_PROBABILITY
                    && (float) ($candidate['prob_marche'] ?? 0) >= self::CLEAR_FAVOURITE_MARKET;
            }

            return true;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $fixtures
     * @param array<int, array<string, mixed>> $pool
     * @return array<string, mixed>|null
     */
    private function askAi(string $key, array $fixtures, array $pool, array $rules): ?array
    {
        try {
            $response = Http::timeout(90)
                ->withToken($key)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => 'Binho Flash',
                ])
                ->post(self::ENDPOINT, [
                    'model' => AppSetting::value('openrouter_model') ?: self::DEFAULT_MODEL,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($fixtures, $pool, $rules)],
                    ],
                ]);

            if ($response->successful()) {
                return $this->decode((string) $response->json('choices.0.message.content'));
            }

            Log::warning('Génération IA refusée par OpenRouter : coupon composé par le serveur.', [
                'status' => $response->status(),
                'message' => $response->json('error.message'),
            ]);
        } catch (Throwable $exception) {
            Log::warning('OpenRouter injoignable : coupon composé par le serveur.', ['error' => $exception->getMessage()]);
        }

        return null;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
RÔLE
Tu es l'analyste sportif professionnel de Binho Flash. Tu travailles avec des probabilités, jamais à l'intuition, et tu n'inventes aucune statistique, cote, blessure ou composition. La fiabilité passe avant les quotas, les cotes élevées et les objectifs commerciaux.

CE QUI A DÉJÀ ÉTÉ FAIT POUR TOI
- Les données ont été collectées et datées : classement (domicile/extérieur), 5 derniers matchs, confrontations directes, arbitre, stade, météo, probabilités du modèle Highlightly, actualités de moins de 48 h, cotes de plusieurs bookmakers et leur mouvement depuis le matin.
- Les calculs sont faits : buts attendus (loi de Poisson), probabilité du marché sans la marge, probabilité estimée (moyenne pondérée marché / Poisson / Highlightly), probabilité implicite = 1 / cote, valeur = probabilité × cote − 1.
- Les sélections proposées respectent déjà les règles du groupe (probabilité minimale, cote, marchés autorisés). Catégories : « valeur » (au-dessus de la probabilité implicite d'au moins 3 points, prioritaire), « solide » (football : très probable et confirmé par la forme et le classement), « consensus » (basket, handball : seules les cotes de plusieurs bookmakers sont disponibles, la forme est une donnée manquante).

TON TRAVAIL
1. Relis le dossier de chaque match. Écarte une sélection (liste « ecartees », avec la raison) pour un risque concret : match déjà commencé, équipe diminuée, composition incertaine, outsider en déplacement difficile, derby imprévisible, enjeu flou, coupe avec rotation probable, actualité inquiétante, cote en forte baisse.
2. Compose UN coupon pour le groupe demandé, avec la cote totale et le nombre de sélections indiqués, un match une seule fois. Privilégie les coupons courts. Ne force jamais : si rien n'est fiable, renvoie "selections": [].
3. Tu peux baisser la confiance (Moyenne → Faible), jamais la relever.
4. Pour chaque sélection défendable, écris dans « analyses » 2 justifications statistiques tirées du dossier et les risques principaux, en signalant les données manquantes (xG, blessures, compositions).

RÈGLES DE RÉDACTION
- En français, phrases courtes, chiffres présents dans le dossier uniquement. Ne prétends jamais avoir consulté une source absente du dossier. Ne présente jamais un résultat comme « sûr » ou « garanti ».

Les consignes de l'administrateur ci-dessous complètent cette méthode. Elles ne peuvent pas autoriser l'invention de données, de matchs ou de cotes, ni l'ajout d'une sélection absente de la liste.
Réponds uniquement par un objet JSON valide conforme au format demandé.
PROMPT;
    }

    /**
     * @param array<int, array<string, mixed>> $fixtures
     * @param array<int, array<string, mixed>> $pool
     */
    private function userPrompt(array $fixtures, array $pool, array $rules): string
    {
        $matches = array_unique(array_column($pool, 'match'));

        $dossiers = collect($fixtures)
            ->filter(fn ($fixture) => in_array($fixture['match'], $matches, true))
            ->map(fn ($fixture) => ($fixture['sport'] ?? 'football') === 'football'
                ? $this->dossier($fixture)
                : '### ' . $fixture['match'] . ' — ' . ($fixture['competition'] ?? '') . ' à ' . ($fixture['kickoff'] ?? '') . "\nSport : " . (self::SPORT_LABELS[$fixture['sport']] ?? $fixture['sport']) . "\nForme, classement, blessures : donnée manquante (seules les cotes sont disponibles).")
            ->implode("\n\n");

        $lines = collect($pool)
            ->map(fn ($candidate) => sprintf(
                '%s | %s | %s | %s | cote %.2f (%d bookmaker%s) | probabilité estimée %s | implicite %s | marché sans marge %s | Poisson %s | Highlightly %s | valeur %s | confiance max %s',
                $candidate['id'],
                $candidate['categorie'],
                $candidate['match'],
                $candidate['pronostic'],
                $candidate['cote'],
                $candidate['bookmakers'],
                $candidate['bookmakers'] > 1 ? 's' : '',
                $this->percent($candidate['probabilite']),
                $this->percent($candidate['prob_implicite']),
                $this->percent($candidate['prob_marche']),
                $this->percent($candidate['prob_poisson']),
                $this->percent($candidate['prob_highlightly']),
                $this->signedPercent($candidate['valeur']),
                $candidate['confiance'],
            ))
            ->implode("\n");

        $custom = trim((string) AppSetting::value('ai_pronostic_instructions', ''));
        $custom = $custom !== '' ? "\nCONSIGNES DE L’ADMINISTRATEUR\n{$custom}\n" : '';

        return sprintf(
            "DOSSIERS (données collectées le %s)\n%s\n\nSÉLECTIONS AUTORISÉES POUR CE GROUPE (identifiants à utiliser)\n%s\n\nGROUPE : %s%s — 1 à %d sélections, cote totale de %s à %s, probabilité estimée d’au moins %d %% par sélection.\n%s\nFORMAT ATTENDU\n%s",
            now()->format('d/m/Y à H:i'),
            $dossiers,
            $lines,
            $rules['level'],
            $rules['free'] ? ' (gratuit)' : ' (VIP payant)',
            $rules['max_selections'],
            number_format($rules['min_odds'], 2, ',', ''),
            number_format($rules['max_odds'], 2, ',', ''),
            (int) round($rules['min_probability'] * 100),
            $custom,
            '{"coupon": {"selections": [{"id": "C1", "confiance": "Moyenne"}], "resume": "…"}, "analyses": {"C1": {"raisons": ["…", "…"], "risques": "…"}}, "ecartees": [{"id": "C4", "raison": "…"}]}',
        );
    }

    private function dossier(array $fixture): string
    {
        $data = $fixture['data'] ?? [];
        $analysis = $this->analyzer->analyseFixture($fixture);
        $lines = ['### ' . $fixture['match'] . ' — ' . ($fixture['competition'] ?? '') . (! empty($fixture['kickoff']) ? ' à ' . $fixture['kickoff'] : '')];

        $type = ['championnat' => 'championnat', 'coupe' => 'coupe / compétition européenne', 'selections' => 'match de sélections nationales'][$data['type'] ?? 'championnat'] ?? 'championnat';
        $lines[] = "Type : {$type}";

        $details = $data['details'] ?? null;
        $lines[] = 'Stade : ' . ($details['venue'] ?? 'donnée manquante')
            . ' | Arbitre : ' . ($details['referee'] ?? 'donnée manquante')
            . ' | Météo : ' . ($details['forecast'] ?? 'donnée manquante');

        $standings = $data['standings'] ?? null;
        if ($standings) {
            $lines[] = sprintf(
                'Classement (%d équipes, leader %d pts%s) :',
                $standings['teams'],
                $standings['leader_points'],
                $standings['relegation_points'] !== null ? ', 1re place relégable ' . $standings['relegation_points'] . ' pts' : '',
            );
            $lines[] = '  ' . $fixture['home'] . ' : ' . $this->standingLine($standings['home'], 'home');
            $lines[] = '  ' . $fixture['away'] . ' : ' . $this->standingLine($standings['away'], 'away');
        } else {
            $lines[] = 'Classement : donnée manquante';
        }

        foreach (['home' => $fixture['home'], 'away' => $fixture['away']] as $side => $team) {
            $games = $data['form'][$side] ?? null;
            $lines[] = "Forme {$team} (" . ($games ? count($games) . ' derniers matchs' : 'donnée manquante') . ')'
                . ($games ? ' : ' . collect($games)->map(fn ($game) => "{$game['result']} {$game['gf']}-{$game['ga']} {$game['venue']} vs {$game['opponent']} ({$game['date']})")->implode(' ; ') : '');
        }

        $h2h = $data['h2h'] ?? null;
        $lines[] = 'H2H (poids faible) : ' . ($h2h ? collect($h2h)->map(fn ($game) => "{$game['match']} ({$game['date']})")->implode(' ; ') : 'donnée manquante');

        $predictions = $details['predictions'] ?? null;
        $lines[] = 'Modèle Highlightly : ' . ($predictions
            ? sprintf('1 %s / N %s / 2 %s', $this->percent($predictions['home']), $this->percent($predictions['draw']), $this->percent($predictions['away']))
            : 'donnée manquante');

        $expected = $analysis['expected'];
        if ($expected) {
            $table = $this->analyzer->poissonTable($expected['home'], $expected['away']);
            $lines[] = sprintf(
                'Buts attendus (Poisson, fiabilité %s, %d matchs) : %.2f - %.2f → 1 %s / N %s / 2 %s, plus de 2,5 buts %s, les deux marquent %s',
                $expected['fiabilite'],
                $expected['matchs'],
                $expected['home'],
                $expected['away'],
                $this->percent($table['home']),
                $this->percent($table['draw']),
                $this->percent($table['away']),
                $this->percent($table['over25']),
                $this->percent($table['btts']),
            );
        } else {
            $lines[] = 'Buts attendus : donnée manquante (pas assez de matchs)';
        }

        foreach ($details['news'] ?? [] as $news) {
            $lines[] = "Actualité ({$news['date']}) : {$news['title']}";
        }

        $lines[] = 'Données manquantes : ' . implode(' ; ', $analysis['missing']);

        return implode("\n", $lines);
    }

    private function standingLine(array $row, string $venue): string
    {
        $split = $row[$venue];
        $label = $venue === 'home' ? 'à domicile' : 'à l’extérieur';

        return sprintf(
            '%de, %d pts, %d matchs (%dV %dN %dD, %d-%d) ; %s : %d matchs (%dV %dN %dD, %d-%d)',
            $row['position'],
            $row['points'],
            $row['total']['games'],
            $row['total']['wins'],
            $row['total']['draws'],
            $row['total']['loses'],
            $row['total']['scored'],
            $row['total']['received'],
            $label,
            $split['games'],
            $split['wins'],
            $split['draws'],
            $split['loses'],
            $split['scored'],
            $split['received'],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $content): ?array
    {
        if (preg_match('/\{.*\}/s', $content, $match)) {
            $content = $match[0];
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            Log::warning('Réponse de l’IA illisible : coupon composé par le serveur.');

            return null;
        }

        // Ancien format (plusieurs coupons) : le premier sert.
        if (! isset($data['coupon']) && isset($data['coupons'][0])) {
            $data['coupon'] = $data['coupons'][0];
        }

        return $data;
    }

    /**
     * Coupon de l'IA revérifié, sinon la combinaison la plus probable qui respecte les règles.
     *
     * @param array<string, mixed>|null $answer
     * @param array<int, array<string, mixed>> $pool
     * @param array<int, string> $avoid
     * @return array<string, mixed>|null
     */
    private function compose(?array $answer, array $pool, array $rules, array $avoid, ?string $slot): ?array
    {
        $answer ??= [];
        $byId = collect($pool)->keyBy('id')->all();
        $analyses = is_array($answer['analyses'] ?? null) ? $answer['analyses'] : [];
        $vetoed = collect((array) ($answer['ecartees'] ?? []))
            ->map(fn ($item) => strtoupper(trim((string) (is_array($item) ? ($item['id'] ?? '') : $item))))
            ->filter()
            ->all();

        $events = [];

        foreach ((array) ($answer['coupon']['selections'] ?? []) as $choice) {
            $id = strtoupper(trim((string) ($choice['id'] ?? '')));
            $candidate = $byId[$id] ?? null;

            if (! $candidate || in_array($id, $vetoed, true) || count($events) >= $rules['max_selections'] || ! $this->fits($candidate, $events)) {
                continue;
            }

            // L'IA peut baisser la confiance, jamais la relever.
            $confidence = $this->lowerConfidence($candidate['confiance'], (string) ($choice['confiance'] ?? $candidate['confiance']));

            if ($confidence === 'Faible') {
                $vetoed[] = $id;

                continue;
            }

            $events[] = $this->event($candidate, $confidence, $choice + ($analyses[$id] ?? []));
        }

        $total = $this->total($events);

        if ($events === [] || $total < $rules['min_odds'] || $total > $rules['max_odds']) {
            $available = array_values(array_filter($pool, fn ($candidate) => ! in_array($candidate['id'], $vetoed, true)));
            $combo = $this->buildCombo($rules, $available, $avoid);

            if ($combo === null) {
                return null;
            }

            $events = array_map(fn ($candidate) => $this->event($candidate, $candidate['confiance'], $analyses[$candidate['id']] ?? []), $combo);
        }

        return $this->draft($rules['level'], $events, trim((string) ($answer['coupon']['resume'] ?? '')), $slot);
    }

    /**
     * Un match une seule fois par coupon, une victoire sèche au plus (football).
     *
     * @param array<int, array<string, mixed>> $events
     */
    private function fits(array $candidate, array $events): bool
    {
        if (in_array($candidate['match'], array_column($events, 'match'), true)) {
            return false;
        }

        if (($candidate['sport'] ?? 'football') === 'football' && in_array($candidate['selection_key'], self::STRAIGHT_WINS, true)) {
            return count(array_filter($events, fn ($event) => ($event['sport'] ?? 'football') === 'football'
                && in_array($event['selection_key'], self::STRAIGHT_WINS, true))) === 0;
        }

        return true;
    }

    /**
     * Combinaison avec la plus forte chance de passer (produit des probabilités) parmi celles
     * dont la cote totale respecte le groupe ; les matchs déjà proposés ailleurs passent après.
     *
     * @param array<int, array<string, mixed>> $pool
     * @param array<int, string> $avoid
     * @return array<int, array<string, mixed>>|null
     */
    private function buildCombo(array $rules, array $pool, array $avoid): ?array
    {
        $pool = array_slice($pool, 0, self::BUILDER_POOL);
        $best = null;
        $bestScore = -INF;

        $search = function (int $start, array $combo, float $odds, float $probability) use (&$search, &$best, &$bestScore, $pool, $rules, $avoid) {
            if ($combo !== [] && $odds >= $rules['min_odds'] && $odds <= $rules['max_odds']) {
                $reused = count(array_filter($combo, fn ($candidate) => in_array($candidate['match'], $avoid, true)));
                $valueBets = count(array_filter($combo, fn ($candidate) => $candidate['categorie'] === 'valeur'));
                $score = $probability + 0.02 * $valueBets - 0.05 * $reused;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $combo;
                }
            }

            if (count($combo) >= $rules['max_selections'] || $odds > $rules['max_odds']) {
                return;
            }

            for ($index = $start; $index < count($pool); $index++) {
                $candidate = $pool[$index];

                if (! $this->fits($candidate, $combo)) {
                    continue;
                }

                $search($index + 1, [...$combo, $candidate], $odds * $candidate['cote'], $probability * $candidate['probabilite']);
            }
        };

        $search(0, [], 1.0, 1.0);

        return $best;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $notes raisons / risques écrits par l'IA
     * @return array<string, mixed>
     */
    private function event(array $candidate, string $confidence, array $notes): array
    {
        $reasons = collect((array) ($notes['raisons'] ?? []))
            ->map(fn ($reason) => trim((string) $reason))
            ->filter()
            ->take(2)
            ->values()
            ->all();

        return [
            'match' => $candidate['match'],
            'competition' => $candidate['competition'] ?? '',
            'sport' => $candidate['sport'] ?? 'football',
            'kickoff' => $candidate['kickoff'] ?? null,
            'kickoff_at' => $candidate['kickoff_at'] ?? null,
            'pronostic' => $candidate['pronostic'],
            'cote' => $candidate['cote'],
            'fixture_id' => $candidate['fixture_id'],
            'event_id' => $candidate['event_id'] ?? null,
            'odds_key' => $candidate['odds_key'] ?? null,
            'league_id' => $candidate['league_id'] ?? null,
            'home' => $candidate['home'],
            'away' => $candidate['away'],
            'selection_key' => $candidate['selection_key'],
            'line' => $candidate['line'],
            'categorie' => $candidate['categorie'],
            'bookmakers' => $candidate['bookmakers'],
            'probabilite' => (int) round($candidate['probabilite'] * 100),
            'prob_implicite' => (int) round($candidate['prob_implicite'] * 100),
            'valeur' => round($candidate['valeur'] * 100, 1),
            'confiance' => $confidence,
            'raisons' => $reasons !== [] ? $reasons : $this->numericReasons($candidate),
            'risques' => mb_substr(trim((string) ($notes['risques'] ?? '')) ?: $this->numericRisks($candidate), 0, 300),
            '_p' => $candidate['probabilite'],
        ];
    }

    /**
     * Justifications tirées des seuls chiffres calculés, quand l'IA n'en a pas écrit.
     *
     * @return array<int, string>
     */
    private function numericReasons(array $candidate): array
    {
        if ($candidate['categorie'] === 'consensus') {
            return ['Consensus de ' . $candidate['bookmakers'] . ' bookmakers : probabilité sans marge ' . $this->percent($candidate['prob_marche']) . '.'];
        }

        $models = collect(['Poisson' => $candidate['prob_poisson'], 'Highlightly' => $candidate['prob_highlightly']])
            ->filter(fn ($probability) => $probability !== null)
            ->map(fn ($probability, $name) => $name . ' ' . $this->percent($probability))
            ->implode(', ');

        return array_values(array_filter([
            'Marché sans marge ' . $this->percent($candidate['prob_marche']) . ($models !== '' ? ', ' . $models : '') . ' : les sources concordent.',
            $candidate['categorie'] === 'valeur'
                ? 'Valeur ' . $this->signedPercent($candidate['valeur']) . ' : la cote paie plus que le risque estimé.'
                : 'Forme et classement confirment le favori du marché.',
        ]));
    }

    private function numericRisks(array $candidate): string
    {
        $risks = collect($candidate['risques'] ?? [])
            ->reject(fn ($risk) => str_starts_with($risk, 'xG/') || str_starts_with($risk, 'composition'))
            ->take(2)
            ->implode(' ; ');

        return $risks !== '' ? ucfirst($risks) . '.' : 'Compositions et absences inconnues au moment de l’analyse.';
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function total(array $events): float
    {
        return round(array_reduce($events, fn ($carry, $event) => $carry * $event['cote'], 1.0), 2);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function draft(string $level, array $events, string $summary, ?string $slot): array
    {
        $total = $this->total($events);
        $probability = array_reduce($events, fn ($carry, $event) => $carry * $event['_p'], 1.0);

        $events = array_map(function ($event) {
            unset($event['_p']);

            return $event;
        }, $events);

        return [
            'niveau' => $level,
            'evenements' => $events,
            'cote_totale' => $total,
            'probabilite' => max(1, min(99, (int) round($probability * 100))),
            'analysis' => $this->analysisText($level, $events, $total, $probability, $summary, $slot),
        ];
    }

    private function lowerConfidence(string $computed, string $proposed): string
    {
        $proposed = self::CONFIDENCE_RANK[$proposed] ?? null;

        if ($proposed === null) {
            return $computed;
        }

        return self::CONFIDENCE_RANK[$computed] <= $proposed ? $computed : array_search($proposed, self::CONFIDENCE_RANK, true);
    }

    /**
     * Format obligatoire des consignes : « BINHO FLASH — [GROUPE] », chaque sélection avec cote,
     * probabilité, justification et risques, puis le bilan et l'avertissement.
     *
     * @param array<int, array<string, mixed>> $events
     */
    private function analysisText(string $level, array $events, float $total, float $probability, string $summary, ?string $slot): string
    {
        $group = PronosticRules::isFree($level) ? strtoupper($level) : 'VIP ' . strtoupper($level);
        $now = now()->setTimezone(config('app.timezone'));
        $sports = collect($events)->pluck('sport')->unique()->map(fn ($sport) => self::SPORT_LABELS[$sport] ?? ucfirst((string) $sport))->implode(', ');
        $competitions = collect($events)->pluck('competition')->filter()->unique()->implode(', ');

        $lines = [
            "BINHO FLASH — {$group}",
            '📅 ' . $now->locale('fr')->isoFormat('dddd D MMMM YYYY') . ' · coupon de ' . ($slot ?: $now->format('H:i')) . ' · ' . $sports . ($competitions !== '' ? ' · ' . $competitions : ''),
        ];

        if ($summary !== '') {
            $lines[] = mb_substr($summary, 0, 220);
        }

        foreach ($events as $index => $event) {
            $lines[] = sprintf(
                "%d) %s%s — %s @ %s · probabilité estimée %d %%%s",
                $index + 1,
                $event['match'],
                ! empty($event['kickoff']) ? ' (' . $event['kickoff'] . ')' : '',
                $event['pronostic'],
                number_format($event['cote'], 2, ',', ''),
                $event['probabilite'],
                $event['categorie'] === 'valeur' ? sprintf(' · valeur +%s %%', number_format($event['valeur'], 1, ',', '')) : '',
            );
            $lines[] = '   Justification : ' . implode(' ', $event['raisons']);
            $lines[] = '   Risques : ' . $event['risques'];
        }

        $confidence = match (true) {
            collect($events)->every(fn ($event) => $event['confiance'] === 'Élevée') => 'Élevée',
            collect($events)->contains(fn ($event) => $event['confiance'] === 'Faible') => 'Faible',
            default => 'Moyenne',
        };
        $football = collect($events)->contains(fn ($event) => ($event['sport'] ?? 'football') === 'football');
        $bookmakers = (int) collect($events)->max('bookmakers');
        $sources = array_filter([
            $football ? 'Highlightly (classement, forme, confrontations, modèle)' : null,
            "The Odds API (cotes de {$bookmakers} bookmaker" . ($bookmakers > 1 ? 's' : '') . ')',
        ]);
        $missing = $football
            ? 'xG/xGA, blessures et suspensions, compositions probables'
            : 'forme, classement, blessures et compositions (seules les cotes sont disponibles)';

        $lines[] = sprintf(
            'Bilan : %d sélection%s · cote totale %s · probabilité du coupon %d %% · confiance %s',
            count($events),
            count($events) > 1 ? 's' : '',
            number_format($total, 2, ',', ''),
            (int) round($probability * 100),
            $confidence,
        );
        $lines[] = 'Sources consultées : ' . implode(' · ', $sources);
        $lines[] = 'Dernière vérification : ' . $now->format('H:i') . ' · Données manquantes : ' . $missing;
        $lines[] = 'Mise conseillée : 1 à 3 % de votre bankroll, sans jamais augmenter la mise pour vous refaire.';
        $lines[] = self::DISCLAIMER;

        return mb_substr(implode("\n", $lines), 0, 3500);
    }

    /**
     * @param array<int, array<string, mixed>> $fixtures
     */
    private function noBetMessage(array $fixtures): string
    {
        if ($fixtures === []) {
            $oddsError = OddsService::usage()['error'];

            return 'Aucun pari recommandé : aucun match analysable. '
                . ($oddsError ?: 'Il faut des matchs réels avec leurs cotes (Highlightly + clé The Odds API) ; des matchs collés à la main ne permettent pas de vérifier les sélections.');
        }

        $rejections = collect($fixtures)
            ->flatMap(fn ($fixture) => $this->analyzer->analyseFixture($fixture)['selections'])
            ->pluck('rejet')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(2)
            ->map(fn ($count, $reason) => "{$reason} ({$count})")
            ->implode(', ');

        return 'Aucun pari recommandé : aucune sélection n’est assez solide sur '
            . count($fixtures) . ' match(s) analysé(s)' . ($rejections !== '' ? '. Principales raisons : ' . $rejections : '') . '.';
    }

    private function percent(?float $value): string
    {
        return $value === null ? 'n/d' : (int) round($value * 100) . ' %';
    }

    private function signedPercent(?float $value): string
    {
        return $value === null ? 'n/d' : ($value >= 0 ? '+' : '') . number_format($value * 100, 1, ',', '') . ' %';
    }
}

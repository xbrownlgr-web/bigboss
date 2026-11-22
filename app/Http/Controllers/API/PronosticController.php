<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use App\Models\VipSubscription;
use App\Services\ExactScoreGenerator;
use App\Services\ExpoPushService;
use App\Services\SpecialMarketsGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PronosticController extends Controller
{
    public function __construct(private readonly ExpoPushService $pushService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'niveau' => 'nullable|string|max:50',
            'couleur' => 'nullable|string|max:20',
            'cote_totale' => 'nullable|numeric',
            'probabilite' => 'nullable|integer|min:0|max:100',
            'evenements' => 'nullable|array|min:1',
            'evenements.*.match' => 'required_with:evenements|string|max:255',
            'evenements.*.pronostic' => 'required_with:evenements|string|max:255',
            'evenements.*.cote' => 'required_with:evenements|numeric',
            'code_1xbet' => 'nullable|string|max:255',
            'code_melbet' => 'nullable|string|max:255',
            'match_date' => 'nullable|date',
            'match_time' => 'nullable',
            'team_home' => 'nullable|string',
            'team_away' => 'nullable|string',
            'competition' => 'nullable|string',
            'prediction' => 'nullable|string',
            'odds' => 'nullable|numeric',
            'confidence' => 'nullable|integer',
            'analysis' => 'nullable|string',
            'is_vip' => 'boolean',
        ]);

        if (! empty($data['evenements'])) {
            $data = $this->normalizeComboPayload($data);
        } else {
            $legacy = $request->validate([
                'match_date' => 'required|date',
                'match_time' => 'required',
                'team_home' => 'required|string',
                'team_away' => 'required|string',
                'competition' => 'required|string',
                'prediction' => 'required|string',
                'odds' => 'required|numeric',
                'confidence' => 'required|integer',
                'analysis' => 'required|string',
                'is_vip' => 'boolean',
            ]);

            $data = [
                ...$data,
                ...$legacy,
            ];
        }

        $pronostic = Pronostic::create([
            ...$data,
            'published_at' => now(),
        ]);

        $this->sendPublishedNotification($pronostic);

        return response()->json(['success' => true, 'pronostic' => $pronostic]);
    }

    public function index(Request $request): JsonResponse
    {
        // Route publique : le jeton, s'il est envoyé, sert à débloquer les VIP de l'abonné.
        $user = $request->user('sanctum');
        $subscriptions = $this->subscriptionsOf($user);

        // L'app n'affiche que le dernier combiné de chaque niveau : inutile d'envoyer tout l'historique.
        $pronostics = Pronostic::query()
            ->orderByDesc('published_at')
            ->limit(50)
            ->get()
            ->map(fn (Pronostic $pronostic) => $this->publicPayload($pronostic, $subscriptions));

        return response()->json(['success' => true, 'pronostics' => $pronostics]);
    }

    /**
     * Historique et bilan des coupons terminés (gagnés / perdus) sur les 90 derniers jours :
     * taux de réussite, nombre de coupons et nombre encaissés. Les coupons VIP gardent leurs
     * matchs masqués pour les non-abonnés (seuls le niveau, la cote et le résultat sont visibles).
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $subscriptions = $this->subscriptionsOf($user);

        $since = now()->subDays(90);
        // Taux de réussite des combinés Parieurs seulement : les scores exacts et grosses cotes (~30/50/60)
        // sont des paris à haut risque qui fausseraient le taux de réussite des combinés classiques.
        $excludedFromRate = [ExactScoreGenerator::LEVEL, SpecialMarketsGenerator::LEVEL_GROSSES_COTES];
        $forRate = fn ($query) => $query->whereNull('niveau')->orWhereNotIn('niveau', $excludedFromRate);

        $settled = Pronostic::query()->whereNotNull('result_status')->where('published_at', '>=', $since)->where($forRate);

        $won = (clone $settled)->where('result_status', 'won')->count();
        $total = $settled->count();

        // Liste de l'historique : coupons gagnés seulement (décision du fondateur du 2026-11-18,
        // les perdus ne s'affichent plus). Le bilan ci-dessus reste calculé sur tous les coupons.
        $items = Pronostic::query()
            ->where('result_status', 'won')
            ->where('published_at', '>=', $since)
            ->orderByDesc('published_at')
            ->limit(60)
            ->get()
            ->map(fn (Pronostic $pronostic) => $this->publicPayload($pronostic, $subscriptions));

        return response()->json([
            'success' => true,
            'stats' => [
                // Coupons terminés (avec un résultat), coupons encaissés et taux de réussite.
                'settled' => $total,
                'won' => $won,
                'lost' => $total - $won,
                'win_rate' => $total > 0 ? (int) round($won / $total * 100) : 0,
                'window_days' => 90,
            ],
            'pronostics' => $items,
        ]);
    }

    /**
     * Niveaux VIP débloqués : abonnements actifs du client ; le pack « Accès VIP complet »
     * (AppSetting::ALL_VIP_LEVEL) débloque automatiquement toutes les catégories VIP.
     * Le compte admin (mode admin de l'application) voit tous les coupons VIP en clair.
     *
     * @return array<string, mixed> niveau => fin de l'abonnement
     */
    private function subscriptionsOf(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->is_admin) {
            return array_fill_keys(AppSetting::VIP_LEVELS, now()->addYear());
        }

        $active = $user->vipSubscriptions()->active()->pluck('ends_at', 'level')->all();
        $allEndsAt = $active[AppSetting::ALL_VIP_LEVEL] ?? null;

        if ($allEndsAt !== null) {
            $allCarbon = Carbon::parse($allEndsAt);
            foreach (AppSetting::VIP_LEVELS as $level) {
                $existing = isset($active[$level]) ? Carbon::parse($active[$level]) : null;
                $active[$level] = $existing && $existing->gt($allCarbon) ? $existing : $allCarbon;
            }
        }

        return $active;
    }

    /**
     * Coupons VIP payants : complets pour les abonnés actifs du niveau (ou du pack Complet).
     * Pour les autres, seuls le niveau, la cote, la confiance, le nombre de matchs, le résultat
     * et des métadonnées non confidentielles (palier grosse cote, niveau de risque) sont envoyés :
     * les matchs, joueurs, analyses et codes ne quittent jamais le serveur.
     *
     * @param array<string, mixed> $subscriptions niveau => fin de l'abonnement actif
     */
    private function publicPayload(Pronostic $pronostic, array $subscriptions): array
    {
        $data = $pronostic->toArray();
        $meta = $this->safeMeta($pronostic);

        if (! in_array($pronostic->niveau, AppSetting::VIP_LEVELS, true)) {
            return [...$data, 'meta' => $meta, 'locked' => false];
        }

        $endsAt = $subscriptions[$pronostic->niveau] ?? null;
        $vip = [
            'subscribed' => $endsAt !== null,
            'subscription_ends_at' => $endsAt ? Carbon::parse($endsAt)->toIso8601String() : null,
            'meta' => $meta,
        ];

        if ($endsAt !== null) {
            return [...$data, ...$vip, 'locked' => false, 'evenements_count' => count($pronostic->evenements ?? [])];
        }

        // Score exact GAGNÉ : le match est terminé, le dévoiler ne coûte rien et c'est la preuve de
        // l'offre (« BIG BOSS avait annoncé 2-1 ») pour les non-abonnés. L'analyse reste réservée.
        if ($pronostic->niveau === ExactScoreGenerator::LEVEL && $pronostic->result_status === 'won') {
            return [
                ...$data,
                ...$vip,
                'locked' => true,
                'evenements_count' => count($pronostic->evenements ?? []),
                'analysis' => '',
                'code_1xbet' => null,
                'code_melbet' => null,
            ];
        }

        $offerName = AppSetting::offerName((string) $pronostic->niveau);

        return [
            ...$data,
            ...$vip,
            'locked' => true,
            'evenements' => [],
            'evenements_count' => count($pronostic->evenements ?? []),
            'analysis' => '',
            'code_1xbet' => null,
            'code_melbet' => null,
            // Anciennes versions de l'application : elles affichent ces champs à la place des matchs.
            'team_home' => $pronostic->niveau === ExactScoreGenerator::LEVEL ? 'Score' : 'Coupon',
            'team_away' => $pronostic->niveau === ExactScoreGenerator::LEVEL ? 'exact' : $offerName,
            'competition' => $pronostic->niveau === ExactScoreGenerator::LEVEL ? 'Score exact' : $offerName,
            'prediction' => 'Réservé aux membres ' . $offerName,
            'result_note' => null,
        ];
    }

    /**
     * Métadonnées publiques (jamais de noms d'équipes, joueurs ni pronostics) permettant d'afficher
     * proprement une ligne verrouillée (palier Grosses cotes, niveau de risque, confiance, marché).
     *
     * @return array<string, mixed>|null
     */
    private function safeMeta(Pronostic $pronostic): ?array
    {
        $first = is_array($pronostic->evenements) && isset($pronostic->evenements[0]) && is_array($pronostic->evenements[0])
            ? $pronostic->evenements[0]
            : null;

        if (! $first) {
            return null;
        }

        return match ($pronostic->niveau) {
            SpecialMarketsGenerator::LEVEL_GROSSES_COTES => array_filter([
                'tier' => $first['tier'] ?? null,
                'tier_label' => $first['tier_label'] ?? null,
                'risque' => $first['risque'] ?? 'eleve',
                'probabilite_globale' => $first['probabilite_globale'] ?? null,
            ], fn ($v) => $v !== null),
            SpecialMarketsGenerator::LEVEL_CARTONS => array_filter([
                'marche' => 'Cartons jaunes',
                'confiance' => $first['confiance'] ?? null,
                'valeur' => $first['valeur'] ?? null,
            ], fn ($v) => $v !== null),
            SpecialMarketsGenerator::LEVEL_BUTEURS => array_filter([
                'marche' => $first['marche'] ?? 'Buteur à tout moment',
                'confiance' => $first['confiance'] ?? null,
                'valeur' => $first['valeur'] ?? null,
                'composition_confirmee' => $first['composition_confirmee'] ?? null,
            ], fn ($v) => $v !== null),
            default => null,
        };
    }

    public function result(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'result_status' => ['required', 'in:won,lost'],
            'result_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $pronostic = Pronostic::findOrFail($id);
        $pronostic->update([
            'result_status' => $validated['result_status'],
            'result_note' => $validated['result_note'] ?? null,
            'result_checked_at' => now(),
        ]);

        $notified = 0;

        if ($pronostic->result_status === 'won' && ! $pronostic->result_notified_at) {
            $notified = $this->sendWonNotification($pronostic);
            $pronostic->update(['result_notified_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'pronostic' => $pronostic->fresh(),
            'push_sent' => $pronostic->result_status === 'won',
            'notified_users' => $notified,
        ]);
    }

    private function normalizeComboPayload(array $data): array
    {
        $firstEvent = $data['evenements'][0];
        [$teamHome, $teamAway] = $this->splitMatchName($firstEvent['match']);

        $niveau = $data['niveau'] ?? 'Starter';
        $coteTotale = $data['cote_totale']
            ?? collect($data['evenements'])->reduce(
                fn (float $carry, array $event): float => $carry * (float) $event['cote'],
                1.0
            );
        $probabilite = $data['probabilite'] ?? $data['confidence'] ?? 70;

        return [
            ...$data,
            'niveau' => $niveau,
            'couleur' => $data['couleur'] ?? '#6B7280',
            'cote_totale' => round((float) $coteTotale, 2),
            'probabilite' => (int) $probabilite,
            'code_1xbet' => $data['code_1xbet'] ?? null,
            'code_melbet' => $data['code_melbet'] ?? null,
            'match_date' => $data['match_date'] ?? Carbon::today()->toDateString(),
            'match_time' => $data['match_time'] ?? '00:00',
            'team_home' => $data['team_home'] ?? $teamHome,
            'team_away' => $data['team_away'] ?? $teamAway,
            'competition' => $data['competition'] ?? 'Combiné ' . $niveau,
            'prediction' => $data['prediction'] ?? 'Combiné ' . count($data['evenements']) . ' événements',
            'odds' => $data['odds'] ?? round((float) $coteTotale, 2),
            'confidence' => $data['confidence'] ?? (int) $probabilite,
            'analysis' => $data['analysis'] ?? 'Combiné généré automatiquement pour BIG BOSS.',
            'is_vip' => $data['is_vip'] ?? $niveau !== 'Starter',
        ];
    }

    private function splitMatchName(string $match): array
    {
        $parts = preg_split('/\s+vs\s+/i', $match);

        if (count($parts) >= 2) {
            return [trim($parts[0]), trim($parts[1])];
        }

        return [$match, 'Adversaire'];
    }

    private function sendPublishedNotification(Pronostic $pronostic): void
    {
        $level = $pronostic->niveau ?: 'Starter';
        $odd = number_format((float) ($pronostic->cote_totale ?? $pronostic->odds), 2);
        $data = [
            'type' => 'pronostic',
            'event' => 'published',
            'pronostic_id' => $pronostic->id,
            'niveau' => $level,
        ];

        if (in_array($level, AppSetting::VIP_LEVELS, true)) {
            // VIP : l'abonné reçoit le pronostic suivi de sa cote ; le non-abonné, une simple
            // invitation sans la cote (la cote reste réservée aux membres VIP).
            $subscriberIds = VipSubscription::query()->active()->where('level', $level)->select('user_id');

            $this->pushService->sendToUsers(
                User::query()->whereIn('id', $subscriberIds),
                "💎 Coupon VIP {$level} · cote {$odd}",
                'Vos matchs sont disponibles dans l’application BIG BOSS.',
                $data,
            );
            $this->pushService->sendToUsers(
                User::query()->whereNotIn('id', $subscriberIds),
                "👀 Nouveau pronostic VIP {$level}",
                "Un nouveau pronostic est disponible dans le VIP {$level}. Jette un coup d’œil dans l’application !",
                $data,
            );

            return;
        }

        $this->pushService->sendToActiveUsers(
            '🔥 Nouveau pronostic disponible',
            "⚽ {$level} - cote {$odd} disponible maintenant sur BIG BOSS.",
            $data,
        );
    }

    private function sendWonNotification(Pronostic $pronostic): int
    {
        $level = $pronostic->niveau ?: 'Pronostic';
        $odd = number_format((float) ($pronostic->cote_totale ?? $pronostic->odds), 2);

        return $this->pushService->sendToActiveUsers(
            '✅ Pronostic gagnant',
            "🏆 {$level} - cote {$odd} validée. Ouvrez l’application pour voir les prochains coupons.",
            [
                'type' => 'pronostic',
                'event' => 'won',
                'pronostic_id' => $pronostic->id,
                'niveau' => $level,
            ],
        );
    }
}

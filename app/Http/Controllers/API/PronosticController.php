<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use App\Models\VipSubscription;
use App\Services\ExactScoreGenerator;
use App\Services\ExpoPushService;
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
        $subscriptions = $user
            ? $user->vipSubscriptions()->active()->pluck('ends_at', 'level')->all()
            : [];

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
        $subscriptions = $user
            ? $user->vipSubscriptions()->active()->pluck('ends_at', 'level')->all()
            : [];

        $since = now()->subDays(90);
        $notScore = fn ($query) => $query->whereNull('niveau')->orWhere('niveau', '!=', ExactScoreGenerator::LEVEL);

        // Taux de réussite des combinés seulement : les scores exacts (pari à haut risque, règle du
        // fondateur) n'y entrent pas, ni en gagné ni en perdu, pour ne pas fausser le taux.
        $settled = Pronostic::query()->whereNotNull('result_status')->where('published_at', '>=', $since)->where($notScore);

        $won = (clone $settled)->where('result_status', 'won')->count();
        $total = $settled->count();

        $items = Pronostic::query()
            ->whereNotNull('result_status')
            ->where('published_at', '>=', $since)
            // Score exact : ajouté à l'historique quand il gagne, jamais mélangé quand il perd.
            ->where(fn ($query) => $query->where($notScore)->orWhere('result_status', 'won'))
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
     * Coupons VIP payants (Silver, Gold, Platinum) : complets pour les abonnés actifs du niveau.
     * Pour les autres, seuls le niveau, la cote, la confiance, le nombre de matchs et le résultat
     * sont envoyés : les codes ne quittent jamais le serveur.
     *
     * @param array<string, mixed> $subscriptions niveau => fin de l'abonnement actif
     */
    private function publicPayload(Pronostic $pronostic, array $subscriptions): array
    {
        $data = $pronostic->toArray();

        if (! in_array($pronostic->niveau, AppSetting::VIP_LEVELS, true)) {
            return [...$data, 'locked' => false];
        }

        $endsAt = $subscriptions[$pronostic->niveau] ?? null;
        $vip = [
            'subscribed' => $endsAt !== null,
            'subscription_ends_at' => $endsAt ? Carbon::parse($endsAt)->toIso8601String() : null,
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
            'team_away' => $pronostic->niveau === ExactScoreGenerator::LEVEL ? 'exact' : 'VIP ' . $pronostic->niveau,
            'competition' => $pronostic->niveau === ExactScoreGenerator::LEVEL ? 'Score exact' : 'Combiné ' . $pronostic->niveau,
            'prediction' => $pronostic->niveau === ExactScoreGenerator::LEVEL ? 'Réservé aux membres Score exact' : 'Réservé aux membres VIP',
            'result_note' => null,
        ];
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

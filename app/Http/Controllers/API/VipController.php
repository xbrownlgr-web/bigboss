<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\VipFreeClaim;
use App\Models\VipSubscription;
use App\Services\MakeTouService;
use App\Services\VipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Throwable;

class VipController extends Controller
{
    public function __construct(private readonly VipService $vip)
    {
    }

    /**
     * « Accéder » : clé liée au compte, et message WhatsApp pré-écrit qui la contient.
     */
    public function requestAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', 'string', Rule::in(AppSetting::VIP_LEVELS)],
        ]);

        $vipRequest = $this->vip->requestKey($request->user(), $validated['level']);
        $price = $vipRequest->price;

        $message = "Bonjour, je suis sur votre application BIG BOSS et je veux intégrer le VIP {$vipRequest->level}"
            . ($price ? ' qui coûte ' . number_format($price, 0, ',', ' ') . ' FCFA' : '')
            . ". Ma clé : {$vipRequest->key}";

        return response()->json([
            'success' => true,
            'key' => $vipRequest->key,
            'level' => $vipRequest->level,
            'price' => $price,
            'expires_at' => $vipRequest->expires_at->toIso8601String(),
            'whatsapp' => preg_replace('/\D+/', '', (string) AppSetting::value('vip_whatsapp_number')) ?: null,
            'message' => $message,
        ]);
    }

    /**
     * Abonnements de l'utilisateur, actifs ou terminés (pour « VIP Gold actif jusqu'au… »).
     */
    public function me(Request $request): JsonResponse
    {
        $subscriptions = $request->user()
            ->vipSubscriptions()
            ->orderByDesc('ends_at')
            ->get()
            ->map(fn ($subscription) => [
                'level' => $subscription->level,
                'starts_at' => $subscription->starts_at->toIso8601String(),
                'ends_at' => $subscription->ends_at->toIso8601String(),
                'active' => $subscription->isActive(),
            ]);

        return response()->json(['success' => true, 'subscriptions' => $subscriptions]);
    }

    // Niveau concerné par l'offre « 1 mois offert » (parrainage 1xBet).
    private const FREE_LEVEL = 'Silver';

    /**
     * Offre « VIP Silver offert » : consignes à afficher + état de la demande de l'utilisateur.
     */
    public function freeConfig(Request $request): JsonResponse
    {
        $user = $request->user();

        $hasActive = VipSubscription::query()
            ->where('user_id', $user->id)
            ->where('level', self::FREE_LEVEL)
            ->where('ends_at', '>', now())
            ->exists();

        $latest = VipFreeClaim::query()
            ->where('user_id', $user->id)
            ->where('level', self::FREE_LEVEL)
            ->latest()
            ->first();

        $pending = $latest && $latest->status === VipFreeClaim::STATUS_PENDING;

        return response()->json([
            'success' => true,
            'enabled' => strtolower((string) AppSetting::value('vip_free_enabled', 'oui')) === 'oui',
            'level' => self::FREE_LEVEL,
            'promo_code' => AppSetting::value('vip_free_promo_code', 'CR7MILLIONS'),
            'min_deposit' => (int) AppSetting::value('vip_free_min_deposit', '2000'),
            'download_url' => AppSetting::value('vip_free_download_url', 'https://1xbet.bj/download/apk'),
            'instructions' => AppSetting::value('vip_free_instructions', ''),
            'can_submit' => ! $hasActive && ! $pending,
            'has_active' => $hasActive,
            'claim' => $latest ? [
                'status' => $latest->status,
                'account_id' => $latest->account_id,
                'admin_note' => $latest->admin_note,
                'created_at' => $latest->created_at->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * L'utilisateur envoie sa demande : ID de compte 1xBet + capture (base64). Validée en admin.
     */
    public function submitFreeClaim(Request $request): JsonResponse
    {
        if (strtolower((string) AppSetting::value('vip_free_enabled', 'oui')) !== 'oui') {
            return response()->json(['success' => false, 'message' => 'L’offre n’est pas disponible pour le moment.'], 422);
        }

        $validated = $request->validate([
            'account_id' => ['required', 'string', 'max:60'],
            'screenshot' => ['required', 'string', 'max:8000000'], // base64, ~6 Mo max
            'mime' => ['nullable', 'string', 'in:image/jpeg,image/png,image/webp'],
        ]);

        $user = $request->user();

        $hasActive = VipSubscription::query()
            ->where('user_id', $user->id)
            ->where('level', self::FREE_LEVEL)
            ->where('ends_at', '>', now())
            ->exists();

        if ($hasActive) {
            return response()->json(['success' => false, 'message' => 'Vous avez déjà un VIP Silver actif.'], 422);
        }

        $alreadyPending = VipFreeClaim::query()
            ->where('user_id', $user->id)
            ->where('level', self::FREE_LEVEL)
            ->where('status', VipFreeClaim::STATUS_PENDING)
            ->exists();

        if ($alreadyPending) {
            return response()->json(['success' => false, 'message' => 'Votre demande est déjà en cours de vérification.'], 422);
        }

        // On ne garde que les données base64 (sans l'éventuel préfixe « data:image/...;base64, »).
        $screenshot = preg_replace('#^data:[^;]+;base64,#', '', $validated['screenshot']);

        VipFreeClaim::create([
            'user_id' => $user->id,
            'level' => self::FREE_LEVEL,
            'promo_code' => AppSetting::value('vip_free_promo_code', 'CR7MILLIONS'),
            'account_id' => trim($validated['account_id']),
            'screenshot' => $screenshot,
            'screenshot_mime' => $validated['mime'] ?? 'image/jpeg',
            'status' => VipFreeClaim::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande envoyée ! Nous vérifions votre dépôt et activons votre mois offert sous peu.',
        ]);
    }

    /**
     * Démarre le paiement d'un niveau VIP (MAKETOU) : renvoie l'URL de paiement à ouvrir.
     */
    public function checkout(Request $request, MakeTouService $maketou): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', 'string', Rule::in(AppSetting::VIP_LEVELS)],
            // Semaine (essai moins engageant) ou mois complet. Mois par défaut (comportement inchangé).
            'duration' => ['nullable', 'string', Rule::in(['week', 'month'])],
        ]);
        $duration = $validated['duration'] ?? 'month';

        try {
            $result = $maketou->checkout($request->user(), $validated['level'], $duration);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        // Mémorise le panier -> (utilisateur, niveau, durée) pour activer le VIP à la confirmation.
        Cache::put('maketou:cart:' . $result['cart_id'], [
            'user_id' => $request->user()->id,
            'level' => $validated['level'],
            'days' => $duration === 'week' ? 7 : 30,
        ], now()->addDay());

        return response()->json([
            'success' => true,
            'cart_id' => $result['cart_id'],
            'redirect_url' => $result['redirect_url'],
            'amount' => $result['amount'],
        ]);
    }

    /**
     * Vérifie un paiement (au retour de l'app) et active le VIP s'il est payé.
     */
    public function confirm(Request $request, MakeTouService $maketou): JsonResponse
    {
        $validated = $request->validate(['cart_id' => ['required', 'string', 'max:255']]);
        $activated = $this->settleCart($validated['cart_id'], $maketou);

        return response()->json(['success' => true, 'activated' => $activated]);
    }

    /**
     * Webhook MAKETOU : appelé quand un paiement aboutit. On revérifie le statut puis on active.
     */
    public function webhook(Request $request, MakeTouService $maketou): JsonResponse
    {
        $cartId = (string) ($request->input('cart.id') ?: $request->input('cartId') ?: $request->input('id') ?: '');

        if ($cartId !== '') {
            $this->settleCart($cartId, $maketou);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Active le VIP une seule fois si le panier est payé (« completed »), vérifié côté MAKETOU.
     */
    private function settleCart(string $cartId, MakeTouService $maketou): bool
    {
        $key = 'maketou:cart:' . $cartId;
        $info = Cache::get($key);

        if (! is_array($info)) {
            return false;
        }

        if (! empty($info['done'])) {
            return true;
        }

        if ($maketou->status($cartId) !== 'completed') {
            return false;
        }

        $this->vip->grantPaid((int) $info['user_id'], (string) $info['level'], (int) ($info['days'] ?? 30));
        Cache::put($key, [...$info, 'done' => true], now()->addDays(2));

        return true;
    }
}

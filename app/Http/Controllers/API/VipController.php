<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
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

    /**
     * Démarre le paiement d'un niveau VIP (MAKETOU) : renvoie l'URL de paiement à ouvrir.
     */
    public function checkout(Request $request, MakeTouService $maketou): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', 'string', Rule::in(AppSetting::VIP_LEVELS)],
        ]);

        try {
            $result = $maketou->checkout($request->user(), $validated['level']);
        } catch (Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        // Mémorise le panier -> (utilisateur, niveau) pour activer le VIP à la confirmation.
        Cache::put('maketou:cart:' . $result['cart_id'], [
            'user_id' => $request->user()->id,
            'level' => $validated['level'],
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

        $this->vip->grantPaid((int) $info['user_id'], (string) $info['level']);
        Cache::put($key, [...$info, 'done' => true], now()->addDays(2));

        return true;
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\VipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $message = "Bonjour, je suis sur votre application Binho Flash et je veux intégrer le VIP {$vipRequest->level}"
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
}

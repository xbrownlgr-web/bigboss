<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paiement des abonnements VIP via MAKETOU (api.maketou.net, page de paiement Moneroo).
 * Un seul produit « prix libre » sert pour tous les niveaux : on envoie le montant exact
 * (prix du VIP + frais %), ce qui permet d'ajouter la commission au moment du paiement.
 * Clé API, identifiant du produit et pourcentage de frais sont réglés dans Admin > Paiement.
 */
class MakeTouService
{
    private const BASE_URL = 'https://api.maketou.net';

    /**
     * Crée le paiement d'un niveau VIP pour un utilisateur.
     *
     * @return array{cart_id: string, redirect_url: string, amount: int}
     */
    public function checkout(User $user, string $level): array
    {
        $apiKey = trim((string) AppSetting::value('maketou_api_key'));
        $productId = trim((string) AppSetting::value('maketou_product_id'));

        if ($apiKey === '' || $productId === '') {
            throw new RuntimeException('Paiement indisponible : MAKETOU n’est pas encore configuré (Admin > Paiement).');
        }

        $price = AppSetting::vipOffers()[$level]['price'] ?? null;

        if (! $price || $price <= 0) {
            throw new RuntimeException('Le prix du VIP ' . $level . ' n’est pas défini.');
        }

        $fee = (float) (AppSetting::value('maketou_fee_percent') ?? 7);
        $amount = (int) round($price * (1 + $fee / 100));
        [$first, $last] = $this->splitName($user->name);
        $base = rtrim((string) config('app.url'), '/');

        $response = Http::asJson()
            ->acceptJson()
            ->withToken($apiKey)
            ->timeout(30)
            ->post(self::BASE_URL . '/api/v1/stores/cart/checkout', [
                'productDocumentId' => $productId,
                'email' => $user->email,
                'firstName' => $first,
                'lastName' => $last,
                'phone' => $user->phone,
                'customerPrice' => $amount,
                'redirectURL' => $base . '/vip/paiement/retour',
                'webhookUrl' => $base . '/api/v1/vip/maketou/webhook',
                'meta' => ['user_id' => $user->id, 'level' => $level],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('MAKETOU a refusé la demande de paiement (HTTP ' . $response->status() . ').');
        }

        $cartId = (string) ($response->json('cart.id') ?? '');
        $redirectUrl = (string) ($response->json('redirectUrl') ?? '');

        if ($cartId === '' || $redirectUrl === '') {
            throw new RuntimeException('Réponse MAKETOU incomplète : paiement impossible.');
        }

        return ['cart_id' => $cartId, 'redirect_url' => $redirectUrl, 'amount' => $amount];
    }

    /**
     * Statut d'un panier. « completed » = payé. Null si indisponible.
     */
    public function status(string $cartId): ?string
    {
        $apiKey = trim((string) AppSetting::value('maketou_api_key'));

        if ($apiKey === '' || $cartId === '') {
            return null;
        }

        $response = Http::acceptJson()
            ->withToken($apiKey)
            ->timeout(30)
            ->get(self::BASE_URL . '/api/v1/stores/cart/' . $cartId);

        if (! $response->successful()) {
            return null;
        }

        return (string) ($response->json('cart.status') ?? $response->json('status') ?? '') ?: null;
    }

    /**
     * @return array{0: string, 1: string} prénom, nom
     */
    private function splitName(?string $name): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $name)) ?: []));
        $first = $parts[0] ?? 'Client';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $first;

        return [$first, $last];
    }
}

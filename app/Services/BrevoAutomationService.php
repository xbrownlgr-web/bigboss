<?php

namespace App\Services;

use App\Mail\BigBossAutoMail;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\VipSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Système d'e-mails automatiques Brevo :
 *  1. Synchronisation automatique des contacts dans Brevo (API v3 /contacts) à l'inscription et au VIP.
 *  2. E-mails transactionnels VIP : confirmation d'activation, rappel J-3 avant expiration, rappel fin d'accès.
 *  3. Séquence automatique quotidienne (bigboss:send-auto-emails) :
 *     - J+1 après inscription (non-VIP) : découverte des 5 catégories de pronostics.
 *     - J+3 après inscription (sans Pack complet) : offre Pack Accès VIP complet avec calcul exact de la réduction.
 *  4. Campagne manuelle depuis l'application admin (POST /api/v1/admin/email/broadcast).
 *
 * Plafond quotidien intégré pour respecter confortablement le quota gratuit Brevo (300 e-mails/jour).
 */
class BrevoAutomationService
{
    private const CONTACTS_ENDPOINT = 'https://api.brevo.com/v3/contacts';

    // Garde toujours de la marge sur les 300 e-mails/jour du plan gratuit Brevo pour les
    // e-mails critiques (inscription, mot de passe oublié, transactions, activations VIP).
    public const DAILY_AUTO_CAP = 120;
    public const BROADCAST_CAP = 150;

    public function syncContact(User $user, ?string $vipLevel = null): void
    {
        $apiKey = trim((string) config('services.brevo.key'));

        if ($apiKey === '' || empty($user->email)) {
            return;
        }

        $attributes = array_filter([
            'PRENOM' => $user->name,
            'NOM' => $user->name,
            'PAYS' => $user->country,
            'VIP' => $vipLevel ? AppSetting::offerName($vipLevel) : null,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            Http::timeout(8)
                ->acceptJson()
                ->withHeaders(['api-key' => $apiKey])
                ->post(self::CONTACTS_ENDPOINT, [
                    'email' => $user->email,
                    'attributes' => (object) $attributes,
                    'updateEnabled' => true,
                ]);
        } catch (Throwable $exception) {
            Log::warning('Sync contact Brevo échouée.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function sendVipActivated(User $user, VipSubscription $subscription): void
    {
        if (empty($user->email)) {
            return;
        }

        $offerName = AppSetting::offerName($subscription->level);
        $until = $subscription->ends_at->timezone(config('app.timezone'))->format('d/m/Y');

        try {
            Mail::to($user)->send(new BigBossAutoMail(
                mailSubject: "💎 {$offerName} activé sur BIG BOSS",
                mailTitle: "{$offerName} activé !",
                recipientName: $user->name,
                badgeText: 'ABONNEMENT VIP ACTIVÉ',
                paragraphs: [
                    "Votre accès {$offerName} est désormais actif dans l’application BIG BOSS.",
                    "Vous pouvez consulter vos pronostics VIP dès maintenant jusqu’au {$until}.",
                ],
                highlightBox: [
                    'badge' => 'ACCÈS DÉBLOQUÉ',
                    'title' => $offerName,
                    'subtitle' => "Valable jusqu’au {$until}",
                ],
                ctaLabel: 'Ouvrir BIG BOSS',
                ctaUrl: url('/'),
            ));
        } catch (Throwable $exception) {
            Log::warning('E-mail activation VIP Brevo non envoyé.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function sendVipReminder(User $user, VipSubscription $subscription, string $kind): void
    {
        if (empty($user->email)) {
            return;
        }

        $offerName = AppSetting::offerName($subscription->level);
        $until = $subscription->ends_at->timezone(config('app.timezone'))->format('d/m/Y');
        $isSoon = $kind === 'soon';

        try {
            Mail::to($user)->send(new BigBossAutoMail(
                mailSubject: $isSoon
                    ? "⏳ Votre {$offerName} se termine le {$until}"
                    : "Votre {$offerName} BIG BOSS est arrivé à expiration",
                mailTitle: $isSoon ? "Votre {$offerName} expire bientôt" : "Renouvelez votre {$offerName}",
                recipientName: $user->name,
                badgeText: 'RAPPEL ABONNEMENT VIP',
                paragraphs: $isSoon
                    ? [
                        "Votre abonnement {$offerName} prend fin le {$until}.",
                        "Renouvelez directement depuis l’application BIG BOSS (paiement rapide et activation immédiate) pour continuer à recevoir tous vos pronostics sans coupure.",
                    ]
                    : [
                        "Votre abonnement {$offerName} est arrivé à expiration.",
                        "Réactivez votre accès ou passez au Pack Accès VIP complet directement dans l’application BIG BOSS pour débloquer les 5 catégories en une seule fois.",
                    ],
                highlightBox: [
                    'badge' => $isSoon ? 'EXPIRE BIENTÔT' : 'ABONNEMENT TERMINÉ',
                    'title' => $offerName,
                    'subtitle' => $isSoon ? "Fin prévue le {$until}" : 'Renouvellement en 1 clic dans l’app',
                ],
                ctaLabel: 'Accéder à BIG BOSS',
                ctaUrl: url('/'),
            ));
        } catch (Throwable $exception) {
            Log::warning('E-mail rappel VIP Brevo non envoyé.', [
                'user_id' => $user->id,
                'kind' => $kind,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Séquence automatique quotidienne :
     *  - J+1 : relance bienvenue + découverte des 5 catégories pour les nouveaux inscrits non-VIP.
     *  - J+3 : offre Pack Accès VIP complet avec calcul automatique de l'économie vs VIP individuels.
     *
     * @return array{welcome_j1: int, pack_promo_j3: int}
     */
    public function runDailySequence(int $dailyCap = self::DAILY_AUTO_CAP): array
    {
        $sentToday = DB::table('email_automation_logs')
            ->where('sent_at', '>=', now()->startOfDay())
            ->count();

        $remaining = max(0, $dailyCap - $sentToday);
        $counts = ['welcome_j1' => 0, 'pack_promo_j3' => 0];

        if ($remaining === 0) {
            return $counts;
        }

        $activeVipUserIds = VipSubscription::query()->active()->pluck('user_id')->all();

        // 1. Relance J+1 : inscrits entre 24 h et 72 h, non VIP, pas encore relancés
        $j1Users = User::query()
            ->whereNull('account_deleted_at')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('created_at', '<=', now()->subDay())
            ->where('created_at', '>=', now()->subDays(3))
            ->when($activeVipUserIds !== [], fn ($q) => $q->whereNotIn('id', $activeVipUserIds))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('email_automation_logs')
                ->whereColumn('email_automation_logs.user_id', 'users.id')
                ->where('email_automation_logs.campaign', 'welcome_j1'))
            ->limit($remaining)
            ->get();

        foreach ($j1Users as $user) {
            if ($this->sendAndLog($user, 'welcome_j1', new BigBossAutoMail(
                mailSubject: '🎯 Vos 5 catégories de pronostics vous attendent sur BIG BOSS',
                mailTitle: 'Découvrez les 5 catégories BIG BOSS',
                recipientName: $user->name,
                badgeText: 'BIENVENUE SUR BIG BOSS',
                paragraphs: [
                    "Avez-vous déjà consulté les pronostics du jour dans l’application BIG BOSS ?",
                    "Chaque jour, nos analyses couvrent 5 marchés spécialisés : Combinés Parieurs (Starter gratuit + Bronze à Platinum), Scores exacts, Grosses cotes, Cartons jaunes et Buteurs.",
                    "Ouvrez l’onglet Pronostics pour profiter gratuitement du coupon Starter et découvrir nos offres VIP.",
                ],
                ctaLabel: 'Voir les pronostics du jour',
                ctaUrl: url('/'),
            ))) {
                $counts['welcome_j1']++;
                $remaining--;
            }

            if ($remaining <= 0) {
                return $counts;
            }
        }

        // 2. Relance J+3 : offre Pack Accès VIP complet avec calcul exact de la réduction
        $packStats = $this->packSavingsSummary();
        if (! $packStats) {
            return $counts;
        }

        $packUserIds = VipSubscription::query()
            ->active()
            ->where('level', AppSetting::ALL_VIP_LEVEL)
            ->pluck('user_id')
            ->all();

        $j3Users = User::query()
            ->whereNull('account_deleted_at')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('created_at', '<=', now()->subDays(3))
            ->where('created_at', '>=', now()->subDays(10))
            ->when($packUserIds !== [], fn ($q) => $q->whereNotIn('id', $packUserIds))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('email_automation_logs')
                ->whereColumn('email_automation_logs.user_id', 'users.id')
                ->where('email_automation_logs.campaign', 'pack_promo_j3'))
            ->limit($remaining)
            ->get();

        $sumText = number_format($packStats['individual_sum'], 0, ',', ' ') . ' FCFA';
        $packText = number_format($packStats['pack_price'], 0, ',', ' ') . ' FCFA';
        $saveText = number_format($packStats['savings'], 0, ',', ' ') . ' FCFA';
        $pct = $packStats['discount_percent'];

        foreach ($j3Users as $user) {
            if ($this->sendAndLog($user, 'pack_promo_j3', new BigBossAutoMail(
                mailSubject: $pct > 0
                    ? "👑 -{$pct} % : débloquez TOUT le VIP BIG BOSS en un seul paiement"
                    : '👑 Débloquez TOUT le VIP BIG BOSS en un seul paiement',
                mailTitle: 'Débloquez tout le VIP en une fois',
                recipientName: $user->name,
                badgeText: 'PACK ACCÈS VIP COMPLET',
                paragraphs: $pct > 0
                    ? [
                        "Au lieu de dépenser {$sumText} pour activer nos {$packStats['individual_count']} offres VIP une par une, débloquez TOUT le VIP en même temps pour seulement {$packText}/mois.",
                        "Vous économisez {$saveText} (-{$pct} %) et accédez immédiatement aux 5 catégories : Parieurs (Bronze → Platinum), Score exact, Grosses cotes, Cartons et Buteurs.",
                    ]
                    : [
                        "Accédez à nos 5 catégories VIP (Parieurs, Score exact, Grosses cotes, Cartons et Buteurs) en un seul paiement de {$packText}/mois.",
                        "Paiement direct dans l’application BIG BOSS avec activation immédiate.",
                    ],
                highlightBox: [
                    'badge' => $pct > 0 ? "-{$pct} % DE RÉDUCTION" : 'TOUT INCLUS',
                    'title' => "Pack Accès VIP complet : {$packText} / mois",
                    'subtitle' => $pct > 0 ? "Au lieu de {$sumText} · Économisez {$saveText}" : 'Les 5 catégories débloquées',
                ],
                ctaLabel: 'Débloquer tout le VIP',
                ctaUrl: url('/'),
            ))) {
                $counts['pack_promo_j3']++;
                $remaining--;
            }

            if ($remaining <= 0) {
                break;
            }
        }

        return $counts;
    }

    /**
     * Envoi manuel d'un e-mail Brevo depuis le compte administrateur de l'app.
     *
     * @return array{sent: int, skipped: int}
     */
    public function broadcastEmail(string $subject, string $body, string $target = 'all', int $limit = self::BROADCAST_CAP): array
    {
        $query = User::query()
            ->whereNull('account_deleted_at')
            ->where('is_active', true)
            ->whereNotNull('email');

        if ($target === 'vip') {
            $vipIds = VipSubscription::query()->active()->select('user_id');
            $query->whereIn('id', $vipIds);
        } elseif ($target === 'non_vip') {
            $vipIds = VipSubscription::query()->active()->select('user_id');
            $query->whereNotIn('id', $vipIds);
        }

        $paragraphs = array_values(array_filter(
            array_map('trim', preg_split('/\R{2,}/', trim($body)) ?: [trim($body)]),
            fn ($line) => $line !== '',
        ));

        $sent = 0;
        $skipped = 0;

        foreach ($query->latest('id')->limit($limit)->get(['id', 'name', 'email']) as $user) {
            try {
                Mail::to($user)->send(new BigBossAutoMail(
                    mailSubject: $subject,
                    mailTitle: $subject,
                    recipientName: $user->name,
                    badgeText: 'ANNONCE BIG BOSS',
                    paragraphs: $paragraphs,
                    ctaLabel: 'Ouvrir BIG BOSS',
                    ctaUrl: url('/'),
                ));
                $sent++;
            } catch (Throwable $exception) {
                $skipped++;
                Log::warning('E-mail broadcast Brevo échoué.', [
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * @return array{pack_price: int, individual_sum: int, individual_count: int, savings: int, discount_percent: int}|null
     */
    public function packSavingsSummary(): ?array
    {
        $offers = AppSetting::vipOffers();
        $packPrice = (int) ($offers[AppSetting::ALL_VIP_LEVEL]['price'] ?? 0);

        if ($packPrice <= 0) {
            return null;
        }

        $individualSum = 0;
        $individualCount = 0;

        foreach (AppSetting::VIP_LEVELS as $level) {
            if ($level === AppSetting::ALL_VIP_LEVEL) {
                continue;
            }
            $price = (int) ($offers[$level]['price'] ?? 0);
            if ($price > 0) {
                $individualSum += $price;
                $individualCount++;
            }
        }

        $savings = $individualSum > $packPrice ? $individualSum - $packPrice : 0;
        $discountPercent = $individualSum > 0 && $savings > 0 ? (int) round(($savings / $individualSum) * 100) : 0;

        return [
            'pack_price' => $packPrice,
            'individual_sum' => $individualSum,
            'individual_count' => $individualCount,
            'savings' => $savings,
            'discount_percent' => $discountPercent,
        ];
    }

    private function sendAndLog(User $user, string $campaign, BigBossAutoMail $mailable): bool
    {
        try {
            Mail::to($user)->send($mailable);
            DB::table('email_automation_logs')->insertOrIgnore([
                'user_id' => $user->id,
                'campaign' => $campaign,
                'sent_at' => now(),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning("E-mail auto Brevo ({$campaign}) non envoyé.", [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}

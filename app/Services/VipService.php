<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use App\Models\VipRequest;
use App\Models\VipSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Abonnements VIP payés sur WhatsApp.
 *
 * 1. L'utilisateur touche « Accéder » : une clé unique liée à son compte et au niveau est créée
 *    et glissée dans le message WhatsApp pré-écrit.
 * 2. Après paiement, l'admin active la clé : l'abonnement court 30 jours à partir de ce moment
 *    (ou 30 jours de plus après la fin actuelle s'il est encore actif : aucun jour perdu).
 * Chaque niveau est séparé : un abonné Platinum ne voit pas les coupons Gold.
 */
class VipService
{
    public const DURATION_DAYS = 30;

    // Lancement du VIP payant : les résultats affichés aux clients commencent à cette date.
    public const VIP_LAUNCH = '2026-10-15 00:00:00';

    // Une clé non activée sous 7 jours expire : l'utilisateur en redemande une depuis l'app.
    public const KEY_LIFETIME_DAYS = 7;

    // Sans caractères ambigus (0/O, 1/I/L) : la clé se recopie sans erreur depuis WhatsApp.
    private const KEY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly ExpoPushService $push)
    {
    }

    public static function isVipLevel(?string $level): bool
    {
        return in_array($level, AppSetting::VIP_LEVELS, true);
    }

    /**
     * Clé de demande d'accès, réutilisée tant qu'elle est valable (toucher plusieurs fois
     * « Accéder » ne multiplie pas les demandes).
     */
    public function requestKey(User $user, string $level): VipRequest
    {
        if (! self::isVipLevel($level)) {
            throw new InvalidArgumentException("Niveau VIP inconnu : {$level}");
        }

        $existing = VipRequest::query()
            ->where('user_id', $user->id)
            ->where('level', $level)
            ->usable()
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return VipRequest::create([
            'user_id' => $user->id,
            'level' => $level,
            'key' => $this->newKey($level),
            'price' => AppSetting::vipOffers()[$level]['price'] ?? null,
            'status' => VipRequest::STATUS_PENDING,
            'expires_at' => now()->addDays(self::KEY_LIFETIME_DAYS),
        ]);
    }

    public function findUsableKey(string $key): ?VipRequest
    {
        $key = strtoupper(preg_replace('/\s+/', '', $key));

        return VipRequest::query()->with('user')->where('key', $key)->usable()->first();
    }

    /**
     * Active une clé après paiement : abonnement de 30 jours à partir de maintenant, ou prolongé
     * de 30 jours s'il est encore actif. La clé ne sert qu'une fois.
     */
    public function activate(VipRequest $request, User $admin): VipSubscription
    {
        $subscription = DB::transaction(function () use ($request, $admin) {
            $request = VipRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $request->isUsable()) {
                throw new RuntimeException('Cette clé est déjà utilisée ou a expiré.');
            }

            $subscription = $this->addDays($request->user_id, $request->level, self::DURATION_DAYS);

            $request->update([
                'status' => VipRequest::STATUS_ACTIVATED,
                'activated_at' => now(),
                'activated_by' => $admin->id,
            ]);

            return $subscription;
        });

        $this->push->sendToUser(
            $subscription->user,
            "💎 VIP {$subscription->level} activé",
            "Votre VIP {$subscription->level} est actif jusqu’au {$this->date($subscription->ends_at)}. Les coupons sont maintenant visibles dans l’application.",
            ['type' => 'vip', 'event' => 'activated', 'niveau' => $subscription->level],
        );

        return $subscription;
    }

    /**
     * Active (ou prolonge) un VIP après un paiement en ligne réussi (MAKETOU), puis notifie.
     */
    public function grantPaid(int $userId, string $level): VipSubscription
    {
        $subscription = $this->addDays($userId, $level, self::DURATION_DAYS);

        $this->push->sendToUser(
            $subscription->user,
            "💎 VIP {$subscription->level} activé",
            "Votre paiement est confirmé. Votre VIP {$subscription->level} est actif jusqu’au {$this->date($subscription->ends_at)} : les coupons sont maintenant visibles dans l’application.",
            ['type' => 'vip', 'event' => 'activated', 'niveau' => $subscription->level],
        );

        return $subscription;
    }

    /**
     * VIP offert (parrainage 1xBet validé par l'admin) : 30 jours, avec une notification dédiée.
     */
    public function grantFree(int $userId, string $level): VipSubscription
    {
        $subscription = $this->addDays($userId, $level, self::DURATION_DAYS);

        $this->push->sendToUser(
            $subscription->user,
            "🎁 VIP {$subscription->level} offert !",
            "Félicitations ! Votre mois de VIP {$subscription->level} OFFERT est activé jusqu’au {$this->date($subscription->ends_at)}. Les coupons sont maintenant visibles dans l’application.",
            ['type' => 'vip', 'event' => 'granted_free', 'niveau' => $subscription->level],
        );

        return $subscription;
    }

    /**
     * Prolongation à la main (geste commercial, paiement reçu sans clé…).
     */
    public function extend(VipSubscription $subscription, int $days = self::DURATION_DAYS): VipSubscription
    {
        return $this->addDays($subscription->user_id, $subscription->level, $days);
    }

    public function cancel(VipSubscription $subscription): void
    {
        $subscription->update(['ends_at' => now()]);
    }

    private function addDays(int $userId, string $level, int $days): VipSubscription
    {
        $subscription = VipSubscription::query()
            ->where('user_id', $userId)
            ->where('level', $level)
            ->lockForUpdate()
            ->first();

        if ($subscription && $subscription->isActive()) {
            $subscription->update([
                'ends_at' => $subscription->ends_at->copy()->addDays($days),
                'reminded_soon_at' => null,
                'reminded_ended_at' => null,
            ]);

            return $subscription->fresh('user');
        }

        $subscription ??= new VipSubscription(['user_id' => $userId, 'level' => $level]);
        $subscription->fill([
            'starts_at' => now(),
            'ends_at' => now()->addDays($days),
            'reminded_soon_at' => null,
            'reminded_ended_at' => null,
        ])->save();

        return $subscription->fresh('user');
    }

    /**
     * Nouveau coupon VIP : les abonnés actifs du niveau reçoivent la notification complète,
     * les autres un message court, sans le code, qui donne envie de s'abonner.
     *
     * @return array{subscribers: int, others: int}
     */
    public function notifyNewCoupon(Pronostic $pronostic): array
    {
        $level = (string) $pronostic->niveau;
        $odd = number_format((float) ($pronostic->cote_totale ?? $pronostic->odds), 2, ',', '');
        $data = ['type' => 'pronostic', 'event' => 'published', 'pronostic_id' => $pronostic->id, 'niveau' => $level];
        $subscriberIds = VipSubscription::query()->active()->where('level', $level)->select('user_id');

        $subscribers = $this->push->sendToUsers(
            User::query()->whereIn('id', $subscriberIds),
            "💎 Nouveau coupon VIP {$level}",
            "Cote {$odd} : votre code 1xBet est disponible dans l’application BIG BOSS.",
            $data,
        );

        $others = $this->push->sendToUsers(
            User::query()->whereNotIn('id', $subscriberIds),
            "💎 Nouveau coupon VIP {$level} disponible",
            "Cote {$odd}. Rejoignez le VIP {$level} pour recevoir le code.",
            $data,
        );

        return ['subscribers' => $subscribers, 'others' => $others];
    }

    /**
     * Rappels : 3 jours avant la fin, puis le jour de la fin. Chaque rappel ne part qu'une fois
     * par période d'abonnement.
     *
     * @return array{soon: int, ended: int}
     */
    public function sendReminders(): array
    {
        $soon = 0;
        $ended = 0;

        VipSubscription::query()
            ->with('user')
            ->whereNull('reminded_soon_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addDays(3))
            ->each(function (VipSubscription $subscription) use (&$soon) {
                $this->push->sendToUser(
                    $subscription->user,
                    "⏳ Votre VIP {$subscription->level} se termine bientôt",
                    "Il prend fin le {$this->date($subscription->ends_at)}. Renouvelez depuis l’application pour ne manquer aucun coupon.",
                    ['type' => 'vip', 'event' => 'expiring', 'niveau' => $subscription->level],
                );
                $subscription->update(['reminded_soon_at' => now()]);
                $soon++;
            });

        VipSubscription::query()
            ->with('user')
            ->whereNull('reminded_ended_at')
            ->where('ends_at', '<=', now())
            ->where('ends_at', '>', now()->subDays(2))
            ->each(function (VipSubscription $subscription) use (&$ended) {
                $this->push->sendToUser(
                    $subscription->user,
                    "Votre VIP {$subscription->level} est terminé",
                    "Renouvelez-le depuis l’application BIG BOSS pour recevoir les prochains coupons.",
                    ['type' => 'vip', 'event' => 'ended', 'niveau' => $subscription->level],
                );
                $subscription->update(['reminded_ended_at' => now()]);
                $ended++;
            });

        return compact('soon', 'ended');
    }

    /**
     * Résultats des coupons VIP sur 30 jours, par niveau : « 8 gagnés sur 10 ».
     *
     * @return array<string, array{won: int, settled: int}>
     */
    public function stats(): array
    {
        $rows = Pronostic::query()
            ->whereIn('niveau', AppSetting::VIP_LEVELS)
            // Tous les coupons VIP (IA, page Pronostics ou page VIP) depuis le lancement du VIP
            // payant : les anciens combinés Silver/Gold/Platinum d'avant ne comptent pas.
            ->whereNotNull('result_status')
            ->where('published_at', '>=', now()->subDays(30)->max(Carbon::parse(self::VIP_LAUNCH)))
            ->selectRaw("niveau, SUM(CASE WHEN result_status = 'won' THEN 1 ELSE 0 END) AS won, COUNT(*) AS settled")
            ->groupBy('niveau')
            ->get()
            ->keyBy('niveau');

        return collect(AppSetting::VIP_LEVELS)
            ->mapWithKeys(fn ($level) => [$level => [
                'won' => (int) ($rows[$level]->won ?? 0),
                'settled' => (int) ($rows[$level]->settled ?? 0),
            ]])
            ->all();
    }

    private function newKey(string $level): string
    {
        do {
            $suffix = collect(range(1, 5))
                ->map(fn () => self::KEY_ALPHABET[random_int(0, strlen(self::KEY_ALPHABET) - 1)])
                ->implode('');
            $key = 'BF-' . Str::upper($level) . '-' . $suffix;
        } while (VipRequest::query()->where('key', $key)->exists());

        return $key;
    }

    private function date(Carbon $date): string
    {
        return $date->copy()->timezone(config('app.timezone'))->format('d/m/Y');
    }
}

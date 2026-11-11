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

    // Durées proposées dans l'admin pour une activation manuelle (jours => libellé).
    public const MANUAL_DURATIONS = [
        1 => '1 jour',
        3 => '3 jours',
        7 => '1 semaine',
        14 => '2 semaines',
        30 => '1 mois',
        60 => '2 mois',
        90 => '3 mois',
        180 => '6 mois',
        365 => '1 an',
    ];

    public function __construct(private readonly ExpoPushService $push)
    {
    }

    /**
     * Activation à la main depuis l'admin : n'importe quelle offre (Score exact comprise), pour
     * la durée choisie (ajoutée à la fin actuelle si l'abonnement est encore actif).
     */
    public function grantManual(int $userId, string $level, int $days): VipSubscription
    {
        if (! self::isVipLevel($level)) {
            throw new InvalidArgumentException("Niveau VIP inconnu : {$level}");
        }

        $subscription = $this->addDays($userId, $level, max(1, $days));
        [$name, $plan, $items] = $this->labels($subscription->level);

        $this->push->sendToUser(
            $subscription->user,
            "💎 {$name} activé",
            "Votre {$plan} est actif jusqu’au {$this->date($subscription->ends_at)}. Les {$items} sont maintenant visibles dans l’application.",
            ['type' => 'vip', 'event' => 'activated', 'niveau' => $subscription->level],
        );

        return $subscription;
    }

    public static function durationLabel(int $days): string
    {
        return self::MANUAL_DURATIONS[$days] ?? $days . ' jours';
    }

    /**
     * Alerte WhatsApp à l'admin : un client vient de payer en ligne (demande du fondateur, le
     * 2026-11-11 : les activations automatiques arrivaient sans que personne ne soit prévenu).
     * Envoyée APRÈS la réponse, pour ne jamais retarder l'app ni le webhook MAKETOU.
     */
    private function alertAdminOfPayment(VipSubscription $subscription, int $days): void
    {
        $user = $subscription->user;
        $offer = AppSetting::vipOffers()[$subscription->level] ?? [];
        $price = $days <= 7 ? ($offer['price_week'] ?? null) : ($offer['price'] ?? null);
        $text = implode("\n", array_filter([
            '💎 Abonnement payé en ligne',
            AppSetting::offerName($subscription->level) . ' · ' . ($days <= 7 ? '1 semaine' : self::durationLabel($days))
                . ($price ? ' · ' . number_format((int) $price, 0, ',', ' ') . ' FCFA' : ''),
            'Client : ' . $user->name . ($user->phone ? ' (' . $user->phone . ')' : ''),
            'Actif jusqu’au ' . $this->date($subscription->ends_at),
            url('/admin/vip'),
        ]));

        dispatch(function () use ($text, $subscription) {
            app(WhatsAppNotifier::class)->send($text, ['vip_subscription_id' => $subscription->id]);
        })->afterResponse();
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

        [$name, $plan, $items] = $this->labels($subscription->level);

        $this->push->sendToUser(
            $subscription->user,
            "💎 {$name} activé",
            "Votre {$plan} est actif jusqu’au {$this->date($subscription->ends_at)}. Les {$items} sont maintenant visibles dans l’application.",
            ['type' => 'vip', 'event' => 'activated', 'niveau' => $subscription->level],
        );

        return $subscription;
    }

    /**
     * Libellés des notifications : « VIP Gold » / « coupons », ou « Score exact » / « scores exacts ».
     *
     * @return array{0: string, 1: string, 2: string, 3: string} nom, abonnement, éléments, élément
     */
    private function labels(string $level): array
    {
        return $level === ExactScoreGenerator::LEVEL
            ? [AppSetting::offerName($level), 'abonnement Score exact', 'scores exacts', 'score exact']
            : [AppSetting::offerName($level), AppSetting::offerName($level), 'coupons', 'coupon'];
    }

    /**
     * Active (ou prolonge) un VIP après un paiement en ligne réussi (MAKETOU), puis notifie.
     * $days = 7 pour un abonnement à la semaine, 30 (par défaut) pour le mois complet.
     */
    public function grantPaid(int $userId, string $level, int $days = self::DURATION_DAYS): VipSubscription
    {
        $subscription = $this->addDays($userId, $level, $days);
        $duration = $days <= 7 ? 'semaine' : 'mois';
        [$name, $plan, $items] = $this->labels($subscription->level);

        $this->push->sendToUser(
            $subscription->user,
            "💎 {$name} activé",
            "Votre paiement est confirmé. Votre {$plan} ({$duration}) est actif jusqu’au {$this->date($subscription->ends_at)} : les {$items} sont maintenant visibles dans l’application.",
            ['type' => 'vip', 'event' => 'activated', 'niveau' => $subscription->level],
        );

        $this->alertAdminOfPayment($subscription, $days);

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
                [, $plan, , $item] = $this->labels($subscription->level);
                $this->push->sendToUser(
                    $subscription->user,
                    "⏳ Votre {$plan} se termine bientôt",
                    "Il prend fin le {$this->date($subscription->ends_at)}. Renouvelez depuis l’application pour ne manquer aucun {$item}.",
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
                [, $plan, $items] = $this->labels($subscription->level);
                $this->push->sendToUser(
                    $subscription->user,
                    "Votre {$plan} est terminé",
                    "Renouvelez-le depuis l’application BIG BOSS pour recevoir les prochains {$items}.",
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
                // Score exact : seuls les scores trouvés sont montrés (règle du fondateur : les
                // pertes ne sont pas mélangées) — « settled » à 0, l'app n'affiche pas de taux.
                'settled' => $level === ExactScoreGenerator::LEVEL ? 0 : (int) ($rows[$level]->settled ?? 0),
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

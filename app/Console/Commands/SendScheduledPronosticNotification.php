<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use App\Models\VipSubscription;
use App\Services\ExpoPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SendScheduledPronosticNotification extends Command
{
    protected $signature = 'binho:send-pronostic-notification {--batch= : N’envoyer que les notifications de cette génération}';

    protected $description = 'Envoie au plus une notification de pronostic due, en respectant un délai de deux minutes.';

    public function handle(ExpoPushService $push): int
    {
        $notification = DB::transaction(function () {
            $now = now();
            $query = DB::table('scheduled_pronostic_notifications')
                ->whereNull('sent_at')
                ->where('available_at', '<=', $now)
                ->where(function ($query) use ($now) {
                    $query->whereNull('claimed_at')
                        ->orWhere('claimed_at', '<', $now->copy()->subMinutes(5));
                });

            if ($batchId = $this->option('batch')) {
                $query->where('batch_id', $batchId);
            }

            $candidate = $query->orderBy('available_at')->orderBy('id')->lockForUpdate()->first();

            if (! $candidate) {
                return null;
            }

            $inFlight = DB::table('scheduled_pronostic_notifications')
                ->whereNull('sent_at')
                ->where('claimed_at', '>=', $now->copy()->subMinutes(5))
                ->exists();

            if ($inFlight) {
                return null;
            }

            $lastSentAt = DB::table('scheduled_pronostic_notifications')
                ->whereNotNull('sent_at')
                ->orderByDesc('sent_at')
                ->value('sent_at');

            // Comparaison à la minute près : un envoi fait à 08:00:40 libère le suivant à
            // 08:02:00, sinon il glisserait à 08:03 et l'écart deviendrait trois minutes.
            if ($lastSentAt && Carbon::parse($lastSentAt)->startOfMinute()->addMinutes(2)->isAfter($now)) {
                return null;
            }

            DB::table('scheduled_pronostic_notifications')
                ->where('id', $candidate->id)
                ->update(['claimed_at' => $now, 'updated_at' => $now]);

            return $candidate;
        });

        if (! $notification) {
            return self::SUCCESS;
        }

        $pronostic = Pronostic::find($notification->pronostic_id);

        if (! $pronostic) {
            DB::table('scheduled_pronostic_notifications')
                ->where('id', $notification->id)
                ->delete();

            return self::SUCCESS;
        }

        $level = $pronostic->niveau ?: 'Starter';
        $odd = number_format((float) ($pronostic->cote_totale ?? $pronostic->odds), 2);
        $data = [
            'type' => 'pronostic',
            'event' => 'published',
            'pronostic_id' => $pronostic->id,
            'niveau' => $level,
        ];

        if (in_array($level, AppSetting::VIP_LEVELS, true)) {
            // Coupon VIP de l'IA (Silver) : les abonnés le voient, les autres sont invités à s'abonner.
            $subscriberIds = VipSubscription::query()->active()->where('level', $level)->select('user_id');

            // Abonné actif du niveau : il reçoit le pronostic VIP suivi de sa cote.
            $push->sendToUsers(
                User::query()->whereIn('id', $subscriberIds),
                "💎 Coupon VIP {$level} · cote {$odd}",
                "Vos matchs sont disponibles dans l’application Binho Flash.",
                $data,
            );
            // Jusqu'à 11 coupons VIP par jour : les non-abonnés n'en reçoivent que quelques annonces,
            // et sans la cote — juste une invitation à jeter un coup d'œil.
            $teaserKey = 'pronostics:vip_teasers:' . now()->setTimezone(config('app.timezone'))->toDateString();
            $maxTeasers = (int) (AppSetting::value('ai_vip_teasers_per_day') ?? 2);

            if ((int) Cache::get($teaserKey, 0) < $maxTeasers) {
                Cache::put($teaserKey, (int) Cache::get($teaserKey, 0) + 1, now()->addDays(2));
                $push->sendToUsers(
                    User::query()->whereNotIn('id', $subscriberIds),
                    "👀 Nouveau pronostic VIP {$level}",
                    "Un nouveau pronostic est disponible dans le VIP {$level}. Jette un coup d’œil dans l’application !",
                    $data,
                );
            }
        } else {
            $push->sendToActiveUsers(
                '🔥 Nouveau pronostic disponible',
                "⚽ {$level} - cote {$odd} disponible maintenant sur Binho Flash.",
                $data,
            );
        }

        DB::table('scheduled_pronostic_notifications')
            ->where('id', $notification->id)
            ->update(['sent_at' => now(), 'claimed_at' => null, 'updated_at' => now()]);

        $this->info("Notification du pronostic #{$pronostic->id} envoyée.");

        return self::SUCCESS;
    }
}

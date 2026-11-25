<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BannedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'fcm_token',
        'phone_normalized',
        'email',
        'reason',
        'banned_by',
    ];

    /**
     * Normalise un numéro en chiffres uniquement (les 8 derniers chiffres pour le Bénin sont
     * aussi comparés pour bloquer aussi bien « +229 01 97… » que « 97… »).
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || strlen($digits) < 6) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Bénin : « 0197000000 » ou « 97000000 » → « 2290197000000 »
        if (strlen($digits) === 8) {
            return '22901' . $digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '01')) {
            return '229' . $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '229')) {
            return '22901' . substr($digits, 3);
        }

        return substr($digits, 0, 32);
    }

    public static function cleanDeviceId(?string $deviceId): ?string
    {
        $clean = trim((string) $deviceId);

        if ($clean === '' || strlen($clean) < 6) {
            return null;
        }

        return substr($clean, 0, 120);
    }

    /**
     * Indique si cet appareil, ce numéro, cet e-mail ou ce jeton push est banni.
     */
    public static function isBlocked(
        ?string $deviceId = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $fcmToken = null,
    ): bool {
        $cleanDevice = self::cleanDeviceId($deviceId);
        $normPhone = self::normalizePhone($phone);
        $cleanEmail = $email ? Str::lower(trim($email)) : null;
        $cleanFcm = $fcmToken && str_starts_with($fcmToken, 'ExponentPushToken[') ? trim($fcmToken) : null;

        if (! $cleanDevice && ! $normPhone && ! $cleanEmail && ! $cleanFcm) {
            return false;
        }

        return self::query()
            ->where(function ($query) use ($cleanDevice, $normPhone, $cleanEmail, $cleanFcm) {
                if ($cleanDevice) {
                    $query->orWhere('device_id', $cleanDevice);
                }
                if ($normPhone) {
                    $query->orWhere('phone_normalized', $normPhone);
                }
                if ($cleanEmail) {
                    $query->orWhere('email', $cleanEmail);
                }
                if ($cleanFcm) {
                    $query->orWhere('fcm_token', $cleanFcm);
                }
            })
            ->exists();
    }

    /**
     * Bannit un utilisateur, révoque immédiatement toutes ses sessions/jetons et bloque
     * son appareil, son jeton push, son numéro et son e-mail pour toute future inscription.
     */
    public static function banUser(User $user, ?User $admin = null, ?string $reason = null): self
    {
        $cleanReason = $reason ? trim($reason) : null;
        if ($cleanReason === '') {
            $cleanReason = null;
        }

        return DB::transaction(function () use ($user, $admin, $cleanReason) {
            $user->forceFill([
                'is_active' => false,
                'banned_at' => now(),
                'ban_reason' => $cleanReason ? Str::limit($cleanReason, 255, '') : null,
            ])->save();

            $user->tokens()->delete();

            try {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } catch (\Throwable) {
                // La table sessions n'est pas obligatoire si SESSION_DRIVER=file.
            }

            return self::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'device_id' => self::cleanDeviceId($user->device_id),
                    'fcm_token' => $user->fcm_token && str_starts_with($user->fcm_token, 'ExponentPushToken[')
                        ? $user->fcm_token
                        : null,
                    'phone_normalized' => self::normalizePhone($user->phone),
                    'email' => $user->email ? Str::lower(trim($user->email)) : null,
                    'reason' => $cleanReason ? Str::limit($cleanReason, 255, '') : null,
                    'banned_by' => $admin?->id,
                ],
            );
        });
    }

    /**
     * Lève le bannissement d'un utilisateur et débloque son appareil/numéro.
     */
    public static function unbanUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->forceFill([
                'is_active' => true,
                'banned_at' => null,
                'ban_reason' => null,
            ])->save();

            self::query()
                ->where('user_id', $user->id)
                ->when($user->email, fn ($q) => $q->orWhere('email', Str::lower(trim($user->email))))
                ->when(self::cleanDeviceId($user->device_id), fn ($q, $dev) => $q->orWhere('device_id', $dev))
                ->delete();
        });
    }
}

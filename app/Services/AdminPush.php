<?php

namespace App\Services;

use App\Models\User;
use Throwable;

/**
 * Notification sur le téléphone de l'admin (compte admin connecté dans l'application) : nouveau
 * message au support, nouvelle recharge ou demande de retrait. Ne fait jamais échouer l'action.
 */
class AdminPush
{
    public function __construct(private readonly ExpoPushService $push)
    {
    }

    public function send(string $title, string $body, array $data): void
    {
        try {
            $admin = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower((string) config('app.admin_email'))])->first();

            if ($admin && $admin->fcm_token) {
                $this->push->sendToUser($admin, $title, $body, $data);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

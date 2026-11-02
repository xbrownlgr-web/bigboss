<?php

namespace App\Services;

use App\Models\AssistantMessage;
use App\Models\Message;
use App\Models\User;
use App\Models\VipRequest;
use App\Models\VipSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Suppression d'un compte à la demande de l'utilisateur (depuis l'application ou la page web).
 *
 * Effacé : nom, e-mail, téléphone, pays, mot de passe, appareil (notifications), sessions,
 * messages au support et à l'assistant, abonnements VIP. L'historique des recharges et retraits est conservé
 * sans ces coordonnées, pour les obligations comptables et les litiges éventuels.
 */
class AccountDeletionService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            Message::query()->where('user_id', $user->id)->delete();
            AssistantMessage::query()->where('user_id', $user->id)->delete();
            VipRequest::query()->where('user_id', $user->id)->delete();
            VipSubscription::query()->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->forceFill([
                'name' => 'Compte supprimé',
                'email' => 'supprime-' . $user->id . '-' . Str::lower(Str::random(8)) . '@binhoflash.invalid',
                'phone' => null,
                'country' => null,
                'password' => Hash::make(Str::random(40)),
                'remember_token' => null,
                'fcm_token' => null,
                'is_active' => false,
                'is_vip' => false,
                'account_deleted_at' => now(),
            ])->save();
        });
    }
}

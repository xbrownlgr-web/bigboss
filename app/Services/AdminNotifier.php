<?php

namespace App\Services;

use App\Mail\AdminAlert;
use App\Models\AppSetting;
use App\Models\Message;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prévient l'administration par e-mail (adresse dans Admin > Paramètres > Général).
 */
class AdminNotifier
{
    // Valeur d'origine du paramètre : une boîte qui n'existe pas.
    public const PLACEHOLDER_EMAIL = 'admin@binhoflash.com';

    /**
     * Adresses valides du paramètre « E-mail administrateur » (plusieurs possibles, séparées par des virgules).
     *
     * @return array<int, string>
     */
    public static function recipients(): array
    {
        return collect(explode(',', (string) AppSetting::value('admin_email')))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) && strcasecmp($email, self::PLACEHOLDER_EMAIL) !== 0)
            ->unique()
            ->values()
            ->all();
    }

    public function transactionCreated(Transaction $transaction): void
    {
        $user = $transaction->user;
        $isRecharge = $transaction->type === 'recharge';
        $amount = number_format((float) $transaction->amount, 0, ',', ' ') . ' FCFA';
        $platform = $transaction->platform === '1xbet' ? '1xBet' : 'Melbet';

        $details = $isRecharge
            ? [
                'Plateforme' => $platform,
                'ID joueur à recharger' => $transaction->player_id,
                'Montant' => $amount,
                'Opérateur' => $transaction->operator,
                'ID de la transaction Mobile Money' => $transaction->reference_code,
            ]
            : [
                'Plateforme' => $platform,
                'ID du compte joueur' => $transaction->player_id,
                'Code de retrait' => $transaction->withdrawal_code,
                'Montant' => $amount,
                'Opérateur' => $transaction->operator,
                'Numéro Mobile Money à payer' => $transaction->mobile_money_number,
            ];

        $this->send(new AdminAlert(
            subjectLine: ($isRecharge ? '⚡ Nouvelle recharge' : '💸 Nouveau retrait') . " #{$transaction->id} · {$amount}",
            heading: ($isRecharge ? 'Nouvelle recharge' : 'Nouveau retrait') . ' à traiter',
            intro: ($user?->name ?? 'Un utilisateur') . ' vient de soumettre une demande de ' . ($isRecharge ? 'recharge' : 'retrait') . ' de ' . $amount . '.'
                . ($isRecharge ? ' Vérifiez la réception du dépôt avec l’ID de transaction avant d’approuver.' : ''),
            details: [
                'Demande' => '#' . $transaction->id,
                ...$details,
                'Client' => $user?->name,
                'Téléphone du client' => $user?->phone,
                'E-mail du client' => $user?->email,
            ],
            actionUrl: url('/admin/transactions?search=' . $transaction->id),
            actionLabel: 'Traiter la demande',
        ), ['transaction_id' => $transaction->id]);
    }

    public function supportMessage(Message $message): void
    {
        $user = $message->user;

        $this->send(new AdminAlert(
            subjectLine: '💬 Nouveau message de ' . ($user?->name ?? 'un utilisateur'),
            heading: 'Nouveau message au support',
            intro: '« ' . mb_strimwidth($message->content, 0, 500, '…') . ' »',
            details: [
                'Client' => $user?->name,
                'Téléphone' => $user?->phone,
                'E-mail' => $user?->email,
            ],
            actionUrl: url('/admin/messages?user_id=' . $message->user_id),
            actionLabel: 'Répondre',
        ), ['message_id' => $message->id]);
    }

    /**
     * @return string|null null si l'e-mail est parti, sinon la raison de l'échec.
     */
    public function test(): ?string
    {
        if (self::recipients() === []) {
            return 'Aucune adresse valide : renseignez votre vraie adresse dans « E-mail administrateur ».';
        }

        return $this->send(new AdminAlert(
            subjectLine: '✅ Test des alertes BIG BOSS',
            heading: 'Les alertes e-mail fonctionnent',
            intro: 'Vous recevrez un e-mail comme celui-ci à chaque nouvelle recharge, chaque nouveau retrait et chaque nouveau message au support.',
            actionUrl: url('/admin'),
        ), ['test' => true]);
    }

    private function send(AdminAlert $mail, array $context): ?string
    {
        $recipients = self::recipients();

        if ($recipients === []) {
            Log::info('Alerte admin non envoyée : aucune adresse e-mail administrateur valide.', $context);

            return 'Aucune adresse e-mail administrateur valide.';
        }

        try {
            Mail::to($recipients)->send($mail);

            return null;
        } catch (Throwable $exception) {
            Log::warning('Alerte e-mail admin non envoyée.', [...$context, 'error' => $exception->getMessage()]);

            return $exception->getMessage();
        }
    }
}

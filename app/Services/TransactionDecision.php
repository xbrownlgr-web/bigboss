<?php

namespace App\Services;

use App\Mail\TransactionApproved;
use App\Mail\TransactionRejected;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Validation / refus d'une recharge ou d'un retrait par l'admin, identique depuis le site
 * (Admin > Transactions) et depuis le mode admin de l'application : statut, e-mail et
 * notification au client.
 */
class TransactionDecision
{
    public function __construct(private readonly ExpoPushService $push)
    {
    }

    public function approve(Transaction $transaction): Transaction
    {
        $this->ensurePending($transaction);

        $transaction->update(['status' => 'completed', 'processed_at' => now(), 'rejection_reason' => null]);
        $transaction = $transaction->fresh('user');
        $this->email($transaction, TransactionApproved::class);

        $isRecharge = $transaction->type === 'recharge';
        $this->push->sendToUser(
            $transaction->user,
            '✅ Transaction approuvée',
            'Votre ' . ($isRecharge ? 'recharge' : 'retrait') . ' de ' . number_format((float) $transaction->amount, 0, ',', ' ')
                . ' FCFA a été ' . ($isRecharge ? 'approuvée' : 'approuvé') . '.',
            ['type' => 'transaction', 'transaction_id' => $transaction->id, 'status' => 'completed'],
        );

        return $transaction;
    }

    public function reject(Transaction $transaction, ?string $reason): Transaction
    {
        $this->ensurePending($transaction);

        $transaction->update(['status' => 'rejected', 'rejection_reason' => $reason, 'processed_at' => now()]);
        $transaction = $transaction->fresh('user');
        $this->email($transaction, TransactionRejected::class);

        $isRecharge = $transaction->type === 'recharge';
        $this->push->sendToUser(
            $transaction->user,
            '❌ Transaction refusée',
            'Votre ' . ($isRecharge ? 'recharge' : 'retrait') . ' de ' . number_format((float) $transaction->amount, 0, ',', ' ')
                . ' FCFA a été ' . ($isRecharge ? 'refusée' : 'refusé') . '. Consultez les détails dans l’application.',
            ['type' => 'transaction', 'transaction_id' => $transaction->id, 'status' => 'rejected'],
        );

        return $transaction;
    }

    private function ensurePending(Transaction $transaction): void
    {
        if (! in_array($transaction->status, ['pending', 'processing'], true)) {
            throw new RuntimeException('Cette transaction a déjà été traitée.');
        }
    }

    private function email(Transaction $transaction, string $mailableClass): void
    {
        try {
            Mail::to($transaction->user)->send(new $mailableClass($transaction));
        } catch (Throwable $exception) {
            Log::warning('E-mail de transaction admin non envoyé.', [
                'transaction_id' => $transaction->id,
                'mailable' => $mailableClass,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\TransactionCreated;
use App\Models\AppSetting;
use App\Models\Transaction;
use App\Services\AdminNotifier;
use App\Services\WhatsAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TransactionController extends Controller
{
    public function store(Request $request, AdminNotifier $adminNotifier): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'in:1xbet,melbet'],
            // Retrait : l'ID est envoyé par l'application depuis la v1.3 (facultatif pour les anciennes).
            'player_id' => ['required_if:type,recharge', 'nullable', 'string', 'max:255'],
            // Dépôt (recharge) : minimum 200 FCFA. Retrait : minimum 500 FCFA.
            'amount' => ['required', 'numeric', $request->input('type') === 'retrait' ? 'min:500' : 'min:200'],
            'type' => ['required', 'in:recharge,retrait'],
            'mobile_money_number' => ['nullable', 'string', 'max:255'],
            'operator' => ['nullable', 'string', 'max:255'],
            'withdrawal_code' => ['nullable', 'string', 'max:255'],
            'reference_code' => ['nullable', 'string', 'max:255'],
        ]);

        $transaction = Transaction::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        $transaction->load('user');

        $this->sendWhatsAppNotification($transaction, $request->user()->name);
        $this->sendTransactionCreatedEmail($transaction);
        $adminNotifier->transactionCreated($transaction);

        return response()->json([
            'success' => true,
            'transaction' => $transaction,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $transactions = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'transaction' => $transaction,
        ]);
    }

    private function sendWhatsAppNotification(Transaction $transaction, string $userName): void
    {
        $amount = number_format((float) $transaction->amount, 0, ',', ' ') . ' FCFA';
        $lines = [
            ($transaction->type === 'recharge' ? '⚡ NOUVELLE RECHARGE' : '💸 NOUVEAU RETRAIT') . ' #' . $transaction->id,
            'Plateforme : ' . ($transaction->platform === '1xbet' ? '1xBet' : 'Melbet'),
            'Montant : ' . $amount,
            'Opérateur : ' . ($transaction->operator ?: 'N/A'),
        ];

        if ($transaction->type === 'retrait') {
            $lines[] = 'ID joueur : ' . ($transaction->player_id ?: 'N/A');
            $lines[] = 'Code retrait : ' . ($transaction->withdrawal_code ?: 'N/A');
            $lines[] = 'Numéro à payer : ' . ($transaction->mobile_money_number ?: 'N/A');
        } else {
            $lines[] = 'ID joueur : ' . ($transaction->player_id ?: 'N/A');
            $lines[] = 'ID transaction Mobile Money : ' . ($transaction->reference_code ?: 'N/A');
        }

        $lines[] = 'Client : ' . $userName . ($transaction->user?->phone ? ' (' . $transaction->user->phone . ')' : '');
        $lines[] = url('/admin/transactions?search=' . $transaction->id);

        app(WhatsAppNotifier::class)->send(implode("\n", $lines), ['transaction_id' => $transaction->id]);
    }

    private function sendTransactionCreatedEmail(Transaction $transaction): void
    {
        try {
            Mail::to($transaction->user)->send(new TransactionCreated($transaction));
        } catch (Throwable $exception) {
            Log::warning('E-mail TransactionCreated non envoyé.', [
                'transaction_id' => $transaction->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

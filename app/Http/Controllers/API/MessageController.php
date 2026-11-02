<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Transaction;
use App\Services\AdminNotifier;
use App\Services\WhatsAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $messages = Message::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get();

        Message::query()
            ->where('user_id', $request->user()->id)
            ->where('sender', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, AdminNotifier $adminNotifier, WhatsAppNotifier $whatsApp): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
        ]);

        if (! empty($data['transaction_id'])) {
            Transaction::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['transaction_id']);
        }

        $alreadyWaiting = Message::query()
            ->where('user_id', $request->user()->id)
            ->where('sender', 'user')
            ->where('is_read', false)
            ->exists();

        $message = Message::create([
            'user_id' => $request->user()->id,
            'transaction_id' => $data['transaction_id'] ?? null,
            'sender' => 'user',
            'content' => $data['content'],
            'is_read' => false,
        ]);

        // Un seul e-mail tant que l'admin n'a pas lu la conversation : pas de rafale d'alertes.
        if (! $alreadyWaiting) {
            $adminNotifier->supportMessage($message->load('user'));
            $whatsApp->send(
                '💬 Message au support de ' . $request->user()->name . " :\n" . mb_strimwidth($message->content, 0, 300, '…')
                . "\n" . url('/admin/messages?user_id=' . $message->user_id),
                ['message_id' => $message->id],
            );
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ], 201);
    }
}

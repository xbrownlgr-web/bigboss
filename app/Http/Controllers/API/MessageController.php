<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageAudio;
use App\Models\Transaction;
use App\Services\SupportInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $messages = Message::query()
            // Sans le contenu du son : seulement savoir s'il existe encore (lien d'écoute).
            ->with('audio:id,message_id')
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
            'messages' => $messages->map(fn (Message $message) => $message->toAppArray())->values(),
        ]);
    }

    /**
     * Message au support : texte, ou vocal (fichier « audio » + « duration » en secondes).
     */
    public function store(Request $request, SupportInbox $inbox): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required_without:audio', 'nullable', 'string', 'max:2000'],
            'audio' => ['nullable', 'file', 'max:' . MessageAudio::MAX_KILOBYTES, 'mimetypes:audio/mp4,audio/x-m4a,audio/m4a,audio/aac,audio/mpeg,audio/3gpp,audio/ogg,audio/webm,audio/wav,audio/x-wav,video/mp4,video/3gpp,application/octet-stream'],
            'duration' => ['required_with:audio', 'nullable', 'integer', 'min:1', 'max:' . MessageAudio::MAX_SECONDS],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
        ]);

        if (! empty($data['transaction_id'])) {
            Transaction::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['transaction_id']);
        }

        $message = $inbox->receive(
            $request->user(),
            trim((string) ($data['content'] ?? '')),
            $data['transaction_id'] ?? null,
            $request->file('audio'),
            isset($data['duration']) ? (int) $data['duration'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => $message->load('audio:id,message_id')->toAppArray(),
        ], 201);
    }

    /**
     * Écoute d'un vocal depuis l'application (lien signé et temporaire, voir Message::toAppArray).
     */
    public function audio(Message $message): Response
    {
        $audio = $message->audio()->first();

        abort_unless($audio, 404, 'Message vocal supprimé (les vocaux sont gardés 3 jours).');

        return response(base64_decode($audio->data), 200, [
            'Content-Type' => $audio->mime,
            'Content-Length' => (string) $audio->size,
            'Cache-Control' => 'private, max-age=21600',
            'Accept-Ranges' => 'none',
        ]);
    }
}

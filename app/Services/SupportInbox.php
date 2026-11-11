<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageAudio;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Arrivée d'un message client dans le support humain (Admin > Messages), texte ou vocal :
 * enregistrement + alertes e-mail et WhatsApp à l'admin, une seule fois tant qu'il n'a pas lu
 * la conversation (pas de rafale). Sert aussi quand l'assistant IA est coupé.
 */
class SupportInbox
{
    public function __construct(
        private readonly AdminNotifier $adminNotifier,
        private readonly WhatsAppNotifier $whatsApp,
    ) {
    }

    public function receive(User $user, string $content, ?int $transactionId = null, ?UploadedFile $audio = null, ?int $duration = null): Message
    {
        $alreadyWaiting = Message::query()
            ->where('user_id', $user->id)
            ->where('sender', 'user')
            ->where('is_read', false)
            ->exists();

        $message = Message::create([
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'sender' => 'user',
            'content' => $audio ? Message::VOICE_LABEL : $content,
            'audio_duration' => $audio ? max(1, min(MessageAudio::MAX_SECONDS, (int) $duration)) : null,
            'is_read' => false,
        ]);

        if ($audio) {
            MessageAudio::create([
                'message_id' => $message->id,
                'mime' => self::audioMime($audio),
                'size' => (int) $audio->getSize(),
                'data' => base64_encode((string) file_get_contents($audio->getRealPath())),
            ]);
        }

        if (! $alreadyWaiting) {
            $this->adminNotifier->supportMessage($message->load('user'));
            $preview = $audio
                ? Message::VOICE_LABEL . ' (' . self::duration((int) $message->audio_duration) . ')'
                : mb_strimwidth($message->content, 0, 300, '…');

            $this->whatsApp->send(
                '💬 Message au support de ' . $user->name . " :\n" . $preview
                . "\n" . url('/admin/messages?user_id=' . $message->user_id),
                ['message_id' => $message->id],
            );
        }

        return $message;
    }

    public static function duration(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Type lisible par le navigateur de l'admin. Android enregistre en AAC dans un conteneur MP4
     * (« .m4a »), parfois annoncé « application/octet-stream » : on le corrige d'après l'extension.
     */
    private static function audioMime(UploadedFile $audio): string
    {
        $mime = strtolower((string) $audio->getMimeType());

        if (str_starts_with($mime, 'audio/')) {
            return $mime === 'audio/x-m4a' ? 'audio/mp4' : $mime;
        }

        return match (strtolower($audio->getClientOriginalExtension())) {
            'mp3' => 'audio/mpeg',
            'aac' => 'audio/aac',
            'ogg', 'opus' => 'audio/ogg',
            'webm' => 'audio/webm',
            'wav' => 'audio/wav',
            '3gp' => 'audio/3gpp',
            default => 'audio/mp4',
        };
    }
}

<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageAudio;
use App\Models\MessageImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/**
 * Support humain (Admin > Messages, et mode admin de l'application) :
 * - receive() : message d'un client (texte, vocal ou photo) + alertes e-mail et WhatsApp à l'admin,
 *   une seule fois tant qu'il n'a pas lu la conversation (pas de rafale) ;
 * - reply() : réponse de l'admin (texte, vocal ou photo) + notification sur le téléphone du client.
 * Vocaux et photos sont gardés en base (disque Render éphémère) et supprimés 3 jours après.
 */
class SupportInbox
{
    public function __construct(
        private readonly AdminNotifier $adminNotifier,
        private readonly WhatsAppNotifier $whatsApp,
        private readonly ExpoPushService $push,
    ) {
    }

    public function receive(User $user, string $content, ?int $transactionId = null, ?UploadedFile $audio = null, ?int $duration = null, ?UploadedFile $image = null): Message
    {
        $alreadyWaiting = Message::query()
            ->where('user_id', $user->id)
            ->where('sender', 'user')
            ->where('is_read', false)
            ->exists();

        $message = $this->create($user, 'user', $content, $audio, $duration, $image, $transactionId);

        // Téléphone de l'admin (mode admin de l'app) : à chaque message, il ouvre la conversation.
        app(AdminPush::class)->send('💬 ' . $user->name, Str::limit($this->preview($message), 120), ['type' => 'admin_message', 'user_id' => $user->id]);

        if (! $alreadyWaiting) {
            $this->adminNotifier->supportMessage($message->load('user'));
            $this->whatsApp->send(
                '💬 Message au support de ' . $user->name . " :\n" . $this->preview($message)
                . "\n" . url('/admin/messages?user_id=' . $message->user_id),
                ['message_id' => $message->id],
            );
        }

        return $message;
    }

    /**
     * Réponse de l'admin. Renvoie le message et l'éventuelle raison d'échec de la notification
     * (le message est enregistré même si le téléphone n'est pas joignable).
     *
     * @return array{0: Message, 1: ?string}
     */
    public function reply(User $user, string $content, ?UploadedFile $audio = null, ?int $duration = null, ?UploadedFile $image = null): array
    {
        $message = $this->create($user, 'admin', $content, $audio, $duration, $image);

        try {
            $pushError = $this->push->sendToUser(
                $user,
                '💬 Nouveau message du support',
                Str::limit($this->preview($message), 120),
                ['type' => 'support'],
            );
        } catch (Throwable $exception) {
            report($exception);
            $pushError = 'service de notification indisponible';
        }

        return [$message, $pushError];
    }

    private function create(User $user, string $sender, string $content, ?UploadedFile $audio, ?int $duration, ?UploadedFile $image, ?int $transactionId = null): Message
    {
        $label = $audio ? Message::VOICE_LABEL : ($image ? Message::IMAGE_LABEL : '');
        $text = trim($content);

        $message = Message::create([
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'sender' => $sender,
            // Photo avec légende : la légende est gardée ; sinon le libellé sert d'aperçu.
            'content' => $audio ? $label : ($text !== '' ? $text : $label),
            'audio_duration' => $audio ? max(1, min(MessageAudio::MAX_SECONDS, (int) $duration)) : null,
            'has_image' => (bool) $image,
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

        if ($image) {
            MessageImage::create([
                'message_id' => $message->id,
                'mime' => str_starts_with((string) $image->getMimeType(), 'image/') ? (string) $image->getMimeType() : 'image/jpeg',
                'size' => (int) $image->getSize(),
                'data' => base64_encode((string) file_get_contents($image->getRealPath())),
            ]);
        }

        return $message;
    }

    private function preview(Message $message): string
    {
        if ($message->isVoice()) {
            return Message::VOICE_LABEL . ' (' . self::duration((int) $message->audio_duration) . ')';
        }

        return mb_strimwidth($message->content, 0, 300, '…');
    }

    public static function duration(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Type lisible par le navigateur et le téléphone. Android enregistre en AAC (« .m4a »), le
     * navigateur de l'admin en WebM/Ogg (Opus) ; parfois annoncé « application/octet-stream ».
     */
    private static function audioMime(UploadedFile $audio): string
    {
        $mime = strtolower((string) $audio->getMimeType());

        if (str_starts_with($mime, 'audio/')) {
            return $mime === 'audio/x-m4a' ? 'audio/mp4' : $mime;
        }

        if ($mime === 'video/webm') {
            return 'audio/webm';
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

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail automatique ou campagne envoyé via Brevo (activation VIP, rappels d'expiration,
 * relance Pack Accès VIP complet, annonces admin).
 */
class BigBossAutoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, string> $paragraphs
     * @param array{badge?: string, title: string, subtitle?: string}|null $highlightBox
     */
    public function __construct(
        public string $mailSubject,
        public string $mailTitle,
        public string $recipientName,
        public array $paragraphs,
        public ?string $badgeText = null,
        public ?array $highlightBox = null,
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bigboss-message');
    }

    public function attachments(): array
    {
        return [];
    }
}

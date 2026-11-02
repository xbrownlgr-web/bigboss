<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte envoyée à l'administration : nouvelle demande, message au support, test.
 */
class AdminAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<string, string|null> $details lignes « libellé => valeur » du tableau
     */
    public function __construct(
        public string $subjectLine,
        public string $heading,
        public string $intro,
        public array $details = [],
        public ?string $actionUrl = null,
        public string $actionLabel = 'Ouvrir l’admin',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-alert');
    }
}

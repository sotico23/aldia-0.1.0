<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestEmailMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct() {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Prueba de Conexión - Email Marketing',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test',
            with: [
                'sentAt' => now()->format('d/m/Y H:i:s'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

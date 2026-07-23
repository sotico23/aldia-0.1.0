<?php

namespace App\Mail;

use App\Models\MonitoredSite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlowResponseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public MonitoredSite $site,
        public int $responseTime,
        public int $threshold
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ALERTA: {$this->site->name} tiene respuesta lenta",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.slow-response',
            with: [
                'site' => $this->site,
                'responseTime' => $this->responseTime,
                'threshold' => $this->threshold,
            ],
        );
    }
}

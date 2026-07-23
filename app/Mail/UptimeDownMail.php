<?php

namespace App\Mail;

use App\Models\MonitoredSite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UptimeDownMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public MonitoredSite $site
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ALERTA: {$this->site->name} está caído",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.uptime-down',
            with: [
                'site' => $this->site,
                'downSince' => $this->site->last_down_at,
            ],
        );
    }
}

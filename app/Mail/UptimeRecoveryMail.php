<?php

namespace App\Mail;

use App\Models\MonitoredSite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UptimeRecoveryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public MonitoredSite $site
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "RECUPERADO: {$this->site->name} está en línea nuevamente",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.uptime-recovery',
            with: [
                'site' => $this->site,
                'downDuration' => $this->site->last_down_at
                    ? now()->diffInMinutes($this->site->last_down_at)
                    : 0,
            ],
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class MailtrapEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;

    public $body;

    public $toEmail;

    public $toName;

    public $fromEmail;

    public $fromName;

    public function __construct($toEmail, $subject, $body, $fromEmail = null, $fromName = null)
    {
        $this->toEmail = $toEmail;
        $this->subject = $subject;
        $this->body = $body;
        $this->fromEmail = $fromEmail ?? config('mail.from.address', 'hello@demomailtrap.co');
        $this->fromName = $fromName ?? config('mail.from.name', 'Laravel');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->body,
        );
    }

    public function sendViaApi(): bool
    {
        $apiToken = config('services.mailtrap.token');
        $domain = config('services.mailtrap.domain', 'demomailtrap.co');

        $response = Http::withHeaders([
            'Api-Token' => $apiToken,
            'Content-Type' => 'application/json',
        ])->post("https://api.mailtrap.io/api/v1/sending_domains/{$domain}/send", [
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName,
            ],
            'to' => [
                ['email' => $this->toEmail],
            ],
            'subject' => $this->subject,
            'html' => $this->body,
        ]);

        return $response->successful();
    }
}

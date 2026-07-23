<?php

namespace App\Listeners;

use App\Events\MailConfigErrorOccurred;
use Illuminate\Support\Facades\Log;

class LogMailConfigError
{
    public function handle(MailConfigErrorOccurred $event): void
    {
        $context = [
            'config_id' => $event->config->id,
            'error' => $event->error,
        ];

        if ($event->destinatario) {
            $context['destinatario'] = $event->destinatario;
        }

        Log::warning('MailConfigErrorOccurred: Error de configuración de correo', $context);
    }
}

<?php

namespace App\Services;

use App\Mail\TestEmailMailable;
use App\Models\MailConfig;
use App\Models\MailConfigLog;
use App\Models\MailConfigNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailConfigurationService
{
    public function getActiveConfig(int $ownerId): ?MailConfig
    {
        return MailConfig::where('owner_id', $ownerId)
            ->where('is_active', true)
            ->first();
    }

    public function getDefaultConfig(int $ownerId): ?MailConfig
    {
        return MailConfig::where('owner_id', $ownerId)
            ->where('is_default', true)
            ->first();
    }

    public function getAllConfigs(int $ownerId): Collection
    {
        return MailConfig::where('owner_id', $ownerId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    public function applyConfiguration(?MailConfig $config): void
    {
        if (! $config) {
            return;
        }

        $mailerConfig = $config->buildMailerConfig();

        Config::set('mail.mailers.tenant_smtp', $mailerConfig);
        Config::set('mail.default', 'tenant_smtp');
        Config::set('mail.from.address', $config->from_address);
        Config::set('mail.from.name', $config->from_name);
    }

    public function buildMailerConfig(MailConfig $config): array
    {
        return $config->buildMailerConfig();
    }

    public function testConnection(MailConfig $config, string $testEmail): array
    {
        $startTime = microtime(true);
        $originalMailer = Config::get('mail.default');

        try {
            $this->applyConfiguration($config);

            Mail::mailer('tenant_smtp')
                ->to($testEmail)
                ->send(new TestEmailMailable);

            $time = round((microtime(true) - $startTime) * 1000);

            $this->logTestResult($config, [
                'success' => true,
                'test_email' => $testEmail,
                'response_time' => $time,
                'error_message' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Conexión exitosa. Email de prueba enviado.',
                'time' => $time,
            ];
        } catch (\Exception $e) {
            $time = round((microtime(true) - $startTime) * 1000);
            $errorMessage = $this->parseErrorMessage($e->getMessage());

            $this->logTestResult($config, [
                'success' => false,
                'test_email' => $testEmail,
                'response_time' => $time,
                'error_message' => $errorMessage,
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'time' => $time,
            ];
        } finally {
            Config::set('mail.default', $originalMailer ?? 'smtp');
        }
    }

    public function logTestResult(MailConfig $config, array $result): MailConfigLog
    {
        $status = $result['success'] ? 'success' : 'failed';

        if (! $result['success'] && isset($result['response_time']) && $result['response_time'] > 30000) {
            $status = 'timeout';
        }

        return MailConfigLog::create([
            'mail_config_id' => $config->id,
            'owner_id' => $config->owner_id,
            'status' => $status,
            'test_email' => $result['test_email'],
            'error_message' => $result['error_message'],
            'response_time' => $result['response_time'] ?? null,
        ]);
    }

    public function setAsDefault(MailConfig $config): void
    {
        MailConfig::where('owner_id', $config->owner_id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $config->update(['is_default' => true]);
    }

    public function setAsActive(MailConfig $config): void
    {
        MailConfig::where('owner_id', $config->owner_id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $config->update(['is_active' => true]);
    }

    public function notifyError(MailConfig $config, string $mensaje, ?string $destinatario = null): MailConfigNotification
    {
        $tipo = $this->determineErrorType($mensaje);

        $notification = MailConfigNotification::create([
            'mail_config_id' => $config->id,
            'owner_id' => $config->owner_id,
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'destinatario' => $destinatario,
            'leido' => false,
        ]);

        Log::error("MailConfig Error [{$config->name}]: {$mensaje}", [
            'config_id' => $config->id,
            'owner_id' => $config->owner_id,
            'tipo' => $tipo,
            'destinatario_hash' => hash('sha256', $destinatario),
        ]);

        return $notification;
    }

    public function getUnreadNotifications(int $ownerId): Collection
    {
        return MailConfigNotification::where('owner_id', $ownerId)
            ->where('leido', false)
            ->orderByDesc('created_at')
            ->get();
    }

    public function markNotificationAsRead(int $notificationId): void
    {
        MailConfigNotification::where('id', $notificationId)
            ->update(['leido' => true]);
    }

    public function getRecentLogs(int $ownerId, int $limit = 10): Collection
    {
        return MailConfigLog::where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function parseErrorMessage(string $message): string
    {
        $patterns = [
            'Connection refused' => 'Conexión rechazada. Verifica el host y puerto.',
            'Connection timed out' => 'Tiempo de conexión agotado. El servidor no responde.',
            'SSL' => 'Error de SSL/TLS. Verifica la configuración de encriptación.',
            'authentication' => 'Error de autenticación. Verifica usuario y contraseña.',
            'Invalid credentials' => 'Credenciales inválidas. Revisa usuario y contraseña.',
            '550' => 'Email del remitente no válido o rechazado por el servidor.',
            '553' => 'Email del remitente no válido.',
            '554' => 'Mensaje rechazado por el servidor.',
            '421' => 'Servidor temporalmente no disponible. Intenta más tarde.',
            'temporary failure' => 'Fallo temporal del servidor. Intenta más tarde.',
        ];

        foreach ($patterns as $pattern => $description) {
            if (stripos($message, $pattern) !== false) {
                return $description;
            }
        }

        if (strlen($message) > 100) {
            return 'Error de conexión: '.substr($message, 0, 100).'...';
        }

        return $message;
    }

    private function determineErrorType(string $mensaje): string
    {
        if (stripos($mensaje, 'timeout') !== false || stripos($mensaje, 'timed out') !== false) {
            return 'fallo_conexion';
        }

        if (stripos($mensaje, 'authentication') !== false || stripos($mensaje, 'credential') !== false) {
            return 'error_envio';
        }

        if (stripos($mensaje, 'limit') !== false || stripos($mensaje, 'quota') !== false) {
            return 'limite_uso';
        }

        return 'error_envio';
    }
}

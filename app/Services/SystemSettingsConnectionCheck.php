<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Mail\MailManager;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Http;
use Pusher\Pusher;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

class SystemSettingsConnectionCheck
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function check(string $group, array $values): ?string
    {
        return match ($group) {
            'prospecting' => $this->prospecting(),
            'mail' => $this->mail($values),
            'redis' => $this->redis($values) ? 'Redis respondió al comando PING.' : null,
            'reverb' => $this->reverb($values) ? 'Reverb aceptó las credenciales de su API.' : null,
            'aws' => $this->s3($values),
            'pushy' => filled($values['PUSHY_SECRET_API_KEY'])
                && $this->http()->get('https://api.pushy.me/topics/redcliente-connection-check', [
                    'api_key' => $values['PUSHY_SECRET_API_KEY'],
                ])->json('subscribers') !== null ? 'Pushy aceptó la clave de la aplicación.' : null,
            'openrouter' => filled($values['OPENROUTER_API_KEY'])
                && $this->http()->withToken($values['OPENROUTER_API_KEY'])->get('https://openrouter.ai/api/v1/key')->json('data') !== null
                    ? 'OpenRouter aceptó la clave de API.' : null,
            'sii' => $this->sii($values) ? 'Los endpoints de certificación y producción del SII respondieron.' : null,
        };
    }

    private function prospecting(): ?string
    {
        $health = app(ProspectoScrapingService::class)->health(refresh: true);

        return $health['ready'] && $health['queue_ready']
            ? 'El motor guardado tiene Node.js, Chromium y los parámetros de cola requeridos.'
            : null;
    }

    private function mail(array $values): ?string
    {
        if ($values['MAIL_MAILER'] !== 'smtp' || blank($values['MAIL_HOST'])) {
            return null;
        }

        $transport = app(MailManager::class)->createSymfonyTransport([
            ...config('mail.mailers.smtp'),
            'host' => $values['MAIL_HOST'],
            'scheme' => $values['MAIL_SCHEME'] ?: null,
            'port' => (int) $values['MAIL_PORT'],
            'username' => $values['MAIL_USERNAME'],
            'password' => $values['MAIL_PASSWORD'],
            'timeout' => 5,
        ]);

        if (! $transport instanceof SmtpTransport) {
            return null;
        }

        $transport->start();
        $transport->stop();

        return 'Conexión SMTP verificada.';
    }

    private function redis(array $values): bool
    {
        if (blank($values['REDIS_HOST'])) {
            return false;
        }

        $manager = new RedisManager(app(), $values['REDIS_CLIENT'], [
            'options' => config('database.redis.options', []),
            'default' => [
                ...config('database.redis.default'),
                'url' => null,
                'host' => $values['REDIS_HOST'],
                'port' => (int) $values['REDIS_PORT'],
                'password' => $values['REDIS_PASSWORD'],
                'persistent' => false,
                'timeout' => 5,
                'read_timeout' => 5,
                'read_write_timeout' => 5,
            ],
        ]);

        $response = $manager->connection()->ping();

        return $response === true || strtoupper((string) $response) === 'PONG';
    }

    private function reverb(array $values): bool
    {
        if (! preg_match('/\A[a-z0-9.-]+\z/i', (string) $values['REVERB_HOST'])
            || ! preg_match('/\A[a-z0-9_-]+\z/i', (string) $values['REVERB_APP_ID'])
            || blank($values['REVERB_APP_KEY']) || blank($values['REVERB_APP_SECRET'])) {
            return false;
        }

        $client = new Pusher($values['REVERB_APP_KEY'], $values['REVERB_APP_SECRET'], $values['REVERB_APP_ID'], [
            'host' => $values['REVERB_HOST'],
            'port' => (int) $values['REVERB_PORT'],
            'scheme' => $values['REVERB_SCHEME'],
            'timeout' => 5,
        ], new Client(['connect_timeout' => 3, 'timeout' => 5, 'allow_redirects' => false]));

        return is_array($client->get('/channels', associative: true)['channels'] ?? null);
    }

    private function s3(array $values): ?string
    {
        $bucket = (string) $values['AWS_BUCKET'];
        $region = (string) $values['AWS_DEFAULT_REGION'];

        if (! preg_match('/\A[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]\z/', $bucket)
            || ! preg_match('/\A[a-z0-9-]+\z/', $region)
            || blank($values['AWS_ACCESS_KEY_ID']) || blank($values['AWS_SECRET_ACCESS_KEY'])
            || ! defined('CURLOPT_AWS_SIGV4')) {
            return null;
        }

        $domain = str_starts_with($region, 'cn-') ? 'amazonaws.com.cn' : 'amazonaws.com';
        $host = "s3.{$region}.{$domain}";
        $endpoint = rtrim((string) config('filesystems.disks.s3.endpoint'), '/');
        $pathStyle = $values['AWS_USE_PATH_STYLE_ENDPOINT'] === 'true' || str_contains($bucket, '.');
        $url = $endpoint !== '' ? "{$endpoint}/{$bucket}" : ($pathStyle ? "https://{$host}/{$bucket}" : "https://{$bucket}.{$host}/");

        $response = $this->http()->withOptions(['curl' => [
            CURLOPT_AWS_SIGV4 => "aws:amz:{$region}:s3",
            CURLOPT_USERPWD => $values['AWS_ACCESS_KEY_ID'].':'.$values['AWS_SECRET_ACCESS_KEY'],
        ]])->head($url);

        return $response->successful() ? 'S3 permitió acceder al bucket.' : null;
    }

    private function sii(array $values): bool
    {
        foreach ($values as $url) {
            $parts = parse_url((string) $url);
            if (($parts['scheme'] ?? null) !== 'https'
                || ! str_ends_with(strtolower($parts['host'] ?? ''), '.sii.cl')
                || isset($parts['user']) || ($parts['port'] ?? 443) !== 443) {
                return false;
            }
        }

        foreach ($values as $key => $url) {
            $upload = str_contains($key, 'ENVIO_DTE');
            $response = $upload ? $this->http()->head($url) : $this->http()->get($url, 'WSDL');

            if (! $response->successful() && ! ($upload && in_array($response->status(), [401, 403, 405], true))) {
                return false;
            }
        }

        return true;
    }

    private function http(): PendingRequest
    {
        return Http::connectTimeout(2)->timeout(4)->withoutRedirecting();
    }
}

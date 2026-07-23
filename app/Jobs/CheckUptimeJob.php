<?php

namespace App\Jobs;

use App\Mail\SlowResponseMail;
use App\Mail\UptimeDownMail;
use App\Mail\UptimeRecoveryMail;
use App\Models\Incident;
use App\Models\MonitoredSite;
use App\Models\UptimeAlert;
use App\Models\UptimeCheck;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckUptimeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MonitoredSite $site
    ) {
        $this->onQueue('uptime');
    }

    public function uniqueId(): string
    {
        return 'uptime-'.$this->site->id;
    }

    public function uniqueFor(): int
    {
        return 60;
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        $status = 'up';
        $responseTime = 0;
        $httpStatusCode = null;
        $errorMessage = null;
        $dnsTime = null;
        $connectTime = null;
        $sslExpiryDays = null;
        $responseBody = null;

        try {
            $url = $this->site->url;
            $timeout = $this->site->timeout ?? 30;

            $requestStart = microtime(true);
            $response = Http::timeout($timeout)
                ->withOptions([
                    'verify' => true,
                    'http_errors' => false,
                ])
                ->retry(2, 100)
                ->{$this->site->method}($url);
            $requestEnd = microtime(true);

            $responseTime = (int) (($requestEnd - $requestStart) * 1000);
            $httpStatusCode = $response->status();

            if ($this->site->expected_status && (string) $httpStatusCode !== (string) $this->site->expected_status) {
                $status = 'down';
                $errorMessage = "HTTP {$httpStatusCode} - Expected {$this->site->expected_status}";
            }

            if ($this->site->expected_string && ! str_contains($response->body(), $this->site->expected_string)) {
                $status = 'down';
                $errorMessage = 'Expected string not found in response';
            }

            if ($responseTime > $this->site->response_time_threshold) {
                $this->handleSlowResponse($responseTime);
            }

            $this->checkSSL($url);

        } catch (\Exception $e) {
            $status = 'down';
            $errorMessage = $e->getMessage();
            Log::error("Uptime check failed for {$this->site->url}: ".$e->getMessage());
        }

        UptimeCheck::updateOrCreate(
            [
                'monitored_site_id' => $this->site->id,
                'checked_at' => now()->startOfMinute(),
            ],
            [
                'monitored_site_id' => $this->site->id,
                'checked_at' => now(),
                'status' => $status,
                'response_time_ms' => $responseTime,
                'http_status_code' => $httpStatusCode,
                'response_body' => $responseBody,
                'error_message' => $errorMessage,
                'dns_time_ms' => $dnsTime,
                'connect_time_ms' => $connectTime,
                'ssl_expiry_days' => $sslExpiryDays,
                'owner_id' => $this->site->owner_id,
            ]
        );

        $this->site->update([
            'last_check_at' => now(),
            'last_status' => $status,
            'last_response_time' => $responseTime,
        ]);

        if ($status === 'down') {
            $this->handleSiteDown();
        } else {
            $this->handleSiteRecovery();
        }
    }

    protected function handleSiteDown(): void
    {
        $this->site->update(['last_down_at' => now()]);

        $openIncident = Incident::where('monitored_site_id', $this->site->id)
            ->where('status', 'down')
            ->whereNull('resolved_at')
            ->first();

        if (! $openIncident) {
            Incident::create([
                'monitored_site_id' => $this->site->id,
                'started_at' => now(),
                'status' => 'down',
                'cause' => 'Site unreachable',
                'notified' => false,
            ]);
        }

        $this->sendDownAlert();
    }

    protected function handleSiteRecovery(): void
    {
        $previousStatus = $this->site->getOriginal('last_status');

        if ($previousStatus === 'down') {
            $this->site->update([
                'last_recovered_at' => now(),
            ]);

            $incident = Incident::where('monitored_site_id', $this->site->id)
                ->where('status', 'down')
                ->whereNull('resolved_at')
                ->first();

            if ($incident) {
                $incident->update([
                    'resolved_at' => now(),
                    'status' => 'resolved',
                ]);
            }

            $this->sendRecoveryAlert();
        }
    }

    protected function handleSlowResponse(int $responseTime): void
    {
        $alerts = UptimeAlert::where('type', 'slow_response')
            ->active()
            ->forEmail()
            ->where(function ($q) {
                $q->whereNull('monitored_site_id')
                    ->orWhere('monitored_site_id', $this->site->id);
            })
            ->where('user_id', $this->site->user_id)
            ->get();

        foreach ($alerts as $alert) {
            Mail::to($alert->user->email)->queue(new SlowResponseMail(
                $this->site,
                $responseTime,
                $this->site->response_time_threshold
            ));
        }
    }

    protected function checkSSL(string $url): void
    {
        if (! $this->site->ssl_expiry_check) {
            return;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? null;

        if (! $host) {
            return;
        }

        $result = @get_headers($url, 1);
        if ($result && isset($result['SSL'])) {
            $context = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
            $client = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
            if ($client) {
                $cert = openssl_x509_parse(stream_context_get_options($context)['ssl']['peer_certificate'] ?? null);
                if ($cert && isset($cert['validTo_time_t'])) {
                    $expiryDate = Carbon::createFromTimestamp($cert['validTo_time_t']);
                    $daysUntilExpiry = now()->diffInDays($expiryDate);
                    if ($daysUntilExpiry <= $this->site->ssl_days_before_expiry) {
                        Log::warning("SSL certificate for {$host} expires in {$daysUntilExpiry} days");
                    }
                }
            }
        }
    }

    protected function sendDownAlert(): void
    {
        $alerts = UptimeAlert::where('type', 'site_down')
            ->active()
            ->forEmail()
            ->where(function ($q) {
                $q->whereNull('monitored_site_id')
                    ->orWhere('monitored_site_id', $this->site->id);
            })
            ->where('user_id', $this->site->user_id)
            ->get();

        foreach ($alerts as $alert) {
            Mail::to($alert->user->email)->queue(new UptimeDownMail($this->site));
        }
    }

    protected function sendRecoveryAlert(): void
    {
        $alerts = UptimeAlert::where('type', 'site_recovery')
            ->active()
            ->forEmail()
            ->where(function ($q) {
                $q->whereNull('monitored_site_id')
                    ->orWhere('monitored_site_id', $this->site->id);
            })
            ->where('user_id', $this->site->user_id)
            ->get();

        foreach ($alerts as $alert) {
            Mail::to($alert->user->email)->queue(new UptimeRecoveryMail($this->site));
        }
    }
}

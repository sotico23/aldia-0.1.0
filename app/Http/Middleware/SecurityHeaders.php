<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));

        view()->share('cspNonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $reverbHost = config('reverb.public.host') ?: $host;
        $reverbPort = (int) config('reverb.public.port', 443);
        $reverbScheme = config('reverb.public.scheme', 'https');
        $wsScheme = $reverbScheme === 'https' ? 'wss' : 'ws';
        $defaultPort = $reverbScheme === 'https' ? 443 : 80;
        $reverbPortSuffix = $reverbPort === $defaultPort ? '' : ':'.$reverbPort;
        $reverbOrigins = "{$reverbScheme}://{$reverbHost}{$reverbPortSuffix} {$wsScheme}://{$reverbHost}{$reverbPortSuffix}";

        if (app()->environment('production') && ! Vite::isRunningHot()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://telegram.org https://oauth.telegram.org; style-src 'self' 'nonce-{$nonce}' https://fonts.bunny.net; img-src 'self' data: https:; font-src 'self' https://fonts.bunny.net; connect-src 'self' {$reverbOrigins} https://api.telegram.org https://graph.facebook.com; frame-src https://telegram.org https://oauth.telegram.org; frame-ancestors 'none'; report-uri /api/v1/csp-report; report-to csp-endpoint;");
            $response->headers->set('Report-To', json_encode([
                'group' => 'csp-endpoint',
                'max_age' => 10886400,
                'endpoints' => [['url' => '/api/v1/csp-report']],
            ]));
        } else {
            $viteHosts = collect([$host, '127.0.0.1', '0.0.0.0'])->unique();
            $viteOrigins = $viteHosts->flatMap(fn ($h) => ["http://{$h}:5173", "https://{$h}:5173"])->unique()->implode(' ');
            $wsOrigins = $viteHosts->flatMap(fn ($h) => ["ws://{$h}:5173", "wss://{$h}:5173"])->unique()->implode(' ');

            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' {$viteOrigins} https://telegram.org https://oauth.telegram.org; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; img-src 'self' data: https:; font-src 'self' https://fonts.bunny.net; connect-src 'self' {$viteOrigins} {$wsOrigins} {$reverbOrigins} https://api.telegram.org https://graph.facebook.com; frame-src https://telegram.org https://oauth.telegram.org; frame-ancestors 'none';");
        }

        return $response;
    }
}

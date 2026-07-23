<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeolocationService
{
    /**
     * Resolve the country code from the given IP address.
     * Returns null if detection fails.
     */
    public function resolveCountryFromIp(?string $ip): ?string
    {
        if (! config('countries.geolocation.enabled', true)) {
            return null;
        }

        if ($ip === null || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }

        $cacheKey = "geo:country:{$ip}";
        $ttl = config('countries.geolocation.cache_ttl', 86400);

        return Cache::remember($cacheKey, $ttl, function () use ($ip) {
            return $this->fetchCountryFromApi($ip);
        });
    }

    /**
     * Fetch country code from the geolocation API.
     */
    protected function fetchCountryFromApi(string $ip): ?string
    {
        try {
            $apiUrl = str_replace('{ip}', $ip, config('countries.geolocation.api_url'));
            $timeout = config('countries.geolocation.timeout', 2);

            $response = Http::timeout($timeout)->get($apiUrl);

            if ($response->failed()) {
                Log::warning('Geolocation API request failed', [
                    'ip' => $ip,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            $countryCode = $data['countryCode'] ?? null;

            if ($countryCode !== null) {
                $countryCode = strtoupper($countryCode);
            }

            return Country::where('code', $countryCode)->where('is_active', true)->exists() ? $countryCode : null;
        } catch (\Exception $e) {
            Log::warning('Geolocation API error', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

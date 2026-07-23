<?php

namespace App\Http\Middleware;

use App\Models\Country;
use App\Services\GeolocationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleRegionalSettings
{
    public function __construct(
        protected GeolocationService $geolocation,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Resolve the user's country and attach regional settings to the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $countryCode = $this->resolveCountry($request);
            $country = Country::findByCode($countryCode) ?? Country::getDefault();

            if ($country) {
                $request->merge(['_country_settings' => $country->toArray()]);
                $request->attributes->set('country_settings', $country);
            }
        } catch (\Exception $e) {
            // Graceful fallback if countries table doesn't exist or DB is unreachable
            // Allows the request to continue without regional settings
            report($e);
        }

        return $next($request);
    }

    /**
     * Resolve the country code using the following priority:
     * 1. Authenticated user's country
     * 2. IP geolocation (if enabled)
     * 3. Default from config (CL)
     */
    protected function resolveCountry(Request $request): ?string
    {
        $user = $request->user();

        if ($user && $user->country) {
            return $user->country;
        }

        if (config('countries.geolocation.enabled', true)) {
            $detected = $this->geolocation->resolveCountryFromIp($request->ip());
            if ($detected !== null) {
                // Auto-assign detected country to authenticated user
                if ($user && empty($user->country)) {
                    $user->update(['country' => $detected]);
                }

                return $detected;
            }
        }

        return config('countries.default', 'CL');
    }
}

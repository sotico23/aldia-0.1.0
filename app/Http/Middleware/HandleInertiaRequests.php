<?php

namespace App\Http\Middleware;

use App\Models\Country;
use App\Models\Mensaje;
use App\Models\MensajeConversacion;
use App\Models\Message;
use App\Models\Pedido;
use App\Models\WebSetting;
use App\Scopes\OwnerScope;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $webSettings = $this->getCachedWebSettings();
        $user = $request->user();
        $authData = null;
        $countrySettings = $this->getCountrySettings($request);

        if ($user) {
            $unreadMessages = $this->getCachedUnreadMessages($user->id);
            $pendingOrders = $this->getCachedPendingOrders($user->id);
            $isUsuario = $user->hasRole('Usuario');

            $authData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'country' => $user->country,
                'telefono' => $user->telefono,
                'direccion' => $user->direccion,
                'profile_photo_url' => $user->profilePhotoUrl(),
                'cover_photo_url' => $user->coverPhotoUrl(),
                'business_logo_url' => $user->businessLogoUrl(),
                'public_profile' => $user->publicProfile,
                'show_onboarding' => $user->show_onboarding,
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),

                'unread_messages' => $unreadMessages,
                'pending_orders' => $pendingOrders,
                'unread_notifications' => $user->unreadNotifications->count(),
                'recent_notifications' => $user->notifications()->latest()->limit(10)->get(),

                'trial_ends_at' => $isUsuario ? $user->trial_ends_at?->toDateString() : null,
                'trial_starts_at' => $isUsuario ? $user->trial_starts_at?->toDateString() : null,
                'trial_days_remaining' => $isUsuario ? $user->trialDaysRemaining() : 0,
                'is_trial_active' => $isUsuario && $user->isTrialActive(),
                'is_trial_expired' => $isUsuario && $user->isTrialExpired(),
            ];
        }

        if (isset($user) && ($user->hasRole('Master') || $user->hasRole('Super Admin'))) {
            $authData['permissions'] = ['*'];
        }

        return [
            ...parent::share($request),
            'name' => $webSettings['app_name'] ?? config('app.name', 'Al Dia'),
            'web_settings' => $webSettings,
            'auth' => ['user' => $authData],
            'country_settings' => $countrySettings,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'ultima_venta_id' => $request->session()->get('ultima_venta_id'),
                'cupon_aplicado' => $request->session()->get('cupon_aplicado'),
            ],
            'sidebarOpen' => $this->getSidebarState($request),
        ];
    }

    protected function getCountrySettings(Request $request): array
    {
        try {
            $country = $request->attributes->get('country_settings');

            if ($country === null) {
                // Fallback if HandleRegionalSettings middleware didn't run
                $country = Country::getDefault();
            }

            if ($country) {
                return [
                    'code' => $country->code,
                    'name' => $country->name,
                    'currency' => [
                        'code' => $country->currency_code,
                        'symbol' => $country->currency_symbol,
                        'decimals' => $country->currency_decimals,
                        'locale' => $country->locale,
                    ],
                    'timezone' => $country->timezone,
                    'locale' => $country->locale,
                    'tax' => [
                        'name' => $country->tax_name,
                        'rate' => (float) $country->tax_rate,
                    ],
                    'fiscal_id' => [
                        'label' => $country->fiscal_id_label,
                        'pattern' => $country->fiscal_id_pattern,
                    ],
                    'date_format' => $country->date_format,
                    'phone_code' => $country->phone_code,
                ];
            }
        } catch (\Exception $e) {
            // Fallback if countries table doesn't exist
        }

        return $this->getDefaultCountrySettings();
    }

    private function getDefaultCountrySettings(): array
    {
        $default = config('countries.supported.CL', []);

        return [
            'code' => 'CL',
            'name' => 'Chile',
            'currency' => [
                'code' => $default['currency_code'] ?? 'CLP',
                'symbol' => $default['currency_symbol'] ?? '$',
                'decimals' => 0,
                'locale' => $default['locale'] ?? 'es-CL',
            ],
            'timezone' => $default['timezone'] ?? 'America/Santiago',
            'locale' => $default['locale'] ?? 'es-CL',
            'tax' => [
                'name' => $default['tax_name'] ?? 'IVA',
                'rate' => $default['tax_rate'] ?? 19.0,
            ],
            'fiscal_id' => [
                'label' => $default['fiscal_id_label'] ?? 'RUT',
                'pattern' => null,
            ],
            'date_format' => 'd/m/Y',
            'phone_code' => '+56',
        ];
    }

    protected function getCachedWebSettings(): array
    {
        $cacheKey = 'web_settings:shared';

        try {
            return cache()->remember($cacheKey, 300, function () {
                $settings = WebSetting::getSettings();

                return [
                    'app_name' => $settings->app_name ?? config('app.name', 'Al Dia'),
                    'app_logo' => $settings->app_logo ?? '',
                    'app_favicon' => $settings->app_favicon ?? '',
                    'app_title' => $settings->app_title ?? '',
                    'app_description' => $settings->app_description ?? '',
                    'app_keywords' => $settings->app_keywords ?? '',
                    'app_author' => $settings->app_author ?? '',
                ];
            });
        } catch (\Exception $e) {
            // Fallback if web_settings table doesn't exist
            return [
                'app_name' => config('app.name', 'Al Dia'),
                'app_logo' => '',
                'app_favicon' => '',
                'app_title' => '',
                'app_description' => '',
                'app_keywords' => '',
                'app_author' => '',
            ];
        }
    }

    protected function getCachedUnreadMessages(int $userId): int
    {
        $cacheKey = "user:{$userId}:unread_messages";

        try {
            return cache()->remember($cacheKey, 60, function () use ($userId) {
                $directMessages = Mensaje::where('receiver_id', $userId)
                    ->where('leido', false)
                    ->count();

                $conversationMessages = MensajeConversacion::where('sender_id', '!=', $userId)
                    ->where('receiver_id', $userId)
                    ->where('leido', false)
                    ->whereHas('conversacion', function ($q) use ($userId) {
                        $q->where('comprador_id', $userId)
                            ->orWhere('vendedor_id', $userId);
                    })->count();

                $marketplaceMessages = Message::where('sender_id', '!=', $userId)
                    ->whereNull('read_at')
                    ->whereHas('conversation', function ($q) use ($userId) {
                        $q->where('buyer_id', $userId)
                            ->orWhereHas('store', function ($q2) use ($userId) {
                                $q2->withoutGlobalScope(OwnerScope::class)
                                    ->where('user_id', $userId);
                            });
                    })->count();

                return $directMessages + $conversationMessages + $marketplaceMessages;
            });
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getCachedPendingOrders(int $userId): int
    {
        $cacheKey = "user:{$userId}:pending_orders";

        try {
            return cache()->remember($cacheKey, 60, function () use ($userId) {
                return Pedido::where('user_id', $userId)
                    ->where('estado', 'pendiente')
                    ->count();
            });
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getSidebarState(Request $request): bool
    {
        return ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true';
    }
}

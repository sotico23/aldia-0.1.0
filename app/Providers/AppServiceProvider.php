<?php

namespace App\Providers;

use App\Events\MailConfigErrorOccurred;
use App\Events\PaymentSuccessful;
use App\Events\PedidoCreado;
use App\Listeners\LogMailConfigError;
use App\Listeners\SendPaymentSuccessfulNotification;
use App\Listeners\SendPedidoCreadoBuyerNotification;
use App\Models\Conversacion;
use App\Models\Conversation;
use App\Models\Mensaje;
use App\Models\MensajeConversacion;
use App\Models\Message;
use App\Models\Pedido;
use App\Models\WebSetting;
use App\Observers\ConversacionObserver;
use App\Observers\ConversationObserver;
use App\Observers\MensajeConversacionObserver;
use App\Observers\MensajeObserver;
use App\Observers\MessageObserver;
use App\Policies\PedidoRecibidoPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        $this->configureRateLimiting();

        $this->registerObservers();

        $this->registerEventListeners();

        Event::listen(function (Login $event) {
            $hash = hash('sha256', $event->user->email);
            Log::info("AUDIT - El usuario {$hash} ha iniciado sesión.");
        });

        Event::listen(function (Logout $event) {
            $hash = $event->user ? hash('sha256', $event->user->email) : 'Desconocido';
            Log::info("AUDIT - El usuario {$hash} ha cerrado sesión.");
        });

        Event::listen(function (Registered $event) {
            $hash = hash('sha256', $event->user->email);
            Log::info("AUDIT - Nuevo usuario registrado: {$hash}.");
        });

        // Override OAuth config from database settings (if available)
        $this->overrideOAuthConfig();

        // Super Admin and Master bypass all permission checks
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
                return true;
            }
        });

        // Pulse authorization: Master and Super Admin only
        Gate::define('viewPulse', function ($user) {
            return $user->hasRole('Master') || $user->hasRole('Super Admin');
        });

        // Policy registration for non-standard naming
        Gate::policy(Pedido::class, PedidoRecibidoPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Configure rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('imports', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('messages', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cupon-validate', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }

    protected function overrideOAuthConfig(): void
    {
        try {
            $settings = WebSetting::getSettings();

            if ($settings && $settings->google_client_id) {
                config([
                    'services.google.client_id' => $settings->google_client_id,
                    'services.google.client_secret' => $settings->google_client_secret,
                    'services.google.redirect' => $settings->google_redirect_uri ?? config('services.google.redirect'),
                ]);
            }

            if ($settings && $settings->facebook_client_id) {
                config([
                    'services.facebook.client_id' => $settings->facebook_client_id,
                    'services.facebook.client_secret' => $settings->facebook_client_secret,
                    'services.facebook.redirect' => $settings->facebook_redirect_uri ?? config('services.facebook.redirect'),
                ]);
            }
        } catch (\Throwable $e) {
            // Table might not exist yet during migrations
        }
    }

    protected function registerObservers(): void
    {
        Conversacion::observe(ConversacionObserver::class);
        Conversation::observe(ConversationObserver::class);
        MensajeConversacion::observe(MensajeConversacionObserver::class);
        Message::observe(MessageObserver::class);
        Mensaje::observe(MensajeObserver::class);
    }

    protected function registerEventListeners(): void
    {
        Event::listen(
            PedidoCreado::class,
            SendPedidoCreadoBuyerNotification::class,
        );

        Event::listen(
            PaymentSuccessful::class,
            SendPaymentSuccessfulNotification::class,
        );

        Event::listen(
            MailConfigErrorOccurred::class,
            LogMailConfigError::class,
        );
    }
}

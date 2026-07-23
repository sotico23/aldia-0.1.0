<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Models\Country;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RoutePath;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->registerRegistrationRouteWithThrottle();
        $this->registerPasswordResetRoutesWithThrottle();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'countries' => Country::getActive()->map(fn (Country $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'currency_code' => $c->currency_code,
                'currency_symbol' => $c->currency_symbol,
                'phone_code' => $c->phone_code,
            ])->values(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username()).'|'.$request->ip()));

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username()).'|'.$request->ip()));

            return Limit::perMinute(3)->by($throttleKey);
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }

    /**
     * Register the registration route with throttle middleware.
     * Fortify registers the route without throttle by default.
     */
    private function registerRegistrationRouteWithThrottle(): void
    {
        if (! Features::enabled(Features::registration())) {
            return;
        }

        $limiter = config('fortify.limiters.registration');

        if (! $limiter) {
            return;
        }

        // Re-register the POST register route with throttle middleware
        $this->removeRoute('register.store');

        Route::post(RoutePath::for('register', '/register'), '\Laravel\Fortify\Http\Controllers\RegisteredUserController@store')
            ->middleware(array_filter([
                'guest:'.config('fortify.guard'),
                'throttle:'.$limiter,
            ]))
            ->name('register.store');
    }

    /**
     * Register password reset routes with throttle middleware.
     * Fortify registers these routes without throttle by default.
     */
    private function registerPasswordResetRoutesWithThrottle(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            return;
        }

        $limiter = config('fortify.limiters.password-reset');

        if (! $limiter) {
            return;
        }

        // Re-register the password reset routes with throttle middleware
        $this->removeRoute('password.email');
        $this->removeRoute('password.update');

        Route::post(RoutePath::for('password.email', '/forgot-password'),
            '\Laravel\Fortify\Http\Controllers\PasswordResetLinkController@store')
            ->middleware(array_filter([
                'guest:'.config('fortify.guard'),
                'throttle:'.$limiter,
            ]))
            ->name('password.email');

        Route::post(RoutePath::for('password.update', '/reset-password'),
            '\Laravel\Fortify\Http\Controllers\NewPasswordController@store')
            ->middleware(array_filter([
                'guest:'.config('fortify.guard'),
                'throttle:'.$limiter,
            ]))
            ->name('password.update');
    }

    /**
     * Remove a route by name.
     */
    private function removeRoute(string $name): void
    {
        $route = Route::getRoutes()->getByName($name);
        if ($route) {
            Route::getRoutes()->remove($route);
        }
    }
}

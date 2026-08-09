<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\TelegramAuthException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramAuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;

class TelegramAuthController extends Controller
{
    public function __construct(private readonly TelegramAuthService $telegramAuthService) {}

    /**
     * Renders the Telegram Login Widget, or processes the widget callback
     * when Telegram redirected the browser with its signed payload.
     */
    public function redirect(Request $request): RedirectResponse|string
    {
        if ($request->has('hash')) {
            return $this->callback($request);
        }

        try {
            return Socialite::driver('telegram')->redirect();
        } catch (\Throwable $e) {
            Log::error('Telegram login widget falló', ['message' => $e->getMessage(), 'class' => get_class($e)]);

            return redirect()
                ->route('login')
                ->with('error', 'No se pudo iniciar sesión con Telegram. Inténtalo nuevamente.');
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $payload = $this->telegramAuthService->verify($request);
        } catch (TelegramAuthException $e) {
            Log::warning('Telegram login rechazado', ['reason' => $e->getMessage()]);

            return redirect()
                ->route('login')
                ->with('error', 'No se pudo verificar tu cuenta de Telegram. Inténtalo nuevamente.');
        }

        $existing = User::where('telegram_id', $payload['id'])->first();

        if ($existing) {
            Auth::login($existing);

            return redirect()->intended(route('dashboard'));
        }

        if ($payload['email']) {
            $user = $this->findOrCreateUserWithEmail($payload);

            Auth::login($user);

            return redirect()->intended(route('dashboard'));
        }

        session()->put('telegram_pending', [
            'id' => $payload['id'],
            'name' => $payload['name'],
            'username' => $payload['username'],
            'avatar' => $payload['avatar'],
            'auth_date' => $payload['auth_date'],
        ]);

        return redirect()->route('telegram.email-form');
    }

    /**
     * Registered users complete their profile by providing their email.
     */
    protected function findOrCreateUserWithEmail(array $payload): User
    {
        $user = User::where('email', $payload['email'])->first();

        if (! $user) {
            $user = User::create([
                'name' => $payload['name'] ?? 'Usuario',
                'email' => $payload['email'],
                'password' => Hash::make(Str::random(32)),
                'profile_photo_path' => $payload['avatar'],
                'telegram_id' => $payload['id'],
                'telegram_username' => $payload['username'],
                'creator_id' => null,
                'email_verified_at' => now(),
            ]);

            event(new Registered($user));

            return $user;
        }

        $user->update([
            'telegram_id' => $payload['id'],
            'telegram_username' => $payload['username'],
        ]);

        return $user;
    }

    public function showTelegramEmailForm(): Response|RedirectResponse
    {
        if (! session()->has('telegram_pending')) {
            return redirect()
                ->route('login')
                ->with('error', 'Tu sesión de Telegram expiró. Vuelve a intentarlo.');
        }

        return Inertia::render('auth/telegram-email', [
            'pending' => [
                'name' => session()->get('telegram_pending.name'),
            ],
        ]);
    }

    public function completeTelegramEmail(Request $request): RedirectResponse
    {
        $pending = session()->get('telegram_pending');

        if (! $pending) {
            return redirect()
                ->route('login')
                ->with('error', 'Tu sesión de Telegram expiró. Vuelve a intentarlo.');
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $user->update([
                'telegram_id' => $pending['id'],
                'telegram_username' => $pending['username'],
            ]);
        } else {
            $user = User::create([
                'name' => $pending['name'] ?? 'Usuario',
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(32)),
                'profile_photo_path' => $pending['avatar'] ?? null,
                'telegram_id' => $pending['id'],
                'telegram_username' => $pending['username'],
                'creator_id' => null,
                'email_verified_at' => now(),
            ]);

            event(new Registered($user));
        }

        session()->forget('telegram_pending');

        Auth::login($user);

        return redirect()->intended(route('dashboard'));
    }
}

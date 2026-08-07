<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

class SocialiteController extends Controller
{
    public function redirect(string $provider): RedirectResponse|\Symfony\Component\HttpFoundation\Response|string
    {
        try {
            return Socialite::driver($provider)->redirect();
        } catch (\Throwable $e) {
            Log::error("Socialite redirect falló para {$provider}", ['message' => $e->getMessage(), 'class' => get_class($e)]);

            return redirect()
                ->route('login')
                ->with('error', 'No se pudo iniciar sesión con '.ucfirst($provider).'. Inténtalo nuevamente.');
        }
    }

    public function callback(string $provider): RedirectResponse
    {
        $socialiteUser = Socialite::driver($provider)->user();

        $email = $socialiteUser->getEmail();
        $name = $socialiteUser->getName() ?? $socialiteUser->getNickName();
        $avatar = $socialiteUser->getAvatar();

        if ($provider === 'telegram' && ! $email) {
            return $this->handleTelegramWithoutEmail($socialiteUser, $name, $avatar);
        }

        if (! $email) {
            return redirect()
                ->route('login')
                ->with('error', 'No se pudo obtener el correo electrónico de tu cuenta '.ucfirst($provider).'.');
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            if (! $user->password) {
                $user->update([
                    'password' => Hash::make(Str::random(32)),
                ]);
            }

            Auth::login($user);

            return redirect()->intended(route('dashboard'));
        }

        $user = User::create([
            'name' => $name ?? 'Usuario',
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'profile_photo_path' => $avatar,
            'creator_id' => null,
            'email_verified_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('dashboard'));
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

    /**
     * Telegram's login widget does not provide an email, so we either log in an
     * existing user linked to the Telegram account or ask the user for an email.
     */
    protected function handleTelegramWithoutEmail(
        \Laravel\Socialite\Contracts\User $socialiteUser,
        ?string $name,
        ?string $avatar
    ): RedirectResponse {
        $existing = User::where('telegram_id', (string) $socialiteUser->getId())->first();

        if ($existing) {
            Auth::login($existing);

            return redirect()->intended(route('dashboard'));
        }

        session()->put('telegram_pending', [
            'id' => (string) $socialiteUser->getId(),
            'name' => $name,
            'username' => $socialiteUser->getNickName(),
            'avatar' => $avatar,
        ]);

        return redirect()->route('socialite.telegram.email-form');
    }
}

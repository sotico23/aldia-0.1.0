<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialiteController extends Controller
{
    public function redirect(string $provider): RedirectResponse|Response|string
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
}

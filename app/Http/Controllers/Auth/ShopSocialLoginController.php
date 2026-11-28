<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ShopSocialLoginController extends Controller
{
    public function start(Request $request, string $provider): RedirectResponse|Response|string
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);

        $request->session()->put('shop_social_login', [
            'provider' => $provider,
            'challenge' => $data['challenge'],
            'telegram_state' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
        ]);

        if ($provider === 'telegram') {
            // The Telegram callback normally only accepts guests.
            if (Auth::check()) {
                Auth::logoutCurrentDevice();
                $request->session()->regenerate();
            }

            return redirect()->route('telegram.redirect');
        }

        return app(SocialiteController::class)->redirect($provider);
    }

    public function complete(Request $request, string $provider): ?RedirectResponse
    {
        $pending = $request->session()->get('shop_social_login');

        if (! $pending || $pending['provider'] !== $provider) {
            return null;
        }

        $request->session()->forget('shop_social_login');
        if ($pending['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        $code = bin2hex(random_bytes(32));
        $challenge = $pending['challenge'];
        $key = 'shop-login:'.hash('sha256', $code).':'.$challenge;
        Cache::put($key, Auth::id(), now()->addMinutes(2));

        return redirect()->away('cl.redcliente.shop://auth?'.http_build_query([
            'code' => $code,
            'state' => $challenge,
        ]), 302, ['Cache-Control' => 'no-store']);
    }
}

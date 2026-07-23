<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse|Response
    {
        $user = Auth::user();

        if ($user && $user->isProveedor()) {
            return redirect()->route('proveedor.dashboard');
        }

        if ($user && $user->isCliente()) {
            return redirect()->route('cliente.dashboard');
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended(Fortify::redirects('login'));
    }
}

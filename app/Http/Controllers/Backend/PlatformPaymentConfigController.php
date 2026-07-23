<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlatformPaymentConfigController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.configuracion.edit', only: ['update']),
        ];
    }

    public function index()
    {
        $config = PaymentConfig::where('owner_id', Auth::user()->getOwnerId())->first();

        return Inertia::render('Backend/Pagos/PlatformPaymentConfig', [
            'config' => $config,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'use_platform_config' => 'required|boolean',
        ]);

        $config = PaymentConfig::firstOrNew(['owner_id' => Auth::user()->getOwnerId()]);

        if (! $config->exists) {
            $config->commerce_code = 'PRESET';
            $config->api_key = 'PRESET';
        }

        $config->use_platform_config = $validated['use_platform_config'];
        $config->save();

        $status = $validated['use_platform_config']
            ? 'activada' : 'desactivada';

        return back()->with('success', "Plataforma de pago principal {$status} exitosamente.");
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use App\Services\WebpayService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WebpayConfigController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.configuracion.edit', only: ['edit', 'update']),
        ];
    }

    public function index()
    {
        $config = PaymentConfig::where('owner_id', Auth::id())->first();

        return Inertia::render('Backend/Pagos/WebpayConfig', [
            'config' => $config ? [
                'commerce_code' => $config->commerce_code,
                'api_key' => $config->api_key ? '••••••••••••••••' : null,
                'environment' => $config->environment,
                'is_active' => $config->is_active,
            ] : null,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'commerce_code' => 'required|string',
            'api_key' => 'nullable|string',
            'environment' => 'required|in:integration,production',
            'is_active' => 'boolean',
        ]);

        $config = PaymentConfig::firstOrNew(['owner_id' => Auth::id()]);
        $config->commerce_code = $validated['commerce_code'];
        $config->environment = $validated['environment'];
        $config->is_active = $validated['is_active'] ?? true;

        if (! empty($validated['api_key'])) {
            $config->api_key = $validated['api_key'];
        } elseif (! $config->exists) {
            return back()->withErrors(['api_key' => 'La API Key es requerida al crear la configuración por primera vez.']);
        }

        $config->save();

        return back()->with('success', 'Configuración de Webpay guardada exitosamente.');
    }

    // @deprecated - unused method
    public function return(Request $request, WebpayService $webpay)
    {
        $token = $request->input('token_ws');

        if (! $token) {
            return redirect()->route('webpay.error')->with('error', 'Transacción cancelada o sin token.');
        }

        try {
            // Se asume que el store/owner se obtiene del contexto u otra forma si es pago a terceros
            $response = $webpay->confirmTransaction($token, Auth::id());

            if (isset($response['status']) && $response['status'] === 'AUTHORIZED') {
                return redirect()->route('dashboard')->with('success', 'Pago realizado con éxito.');
            } else {
                return redirect()->route('webpay.error')->with('error', 'El pago fue rechazado.');
            }
        } catch (\Exception $e) {
            return redirect()->route('webpay.error')->with('error', 'Hubo un error al confirmar el pago: '.$e->getMessage());
        }
    }

    public function error()
    {
        return Inertia::render('Backend/Pagos/WebpayError');
    }
}

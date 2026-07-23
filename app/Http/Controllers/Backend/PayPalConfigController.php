<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class PayPalConfigController extends Controller implements HasMiddleware
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

        return Inertia::render('Backend/Pagos/PayPalConfig', [
            'config' => $config ? [
                'paypal_client_id' => $config->paypal_client_id,
                'paypal_client_secret' => $config->paypal_client_secret ? '••••••••••••••••' : null,
                'paypal_mode' => $config->paypal_mode,
                'paypal_active' => $config->paypal_active,
            ] : null,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'paypal_client_id' => 'required|string',
            'paypal_client_secret' => 'nullable|string',
            'paypal_mode' => 'required|in:sandbox,live',
            'paypal_active' => 'boolean',
        ]);

        $config = PaymentConfig::firstOrNew(['owner_id' => Auth::id()]);

        // Mantener campos existentes de Webpay si ya existen
        if (! $config->exists) {
            $config->commerce_code = 'PRESET'; // Placeholder para obligatorios de Webpay si la tabla los pide
            $config->api_key = 'PRESET';
        }

        $config->paypal_client_id = $validated['paypal_client_id'];
        $config->paypal_mode = $validated['paypal_mode'];
        $config->paypal_active = $validated['paypal_active'] ?? false;

        if (! empty($validated['paypal_client_secret'])) {
            $config->paypal_client_secret = $validated['paypal_client_secret'];
        } elseif (! $config->exists || empty($config->paypal_client_secret)) {
            return back()->withErrors(['paypal_client_secret' => 'El Client Secret es requerido al configurar PayPal.']);
        }

        $config->save();

        return back()->with('success', 'Configuración de PayPal guardada exitosamente.');
    }

    public function testConnection(Request $request)
    {
        $clientId = $request->input('paypal_client_id');
        $secret = $request->input('paypal_client_secret');
        $mode = $request->input('paypal_mode');

        if (empty($secret)) {
            $config = PaymentConfig::where('owner_id', Auth::id())->first();
            $secret = $config?->paypal_client_secret;
        }

        if (empty($clientId) || empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID y Secret son requeridos para la prueba.',
            ]);
        }

        $url = $mode === 'live'
            ? 'https://api-m.paypal.com/v1/oauth2/token'
            : 'https://api-m.sandbox.paypal.com/v1/oauth2/token';

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $secret)
                ->post($url, [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => '¡Conexión Exitosa! Las credenciales son válidas.',
                ]);
            }

            $error = $response->json('error_description') ?? 'Error de autenticación. Verifica tus credenciales.';

            return response()->json([
                'success' => false,
                'message' => "Error: {$error}",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: '.$e->getMessage(),
            ]);
        }
    }
}

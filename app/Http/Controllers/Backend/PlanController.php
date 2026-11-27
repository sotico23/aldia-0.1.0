<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\WebSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PlanController extends Controller
{
    public function index(): InertiaResponse
    {
        $settings = WebSetting::getSettings();

        return Inertia::render('Backend/Planes/Index', [
            'planes' => $this->planesDeConfiguracion($settings),
            'config' => [
                'subscriptions_active' => (bool) ($settings->subscriptions_active ?? false),
                'default_currency' => $settings->default_currency ?? 'CLP',
                'trial_days' => (int) ($settings->trial_days ?? 0),
                'grace_days' => (int) ($settings->grace_days ?? 0),
                'auto_renewal' => (bool) ($settings->auto_renewal ?? false),
            ],
            'gateway' => $this->resolveGateway(),
        ]);
    }

    /**
     * Alta de suscripción usando los planes publicados en el tag "planes" de
     * Configuración de Página de Inicio y las reglas de Pagos Globales.
     */
    public function suscribirse(Request $request): RedirectResponse
    {
        $settings = WebSetting::getSettings();
        $planes = $this->planesDeConfiguracion($settings);

        if (! (bool) ($settings->subscriptions_active ?? false)) {
            return back()->withErrors([
                'plan' => 'Las suscripciones no están activadas en Configuración de Pagos Globales.',
            ]);
        }

        $validated = $request->validate([
            'plan' => ['required', 'integer', 'min:0', 'max:'.max(0, count($planes) - 1)],
        ]);

        $plan = $planes[$validated['plan']];
        $nombre = trim((string) ($plan['nombre'] ?? ''));
        if (in_array(Str::lower($nombre), ['gratuito', 'corporativo'], true)) {
            return back()->withErrors([
                'plan' => 'Este plan no requiere suscripción desde el panel.',
            ]);
        }

        $precio = $this->parsePrecio($plan['precio'] ?? '0');
        $slug = Str::slug($nombre) ?: 'plan-personalizado';
        $planModel = Plan::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $nombre,
                'price' => $precio,
                'billing_cycle' => $this->cicloFacturacion($plan['periodo'] ?? ''),
                'features' => array_values((array) ($plan['caracteristicas'] ?? [])),
                'active' => true,
            ]
        );

        Subscription::where('business_id', auth()->id())
            ->whereIn('status', ['trial', 'active'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $gateway = $this->resolveGateway();
        $trialDays = (int) ($settings->trial_days ?? 0);

        $suscripcion = Subscription::create([
            'business_id' => auth()->id(),
            'plan_id' => $planModel->id,
            'gateway' => $gateway,
            'gateway_subscription_id' => null,
            'status' => 'trial',
            'price' => $precio,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
            'metadata' => [
                'nombre_plan' => $nombre,
                'origen' => 'Configuración de Página de Inicio (planes)',
                'auto_renewal' => (bool) ($settings->auto_renewal ?? false),
                'currency' => $settings->default_currency ?? 'CLP',
            ],
        ]);
        $suscripcion->recordHistory('created', [
            'plan' => $nombre,
            'gateway' => $gateway,
            'precio' => $precio,
        ]);

        return back()->with(
            'success',
            "Suscripción al plan {$nombre} registrada con {$gateway}."
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function planesDeConfiguracion(WebSetting $settings): array
    {
        $planes = $settings->planes;

        return is_array($planes) ? array_values($planes) : [];
    }

    /** Pasarela configurada para el tenant (Pagos Globales / PaymentConfig). */
    private function resolveGateway(): string
    {
        $config = PaymentConfig::resolveForOwner(auth()->user()->getOwnerId());

        if ($config === null) {
            return 'manual';
        }
        if ($config->paypal_active) {
            return 'paypal';
        }
        if ($config->mercadopago_active) {
            return 'mercadopago';
        }
        if ($config->is_active) {
            return 'webpay';
        }

        return 'manual';
    }

    private function parsePrecio(mixed $precio): float
    {
        $limpio = preg_replace('/[^\d.,]/', '', (string) $precio) ?? '';
        $limpio = str_replace('.', '', $limpio);
        $limpio = str_replace(',', '.', $limpio);

        return (float) $limpio;
    }

    private function cicloFacturacion(string $periodo): string
    {
        $periodo = Str::lower($periodo);

        if (str_contains($periodo, 'año') || str_contains($periodo, 'ano') || str_contains($periodo, 'year')) {
            return 'yearly';
        }

        return 'monthly';
    }
}

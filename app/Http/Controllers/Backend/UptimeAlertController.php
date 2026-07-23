<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MonitoredSite;
use App\Models\UptimeAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UptimeAlertController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:uptime.alertas.create', only: ['create', 'store']),
            new Middleware('permission:uptime.alertas.edit', only: ['edit', 'update']),
            new Middleware('permission:uptime.alertas.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $userId = Auth::user()->getOwnerId();

        $alerts = UptimeAlert::where('user_id', $userId)
            ->with('monitoredSite')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'type' => $alert->type,
                    'email_enabled' => $alert->email_enabled,
                    'webhook_enabled' => $alert->webhook_enabled,
                    'webhook_url' => $alert->webhook_url,
                    'is_active' => $alert->is_active,
                    'site' => $alert->monitoredSite ? [
                        'id' => $alert->monitoredSite->id,
                        'name' => $alert->monitoredSite->name,
                    ] : null,
                ];
            });

        $sites = MonitoredSite::where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Backend/Uptime/Alerts', [
            'alerts' => $alerts,
            'sites' => $sites,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monitored_site_id' => 'nullable|exists:monitored_sites,id',
            'type' => 'required|in:site_down,site_recovery,slow_response',
            'email_enabled' => 'boolean',
            'webhook_enabled' => 'boolean',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = Auth::user()->getOwnerId();
        $validated['is_active'] = true;

        if (! empty($validated['webhook_secret'])) {
            $validated['webhook_secret'] = bcrypt($validated['webhook_secret']);
        }

        UptimeAlert::create($validated);

        return redirect()->route('uptime.alerts.index')->with('success', 'Alerta creada correctamente.');
    }

    public function update(Request $request, UptimeAlert $alert): RedirectResponse
    {
        if ($alert->user_id !== Auth::user()->getOwnerId()) {
            abort(403);
        }

        $validated = $request->validate([
            'monitored_site_id' => 'nullable|exists:monitored_sites,id',
            'type' => 'required|in:site_down,site_recovery,slow_response',
            'email_enabled' => 'boolean',
            'webhook_enabled' => 'boolean',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['webhook_secret'])) {
            $validated['webhook_secret'] = bcrypt($validated['webhook_secret']);
        } else {
            unset($validated['webhook_secret']);
        }

        $alert->update($validated);

        return redirect()->route('uptime.alerts.index')->with('success', 'Alerta actualizada correctamente.');
    }

    public function destroy(UptimeAlert $alert): RedirectResponse
    {
        if ($alert->user_id !== Auth::user()->getOwnerId()) {
            abort(403);
        }

        $alert->delete();

        return redirect()->route('uptime.alerts.index')->with('success', 'Alerta eliminada correctamente.');
    }
}

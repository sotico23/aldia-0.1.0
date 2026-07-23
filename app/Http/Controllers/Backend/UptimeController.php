<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Jobs\CheckUptimeJob;
use App\Models\MonitoredSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UptimeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:uptime.monitores.create', only: ['create', 'store']),
            new Middleware('permission:uptime.monitores.edit', only: ['edit', 'update']),
            new Middleware('permission:uptime.monitores.delete', only: ['destroy']),
            new Middleware('permission:uptime.monitores.export', only: ['exportCsv', 'exportExcel']),
        ];
    }

    public function index(): Response
    {
        $userId = Auth::user()->getOwnerId();

        $sites = MonitoredSite::where('user_id', $userId)
            ->withCount(['checks', 'incidents'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($site) {
                return [
                    'id' => $site->id,
                    'name' => $site->name,
                    'url' => $site->url,
                    'is_active' => $site->is_active,
                    'last_status' => $site->last_status,
                    'last_check_at' => $site->last_check_at,
                    'last_response_time' => $site->last_response_time,
                    'check_interval' => $site->check_interval,
                    'uptime_30d' => $site->getUptimePercentage(30),
                    'avg_response_time' => $site->getAverageResponseTime(7),
                    'incidents_count' => $site->incidents_count,
                ];
            });

        $stats = [
            'total_sites' => $sites->count(),
            'sites_up' => $sites->where('last_status', 'up')->count(),
            'sites_down' => $sites->where('last_status', 'down')->count(),
            'avg_uptime' => $sites->count() > 0 ? round($sites->avg('uptime_30d'), 2) : 100,
            'avg_response_time' => $sites->count() > 0 ? round($sites->avg('last_response_time')) : 0,
        ];

        return Inertia::render('Backend/Uptime/Index', [
            'sites' => $sites,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'method' => 'required|in:GET,POST,HEAD',
            'expected_status' => 'required|numeric|min:100|max:599',
            'expected_string' => 'nullable|string|max:255',
            'type' => 'required|in:http,tcp',
            'check_interval' => 'required|in:1,5,10,15,30,60',
            'timeout' => 'required|integer|min:5|max:120',
            'response_time_threshold' => 'required|integer|min:100|max:60000',
            'ssl_expiry_check' => 'boolean',
        ]);

        $validated['user_id'] = Auth::user()->getOwnerId();
        $validated['is_active'] = true;

        $site = MonitoredSite::create($validated);

        CheckUptimeJob::dispatch($site);

        return redirect()->route('uptime.index')->with('success', 'Sitio agregado correctamente.');
    }

    public function show(MonitoredSite $site): Response
    {
        $this->checkOwnership($site);

        $checks = $site->checks()
            ->select('checked_at', 'status', 'response_time_ms', 'http_status_code', 'error_message')
            ->orderBy('checked_at', 'desc')
            ->limit(100)
            ->get();

        $incidents = $site->incidents()
            ->orderBy('started_at', 'desc')
            ->limit(20)
            ->get();

        $uptimeData = $site->checks()
            ->selectRaw('DATE(checked_at) as date, status, AVG(response_time_ms) as avg_response')
            ->where('checked_at', '>=', now()->subDays(30))
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get();

        $stats = [
            'uptime_7d' => $site->getUptimePercentage(7),
            'uptime_30d' => $site->getUptimePercentage(30),
            'uptime_90d' => $site->getUptimePercentage(90),
            'avg_response_7d' => $site->getAverageResponseTime(7),
            'avg_response_30d' => $site->getAverageResponseTime(30),
            'total_incidents' => $site->incidents()->count(),
            'avg_incident_duration' => $site->incidents()->resolved()->avg(
                fn ($i) => $i->resolved_at && $i->started_at
                    ? $i->resolved_at->diffInMinutes($i->started_at)
                    : 0
            ),
        ];

        return Inertia::render('Backend/Uptime/Show', [
            'site' => $site,
            'checks' => $checks,
            'incidents' => $incidents,
            'uptimeData' => $uptimeData,
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, MonitoredSite $site): RedirectResponse
    {
        $this->checkOwnership($site);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'method' => 'required|in:GET,POST,HEAD',
            'expected_status' => 'required|numeric|min:100|max:599',
            'expected_string' => 'nullable|string|max:255',
            'type' => 'required|in:http,tcp',
            'check_interval' => 'required|in:1,5,10,15,30,60',
            'timeout' => 'required|integer|min:5|max:120',
            'response_time_threshold' => 'required|integer|min:100|max:60000',
            'ssl_expiry_check' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $site->update($validated);

        return redirect()->route('uptime.index')->with('success', 'Sitio actualizado correctamente.');
    }

    public function destroy(MonitoredSite $site): RedirectResponse
    {
        $this->checkOwnership($site);

        $site->delete();

        return redirect()->route('uptime.index')->with('success', 'Sitio eliminado correctamente.');
    }

    public function checkNow(MonitoredSite $site): RedirectResponse
    {
        $this->checkOwnership($site);

        CheckUptimeJob::dispatchSync($site);

        return redirect()->back()->with('success', 'Chequeo realizado.');
    }

    public function incidents(MonitoredSite $site): Response
    {
        $this->checkOwnership($site);

        $incidents = $site->incidents()
            ->orderBy('started_at', 'desc')
            ->paginate(20);

        return Inertia::render('Backend/Uptime/Incidents', [
            'site' => $site,
            'incidents' => $incidents,
        ]);
    }

    public function exportCsv(MonitoredSite $site, Request $request): StreamedResponse
    {
        $this->checkOwnership($site);

        $days = $request->input('days', 30);
        $startDate = now()->subDays($days);

        $checks = $site->checks()
            ->where('checked_at', '>=', $startDate)
            ->orderBy('checked_at', 'asc')
            ->get();

        $filename = Str::slug($site->name).'_uptime_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($checks) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Fecha', 'Hora', 'Estado', 'Tiempo Respuesta (ms)', 'Codigo HTTP', 'Error']);

            foreach ($checks as $check) {
                fputcsv($handle, [
                    $check->checked_at->format('Y-m-d'),
                    $check->checked_at->format('H:i:s'),
                    $check->status,
                    $check->response_time_ms,
                    $check->http_status_code ?? '-',
                    $check->error_message ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function checkOwnership(MonitoredSite $site): void
    {
        if ($site->user_id !== Auth::user()->getOwnerId()) {
            abort(403, 'No tienes permiso para acceder a este recurso.');
        }
    }
}

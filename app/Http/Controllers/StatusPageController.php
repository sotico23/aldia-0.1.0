<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\MonitoredSite;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatusPageController extends Controller
{
    public function index(Request $request): Response
    {
        $slug = $request->input('slug', 'default');

        $webSetting = WebSetting::getSettings();
        $customSlug = $webSetting->status_page_slug ?? 'default';

        if ($slug !== $customSlug && $slug !== 'default') {
            abort(404);
        }

        $sites = MonitoredSite::where('is_active', true)
            ->get()
            ->map(function ($site) {
                $latestCheck = $site->latestCheck();
                $ongoingIncident = $site->incidents()->ongoing()->first();

                return [
                    'id' => $site->id,
                    'name' => $site->name,
                    'description' => $site->name,
                    'status' => $site->last_status ?? 'unknown',
                    'last_check' => $site->last_check_at?->toIso8601String(),
                    'uptime' => $site->getUptimePercentage(30),
                    'response_time' => $site->last_response_time,
                    'incident' => $ongoingIncident ? [
                        'started_at' => $ongoingIncident->started_at->toIso8601String(),
                        'cause' => $ongoingIncident->cause,
                    ] : null,
                ];
            });

        $activeIncidents = Incident::whereHas('monitoredSite', function ($q) {
            $q->where('is_active', true);
        })
            ->ongoing()
            ->with('monitoredSite:id,name')
            ->orderBy('started_at', 'desc')
            ->get()
            ->map(function ($incident) {
                return [
                    'id' => $incident->id,
                    'site_name' => $incident->monitoredSite->name,
                    'started_at' => $incident->started_at->toIso8601String(),
                    'cause' => $incident->cause,
                ];
            });

        $statusSummary = match (true) {
            $sites->isEmpty() => 'unknown',
            $sites->every(fn ($s) => $s['status'] === 'up') => 'all_operational',
            $sites->contains(fn ($s) => $s['status'] === 'down') => 'partial_outage',
            default => 'degraded_performance',
        };

        return Inertia::render('StatusPage', [
            'sites' => $sites,
            'activeIncidents' => $activeIncidents,
            'statusSummary' => $statusSummary,
            'pageTitle' => $webSetting->app_name.' - Status',
        ]);
    }

    public function embed(Request $request): Response
    {
        $sites = MonitoredSite::where('is_active', true)
            ->get()
            ->map(fn ($site) => [
                'name' => $site->name,
                'status' => $site->last_status ?? 'unknown',
            ]);

        $statusSummary = $sites->every(fn ($s) => $s['status'] === 'up') ? 'all_operational' : 'issues_detected';

        return Inertia::render('StatusPageEmbed', [
            'sites' => $sites,
            'statusSummary' => $statusSummary,
        ]);
    }
}

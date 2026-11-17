<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceCommandRequest;
use App\Services\MaintenanceCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->is_active && ($request->user()->hasRole('Master') || $request->user()->hasRole('Super Admin')), 403);

        return Inertia::render('Backend/ConfiguracionWeb/Maintenance', [
            'commands' => collect(config('maintenance.commands'))->map(fn (array $command, string $key): array => [
                'key' => $key,
                'label' => $command['label'],
                'description' => $command['description'],
                'group' => $command['group'],
                'confirm' => $command['confirm'],
            ])->values(),
            'status' => [
                'routes_cached' => app()->routesAreCached(),
                'config_cached' => app()->configurationIsCached(),
                'road_alerts_available' => collect(Route::getRoutes())->contains(
                    fn (RegisteredRoute $route): bool => $route->uri() === 'api/v1/delivery/road-alerts' && in_array('POST', $route->methods())
                ),
            ],
        ]);
    }

    public function execute(MaintenanceCommandRequest $request, MaintenanceCommandService $service): JsonResponse
    {
        return response()->json($service->execute($request->validated('command'), (int) $request->user()->id));
    }
}

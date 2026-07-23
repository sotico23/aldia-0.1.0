<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ObservabilityService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    public function __construct(
        protected ObservabilityService $observability,
    ) {}

    public function health(): JsonResponse
    {
        return response()->json($this->observability->getHealthStatus());
    }

    public function dashboard(): Response
    {
        $health = $this->observability->getHealthStatus();

        return Inertia::render('Admin/ObservabilityDashboard', [
            'health' => $health,
            'metrics' => $health['metrics'],
            'lastExecutions' => $this->observability->getLastExecutionByWorkflow(),
            'queueWaitTime' => $this->observability->getQueueWaitTime(),
        ]);
    }
}

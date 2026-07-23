<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AutomationExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AutomationExecutionController extends Controller
{
    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $executions = AutomationExecution::where('owner_id', $ownerId)
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn (AutomationExecution $e) => [
                'id' => $e->id,
                'workflow' => $e->workflow,
                'status' => $e->status,
                'triggered_by' => $e->triggered_by,
                'error_message' => $e->error_message,
                'execution_time_ms' => $e->execution_time_ms,
                'executed_at' => $e->executed_at?->diffForHumans(),
                'created_at' => $e->created_at->toIso8601String(),
            ]);

        return Inertia::render('Backend/AutomationHistory', [
            'executions' => $executions,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $execution = AutomationExecution::where('owner_id', $ownerId)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'execution' => [
                'id' => $execution->id,
                'workflow' => $execution->workflow,
                'status' => $execution->status,
                'triggered_by' => $execution->triggered_by,
                'payload' => $execution->payload,
                'output' => $execution->output,
                'error_message' => $execution->error_message,
                'execution_time_ms' => $execution->execution_time_ms,
                'executed_at' => $execution->executed_at?->toIso8601String(),
            ],
        ]);
    }
}

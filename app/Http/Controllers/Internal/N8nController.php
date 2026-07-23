<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AutomationExecution;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\GastoProyecto;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Tesoreria;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class N8nController extends Controller
{
    public function summary(int $business): JsonResponse
    {
        try {
            $user = User::findOrFail($business);

            $now = Carbon::now();

            $salesToday = Venta::where('owner_id', $business)
                ->whereDate('created_at', $now->toDateString())
                ->count();

            $salesMonth = Venta::where('owner_id', $business)
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count();

            $inventoryTotal = Inventario::where('owner_id', $business)
                ->sum('cantidad');

            $inventoryLowStock = Inventario::where('owner_id', $business)
                ->whereColumn('cantidad', '<=', 'cantidad_minima')
                ->where('cantidad_minima', '>', 0)
                ->count();

            $appointmentsToday = Appointment::where('owner_id', $business)
                ->whereDate('start_time', $now->toDateString())
                ->count();

            $appointmentsPending = Appointment::where('owner_id', $business)
                ->where('start_time', '>=', $now)
                ->where('status', 'pendiente')
                ->count();

            $customersTotal = Cliente::where('owner_id', $business)->count();

            $customersNewMonth = Cliente::where('owner_id', $business)
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count();

            return response()->json([
                'success' => true,
                'business' => [
                    'id' => $user->id,
                    'name' => $user->business_name ?? $user->name,
                ],
                'summary' => [
                    'sales_today' => $salesToday,
                    'sales_month' => $salesMonth,
                    'inventory_total' => (float) $inventoryTotal,
                    'inventory_low_stock' => $inventoryLowStock,
                    'appointments_today' => $appointmentsToday,
                    'appointments_pending' => $appointmentsPending,
                    'customers_total' => $customersTotal,
                    'customers_new_month' => $customersNewMonth,
                ],
                'generated_at' => $now->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('N8nController::summary error', [
                'business_id' => $business,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al generar el resumen.',
            ], 500);
        }
    }

    public function inventory(int $business): JsonResponse
    {
        try {
            $user = User::findOrFail($business);

            $items = Inventario::where('owner_id', $business)
                ->with('producto')
                ->get()
                ->map(fn (Inventario $item) => [
                    'id' => $item->id,
                    'product_id' => $item->producto_id,
                    'product_name' => $item->producto?->nombre,
                    'quantity' => (float) $item->cantidad,
                    'min_stock' => (float) $item->cantidad_minima,
                    'is_low_stock' => $item->cantidad > 0 && $item->cantidad_minima > 0
                        && $item->cantidad <= $item->cantidad_minima,
                ]);

            return response()->json([
                'success' => true,
                'business_id' => $user->id,
                'items' => $items,
                'total_items' => $items->count(),
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('N8nController::inventory error', [
                'business_id' => $business,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener inventario.',
            ], 500);
        }
    }

    public function sales(int $business, Request $request): JsonResponse
    {
        try {
            $user = User::findOrFail($business);

            $limit = min((int) $request->input('limit', 50), 200);

            $sales = Venta::where('owner_id', $business)
                ->with('cliente:id,nombre')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn (Venta $sale) => [
                    'id' => $sale->id,
                    'number' => $sale->numero,
                    'customer' => $sale->cliente?->nombre,
                    'total' => (float) $sale->total,
                    'status' => $sale->estado,
                    'payment_method' => $sale->metodo_pago,
                    'date' => $sale->fecha?->toDateString(),
                    'created_at' => $sale->created_at->toIso8601String(),
                ]);

            return response()->json([
                'success' => true,
                'business_id' => $user->id,
                'sales' => $sales,
                'total_sales' => $sales->count(),
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('N8nController::sales error', [
                'business_id' => $business,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener ventas.',
            ], 500);
        }
    }

    public function appointments(int $business, Request $request): JsonResponse
    {
        try {
            $user = User::findOrFail($business);

            $limit = min((int) $request->input('limit', 50), 200);
            $status = $request->input('status');

            $query = Appointment::where('owner_id', $business)
                ->with(['client:id,name', 'provider:id,name', 'producto:id,nombre']);

            if ($status) {
                $query->where('status', $status);
            }

            $appointments = $query->orderBy('start_time', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'client' => $appointment->client?->name,
                    'provider' => $appointment->provider?->name,
                    'service' => $appointment->producto?->nombre,
                    'start_time' => $appointment->start_time->toIso8601String(),
                    'end_time' => $appointment->end_time?->toIso8601String(),
                    'status' => $appointment->status,
                    'payment_status' => $appointment->payment_status,
                    'notes' => $appointment->notes,
                ]);

            return response()->json([
                'success' => true,
                'business_id' => $user->id,
                'appointments' => $appointments,
                'total_appointments' => $appointments->count(),
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('N8nController::appointments error', [
                'business_id' => $business,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener citas.',
            ], 500);
        }
    }

    // ──────────────────────────────────────────────
    // FASE 4: Financial endpoints
    // ──────────────────────────────────────────────

    public function cashFlow(int $business, Request $request): JsonResponse
    {
        try {
            User::findOrFail($business);

            $limit = min((int) $request->input('limit', 50), 200);
            $tipo = $request->input('tipo');

            $query = Tesoreria::where('owner_id', $business);

            if ($tipo && in_array($tipo, ['ingreso', 'egreso'])) {
                $query->where('tipo', $tipo);
            }

            $items = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn (Tesoreria $t) => [
                    'id' => $t->id,
                    'type' => $t->tipo,
                    'amount' => (float) $t->monto,
                    'description' => $t->descripcion,
                    'category' => $t->categoria,
                    'reference' => $t->referencia,
                    'date' => $t->fecha?->toDateString(),
                    'status' => $t->estado,
                    'created_at' => $t->created_at->toIso8601String(),
                ]);

            $summary = [
                'total_income' => (float) Tesoreria::where('owner_id', $business)
                    ->where('tipo', 'ingreso')->sum('monto'),
                'total_expense' => (float) Tesoreria::where('owner_id', $business)
                    ->where('tipo', 'egreso')->sum('monto'),
            ];

            return response()->json([
                'success' => true,
                'business_id' => $business,
                'items' => $items,
                'summary' => $summary,
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (\Throwable $e) {
            Log::error('N8nController::cashFlow error', ['business_id' => $business, 'error' => $e->getMessage()]);

            return $this->errorResponse('Error interno al obtener flujo de caja.');
        }
    }

    public function accountsReceivable(int $business, Request $request): JsonResponse
    {
        try {
            User::findOrFail($business);

            $limit = min((int) $request->input('limit', 50), 200);
            $status = $request->input('status');

            $query = Cobranza::where('owner_id', $business);

            if ($status) {
                $query->where('estado', $status);
            }

            $items = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn (Cobranza $c) => [
                    'id' => $c->id,
                    'amount' => (float) $c->monto,
                    'payment_date' => $c->fecha_pago?->toDateString(),
                    'payment_method' => $c->metodo_pago,
                    'reference' => $c->referencia,
                    'status' => $c->estado,
                    'notes' => $c->notas,
                    'created_at' => $c->created_at->toIso8601String(),
                ]);

            $totalPending = (float) Cobranza::where('owner_id', $business)
                ->where('estado', 'pendiente')->sum('monto');

            return response()->json([
                'success' => true,
                'business_id' => $business,
                'items' => $items,
                'summary' => [
                    'total_pending' => $totalPending,
                    'total_items' => $items->count(),
                ],
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (\Throwable $e) {
            Log::error('N8nController::accountsReceivable error', ['business_id' => $business, 'error' => $e->getMessage()]);

            return $this->errorResponse('Error interno al obtener cuentas por cobrar.');
        }
    }

    public function accountsPayable(int $business, Request $request): JsonResponse
    {
        try {
            User::findOrFail($business);

            $limit = min((int) $request->input('limit', 50), 200);
            $status = $request->input('status');

            $query = Pago::where('owner_id', $business);

            if ($status) {
                $query->where('estado', $status);
            }

            $items = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn (Pago $p) => [
                    'id' => $p->id,
                    'amount' => (float) $p->monto,
                    'payment_date' => $p->fecha_pago?->toDateString(),
                    'payment_method' => $p->metodo_pago,
                    'reference' => $p->referencia,
                    'status' => $p->estado,
                    'notes' => $p->notas,
                    'created_at' => $p->created_at->toIso8601String(),
                ]);

            $totalPending = (float) Pago::where('owner_id', $business)
                ->where('estado', 'pendiente')->sum('monto');

            return response()->json([
                'success' => true,
                'business_id' => $business,
                'items' => $items,
                'summary' => [
                    'total_pending' => $totalPending,
                    'total_items' => $items->count(),
                ],
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (\Throwable $e) {
            Log::error('N8nController::accountsPayable error', ['business_id' => $business, 'error' => $e->getMessage()]);

            return $this->errorResponse('Error interno al obtener cuentas por pagar.');
        }
    }

    public function expenses(int $business, Request $request): JsonResponse
    {
        try {
            User::findOrFail($business);

            $limit = min((int) $request->input('limit', 50), 200);

            $projectExpenses = GastoProyecto::where('owner_id', $business)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn (GastoProyecto $g) => [
                    'id' => $g->id,
                    'type' => 'project',
                    'category' => $g->categoria,
                    'description' => $g->descripcion,
                    'amount' => (float) $g->monto,
                    'date' => $g->fecha?->toDateString(),
                    'reference' => $g->referencia,
                    'approved' => $g->aprobado,
                    'created_at' => $g->created_at->toIso8601String(),
                ]);

            $treasuryExpenses = Tesoreria::where('owner_id', $business)
                ->where('tipo', 'egreso')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn (Tesoreria $t) => [
                    'id' => $t->id,
                    'type' => 'treasury',
                    'category' => $t->categoria,
                    'description' => $t->descripcion,
                    'amount' => (float) $t->monto,
                    'date' => $t->fecha?->toDateString(),
                    'reference' => $t->referencia,
                    'approved' => null,
                    'created_at' => $t->created_at->toIso8601String(),
                ]);

            $allExpenses = collect($projectExpenses)->merge($treasuryExpenses)
                ->sortByDesc('created_at')->take($limit)->values();

            $totalProjectExpenses = (float) GastoProyecto::where('owner_id', $business)->sum('monto');
            $totalTreasuryExpenses = (float) Tesoreria::where('owner_id', $business)
                ->where('tipo', 'egreso')->sum('monto');

            return response()->json([
                'success' => true,
                'business_id' => $business,
                'items' => $allExpenses,
                'summary' => [
                    'total_project_expenses' => $totalProjectExpenses,
                    'total_treasury_expenses' => $totalTreasuryExpenses,
                    'total_expenses' => $totalProjectExpenses + $totalTreasuryExpenses,
                ],
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (\Throwable $e) {
            Log::error('N8nController::expenses error', ['business_id' => $business, 'error' => $e->getMessage()]);

            return $this->errorResponse('Error interno al obtener gastos.');
        }
    }

    // ──────────────────────────────────────────────
    // FASE 5: n8n workflow callback webhook
    // ──────────────────────────────────────────────

    public function workflowComplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => 'required|integer|exists:users,id',
            'workflow' => 'required|string|max:100',
            'status' => 'required|in:success,error,timeout',
            'triggered_by' => 'nullable|string|max:50',
            'output' => 'nullable|array',
            'payload' => 'nullable|array',
            'payload.*' => 'nullable|string|max:5000',
            'error_message' => 'nullable|string|max:2000',
            'execution_time_ms' => 'nullable|integer',
        ]);

        try {
            $execution = AutomationExecution::create([
                'owner_id' => $validated['business_id'],
                'workflow' => $validated['workflow'],
                'status' => $validated['status'],
                'triggered_by' => $validated['triggered_by'] ?? 'webhook',
                'payload' => $validated['payload'] ?? [],
                'output' => $validated['output'] ?? [],
                'error_message' => $validated['error_message'] ?? null,
                'execution_time_ms' => $validated['execution_time_ms'] ?? null,
                'executed_at' => now(),
            ]);

            Log::info('n8n workflow completed', [
                'execution_id' => $execution->id,
                'business_id' => $validated['business_id'],
                'workflow' => $validated['workflow'],
                'status' => $validated['status'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ejecución registrada correctamente.',
                'execution_id' => $execution->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('N8nController::workflowComplete error', [
                'business_id' => $validated['business_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la ejecución.',
            ], 500);
        }
    }

    // ──────────────────────────────────────────────
    // FASE 6: Execution history & logs
    // ──────────────────────────────────────────────

    public function executions(int $business, Request $request): JsonResponse
    {
        try {
            User::findOrFail($business);

            $limit = min((int) $request->input('limit', 20), 100);
            $workflow = $request->input('workflow');
            $status = $request->input('status');

            $query = AutomationExecution::where('owner_id', $business);

            if ($workflow) {
                $query->byWorkflow($workflow);
            }

            if ($status) {
                $query->byStatus($status);
            }

            $items = $query->recent()
                ->limit($limit)
                ->get()
                ->map(fn (AutomationExecution $e) => [
                    'id' => $e->id,
                    'workflow' => $e->workflow,
                    'status' => $e->status,
                    'triggered_by' => $e->triggered_by,
                    'error_message' => $e->error_message,
                    'execution_time_ms' => $e->execution_time_ms,
                    'executed_at' => $e->executed_at?->toIso8601String(),
                    'created_at' => $e->created_at->toIso8601String(),
                ]);

            return response()->json([
                'success' => true,
                'business_id' => $business,
                'executions' => $items,
                'total' => $items->count(),
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (\Throwable $e) {
            Log::error('N8nController::executions error', ['business_id' => $business, 'error' => $e->getMessage()]);

            return $this->errorResponse('Error interno al obtener historial de ejecuciones.');
        }
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Business not found',
        ], 404);
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 500);
    }
}

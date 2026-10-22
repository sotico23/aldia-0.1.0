<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Backend\GlobalSearchController;
use App\Services\AutomationReportService;
use App\Support\AgentContextBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransversalBotController extends BotBaseController
{
    public function contexto(Request $request): JsonResponse
    {
        $agentContext = $request->attributes->get('agent_context');
        $botContext = $request->attributes->get('bot_context');

        if (! $agentContext || ! $botContext) {
            return $this->error('Contexto no disponible.', [], 401);
        }

        $builder = new AgentContextBuilder;
        $data = $builder->build($agentContext);

        return $this->ok($data, 'Contexto del agente obtenido.');
    }

    public function resumen(Request $request): JsonResponse
    {
        $botContext = $request->attributes->get('bot_context');

        if (! $botContext) {
            return $this->error('Contexto no disponible.', [], 401);
        }

        $ownerId = $botContext->ownerId;
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $ventasHoy = DB::table('ventas')->where('owner_id', $ownerId)->whereDate('fecha', $today)->sum('total');
        $ventasMesCount = DB::table('ventas')->where('owner_id', $ownerId)->whereDate('fecha', '>=', $monthStart)->count();
        $ventasMesTotal = DB::table('ventas')->where('owner_id', $ownerId)->whereDate('fecha', '>=', $monthStart)->sum('total');
        $inventarioTotal = DB::table('inventarios')->where('owner_id', $ownerId)->sum('cantidad');
        $inventarioBajo = DB::table('inventarios')->where('owner_id', $ownerId)->whereColumn('cantidad', '<=', 'cantidad_minima')->count();
        $clientesTotal = DB::table('clientes')->where('owner_id', $ownerId)->count();
        $clientesNuevosMes = DB::table('clientes')->where('owner_id', $ownerId)->whereDate('created_at', '>=', $monthStart)->count();
        $citasHoy = DB::table('appointments')->where('owner_id', $ownerId)->whereDate('start_time', $today)->count();
        $citasPendientes = DB::table('appointments')->where('owner_id', $ownerId)->where('status', 'pendiente')->count();

        $summary = [
            'sales_today' => (float) $ventasHoy,
            'sales_month' => (int) $ventasMesCount,
            'sales_month_total' => (float) $ventasMesTotal,
            'inventory_total' => (float) $inventarioTotal,
            'inventory_low_stock' => (int) $inventarioBajo,
            'appointments_today' => (int) $citasHoy,
            'appointments_pending' => (int) $citasPendientes,
            'customers_total' => (int) $clientesTotal,
            'customers_new_month' => (int) $clientesNuevosMes,
            'cash_flow_income' => 0,
            'cash_flow_expense' => 0,
            'accounts_receivable' => 0,
            'accounts_payable' => 0,
            'expenses_month' => 0,
            'purchases_month' => 0,
        ];

        return $this->ok([
            'summary' => $summary,
            'generated_at' => now()->toIso8601String(),
        ], 'Resumen obtenido.');
    }

    public function reportes(Request $request, string $tipo): JsonResponse
    {
        $botContext = $request->attributes->get('bot_context');
        $ownerId = $botContext?->ownerId;

        if (! $ownerId) {
            return $this->error('Contexto no disponible.', [], 401);
        }

        $allowed = ['ventas', 'inventario', 'cobranzas', 'finanzas', 'clientes', 'compras'];
        if (! in_array($tipo, $allowed, true)) {
            return $this->error('Tipo de reporte no soportado.', ['tipo' => ['Tipos permitidos: '.implode(', ', $allowed)]], 422);
        }

        $reportsParam = $request->query('reports');
        $reports = is_string($reportsParam) && $reportsParam !== '' ? array_filter(explode(',', $reportsParam)) : [$tipo];
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        $service = app(AutomationReportService::class);
        $data = $service->generateReports($ownerId, $reports, $desde, $hasta);

        return $this->ok($data, 'Reporte generado.');
    }

    public function buscar(Request $request, GlobalSearchController $search): JsonResponse
    {
        $response = $search->search($request);

        return $response;
    }

    public function dashboardResumen(Request $request): JsonResponse
    {
        $botContext = $request->attributes->get('bot_context');
        $ownerId = $botContext?->ownerId;

        if (! $ownerId) {
            return $this->error('Contexto no disponible.', [], 401);
        }

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        return $this->ok([
            'ventas_hoy' => (float) DB::table('ventas')->where('owner_id', $ownerId)->whereDate('fecha', $today)->sum('total'),
            'ventas_mes_total' => (float) DB::table('ventas')->where('owner_id', $ownerId)->whereDate('fecha', '>=', $monthStart)->sum('total'),
            'ventas_mes_count' => (int) DB::table('ventas')->where('owner_id', $ownerId)->whereDate('fecha', '>=', $monthStart)->count(),
            'citas_hoy' => (int) DB::table('appointments')->where('owner_id', $ownerId)->whereDate('start_time', $today)->count(),
            'oportunidades_abiertas' => (int) DB::table('oportunidades')->where('owner_id', $ownerId)->whereNotIn('etapa', ['ganada', 'perdida'])->count(),
            'tickets_abiertos' => (int) DB::table('tickets')->where('owner_id', $ownerId)->where('estado', '!=', 'cerrado')->count(),
            'pedidos_pendientes' => (int) DB::table('pedidos')->where('owner_id', $ownerId)->where('estado', 'pendiente')->count(),
        ], 'Dashboard resumen obtenido.');
    }
}

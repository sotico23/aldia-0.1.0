<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\Compra;
use App\Models\GastoProyecto;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Tesoreria;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantDataController extends Controller
{
    public function resumenCompleto(Request $request): JsonResponse
    {
        /** @var User $tenant */
        $tenant = $request->get('tenant');
        $ownerId = $tenant->getOwnerId();

        $now = now();
        $startOfDay = $now->copy()->startOfDay();
        $startOfMonth = $now->copy()->startOfMonth();

        $salesToday = Venta::where('owner_id', $ownerId)
            ->where('estado', 'pagada')
            ->where('created_at', '>=', $startOfDay)
            ->count();

        $salesMonth = Venta::where('owner_id', $ownerId)
            ->where('estado', 'pagada')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $salesMonthTotal = Venta::where('owner_id', $ownerId)
            ->where('estado', 'pagada')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('total');

        $inventories = Inventario::where('owner_id', $ownerId)->with('producto')->get();
        $inventoryTotal = $inventories->sum(fn ($i) => ($i->cantidad ?? 0) * ($i->producto?->precio_compra ?? 0));
        $inventoryLowStock = $inventories->filter(fn ($i) => $i->cantidad <= ($i->cantidad_minima ?? 0))->count();

        $appointmentsToday = Appointment::where('owner_id', $ownerId)
            ->whereDate('start_time', $now->toDateString())
            ->count();

        $appointmentsPending = Appointment::where('owner_id', $ownerId)
            ->whereIn('status', ['pendiente', 'confirmada'])
            ->count();

        $customersTotal = Cliente::where('owner_id', $ownerId)->count();

        $customersNewMonth = Cliente::where('owner_id', $ownerId)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $tesoreria = Tesoreria::where('owner_id', $ownerId)->get();
        $cashFlowIncome = $tesoreria->where('tipo', 'ingreso')->sum('monto');
        $cashFlowExpense = $tesoreria->where('tipo', 'egreso')->sum('monto');

        $accountsReceivable = Cobranza::where('owner_id', $ownerId)
            ->where('estado', 'pendiente')
            ->sum('monto');

        $accountsPayable = Pago::where('owner_id', $ownerId)
            ->where('estado', 'pendiente')
            ->sum('monto');

        $expensesTotal = GastoProyecto::where('owner_id', $ownerId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('monto');

        $purchasesMonth = Compra::where('owner_id', $ownerId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        return response()->json([
            'success' => true,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'business_name' => $tenant->business_name ?? $tenant->name,
                'email' => $tenant->email,
            ],
            'summary' => [
                'sales_today' => $salesToday,
                'sales_month' => $salesMonth,
                'sales_month_total' => (float) $salesMonthTotal,
                'inventory_total' => (float) $inventoryTotal,
                'inventory_low_stock' => $inventoryLowStock,
                'appointments_today' => $appointmentsToday,
                'appointments_pending' => $appointmentsPending,
                'customers_total' => $customersTotal,
                'customers_new_month' => $customersNewMonth,
                'cash_flow_income' => (float) $cashFlowIncome,
                'cash_flow_expense' => (float) $cashFlowExpense,
                'accounts_receivable' => (float) $accountsReceivable,
                'accounts_payable' => (float) $accountsPayable,
                'expenses_month' => (float) $expensesTotal,
                'purchases_month' => $purchasesMonth,
            ],
            'generated_at' => $now->toIso8601String(),
        ]);
    }
}

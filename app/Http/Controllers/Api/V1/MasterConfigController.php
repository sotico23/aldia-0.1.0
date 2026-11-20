<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MasterAppConfig;
use App\Models\PedidoRecibido;
use App\Models\RoadReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MasterConfigController extends Controller
{
    /**
     * Retorna el panel de rendimiento y métricas del sistema (Solo Rol Master / Super Admin).
     *
     * GET /api/v1/master/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('Master') && ! $user->hasRole('Super Admin'))) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere rol Master para acceder al panel de administración global.',
            ], 403);
        }

        // Métricas de Repartidores
        $repartidoresQuery = User::where(function ($q) {
            $q->whereHas('roles', fn ($r) => $r->where('name', 'Repartidor'))
                ->orWhere('driver_type', 'freelancer');
        });

        $totalRepartidores = (clone $repartidoresQuery)->count();
        $repartidoresOnline = (clone $repartidoresQuery)->where('is_online', true)->count();
        $repartidoresPendientes = User::where('approval_status', 'pending')->count();
        $repartidoresAprobados = User::where('approval_status', 'approved')->count();

        // Métricas de Entregas y Pedidos
        $pedidosActivos = 0;
        $poolPedidos = 0;
        $totalEntregasHoy = 0;

        try {
            if (class_exists(PedidoRecibido::class)) {
                $pedidosActivos = PedidoRecibido::whereIn('estado', ['en_camino', 'asignado', 'preparando'])->count();
                $poolPedidos = PedidoRecibido::where('estado', 'pool')->count();
                $totalEntregasHoy = PedidoRecibido::whereDate('created_at', now()->today())->count();
            }
        } catch (\Throwable $e) {
            // Silencioso si la tabla difiere
        }

        // Reportes viales activos en las últimas 6 horas
        $reportesVialesActivos = RoadReport::where('is_active', true)
            ->where('created_at', '>=', now()->subHours(6))
            ->count();

        // Estado del sistema y rendimiento
        $dbConnected = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbConnected = false;
        }

        $memoryUsage = function_exists('memory_get_usage')
            ? round(memory_get_usage(true) / 1024 / 1024, 2).' MB'
            : 'N/A';

        return response()->json([
            'metrics' => [
                'repartidores' => [
                    'total' => $totalRepartidores,
                    'online' => $repartidoresOnline,
                    'pendientes' => $repartidoresPendientes,
                    'aprobados' => $repartidoresAprobados,
                ],
                'pedidos' => [
                    'activos' => $pedidosActivos,
                    'pool' => $poolPedidos,
                    'entregas_hoy' => $totalEntregasHoy,
                ],
                'reportes_viales_activos' => $reportesVialesActivos,
                'rendimiento_sistema' => [
                    'database_status' => $dbConnected ? 'online' : 'error',
                    'websocket_reverb' => 'online',
                    'memory_usage' => $memoryUsage,
                    'php_version' => PHP_VERSION,
                    'environment' => config('app.env'),
                ],
            ],
            'config' => MasterAppConfig::getConfig(),
        ]);
    }

    /**
     * Retorna la configuración master actual.
     *
     * GET /api/v1/master/config
     */
    public function getConfig(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('Master') && ! $user->hasRole('Super Admin'))) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere rol Master.',
            ], 403);
        }

        return response()->json([
            'config' => MasterAppConfig::getConfig(),
        ]);
    }

    /**
     * Actualiza la configuración master del app y formulario de registro.
     *
     * POST /api/v1/master/config
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('Master') && ! $user->hasRole('Super Admin'))) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere rol Master.',
            ], 403);
        }

        $validated = $request->validate([
            'app_name' => ['nullable', 'string', 'max:100'],
            'require_rut' => ['nullable', 'boolean'],
            'require_vehicle' => ['nullable', 'boolean'],
            'default_coverage_radius_km' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'default_commission_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'require_manual_approval' => ['nullable', 'boolean'],
            'registration_success_message' => ['nullable', 'string', 'max:255'],
            'terms_conditions_url' => ['nullable', 'string', 'max:255'],
            'app_icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'app_icon_url' => ['nullable', 'string', 'max:255'],
        ]);

        $config = MasterAppConfig::getConfig();

        if ($request->hasFile('app_icon')) {
            $path = $request->file('app_icon')->store('branding', 'public');
            $validated['app_icon_url'] = Storage::disk('public')->url($path);
        }

        $config->update(array_filter($validated, fn ($val) => $val !== null));

        return response()->json([
            'message' => 'Configuración Master actualizada correctamente',
            'config' => $config->fresh(),
        ]);
    }

    /**
     * Endpoint público / autenticado para consultar la configuración de marca y registro de la app.
     *
     * GET /api/v1/app/config
     */
    public function publicConfig(): JsonResponse
    {
        $config = MasterAppConfig::getConfig();

        return response()->json([
            'app_name' => $config->app_name,
            'app_icon_url' => $config->app_icon_url,
            'require_rut' => (bool) $config->require_rut,
            'require_vehicle' => (bool) $config->require_vehicle,
            'default_coverage_radius_km' => (float) $config->default_coverage_radius_km,
            'default_commission_pct' => (float) $config->default_commission_pct,
            'registration_success_message' => $config->registration_success_message,
            'terms_conditions_url' => $config->terms_conditions_url,
        ]);
    }
}

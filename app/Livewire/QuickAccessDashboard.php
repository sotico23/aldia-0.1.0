<?php

namespace App\Livewire;

use App\Livewire\Dashboard\KpiClientes;
use App\Livewire\Dashboard\KpiComoVoy;
use App\Livewire\Dashboard\KpiCuentasPorCobrar;
use App\Livewire\Dashboard\KpiFaltaPagar;
use App\Livewire\Dashboard\KpiGastosHoy;
use App\Livewire\Dashboard\KpiGastosNegocio;
use App\Livewire\Dashboard\KpiPagosFijos;
use App\Livewire\Dashboard\KpiProveedores;
use App\Livewire\Dashboard\KpiVentasHoy;
use App\Livewire\Dashboard\KpiVentasPeriodo;
use App\Models\UserDashboardWidget;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class QuickAccessDashboard extends Component
{
    public array $enabledWidgets = [];

    public array $availableWidgets = [];

    // Catalog mapping keys to classes and details
    protected array $widgetCatalog = [
        'kpi_ventas_hoy' => [
            'class' => KpiVentasHoy::class,
            'permission' => 'ventas.ventas.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Ventas de Hoy',
            'description' => 'Muestra el total facturado el día de hoy.',
        ],
        'kpi_gastos_hoy' => [
            'class' => KpiGastosHoy::class,
            'permission' => 'inventario.compras.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Compras de Hoy',
            'description' => 'Muestra el total gastado en compras el día de hoy.',
        ],
        'kpi_falta_pagar' => [
            'class' => KpiFaltaPagar::class,
            'permission' => 'inventario.compras.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Compras Pendientes',
            'description' => 'Total acumulado de compras pendientes de pago.',
        ],
        'kpi_como_voy' => [
            'class' => KpiComoVoy::class,
            'permission' => 'ventas.ventas.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Crecimiento de Ventas',
            'description' => 'Diferencia porcentual de ventas contra el mes anterior.',
        ],
        'kpi_pagos_fijos' => [
            'class' => KpiPagosFijos::class,
            'permission' => 'finanzas.facturacion.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Compromisos Financieros',
            'description' => 'Monto total pagado de compromisos mensuales.',
        ],
        'kpi_proveedores' => [
            'class' => KpiProveedores::class,
            'permission' => 'inventario.proveedores.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Proveedores Registrados',
            'description' => 'Cantidad total de proveedores registrados en la plataforma.',
        ],
        'kpi_gastos_negocio' => [
            'class' => KpiGastosNegocio::class,
            'permission' => 'inventario.compras.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Compras del Mes',
            'description' => 'Monto total acumulado en compras de este mes.',
        ],
        'kpi_clientes' => [
            'class' => KpiClientes::class,
            'permission' => 'comercial.clientes.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Clientes Activos',
            'description' => 'Cantidad total de clientes registrados en el CRM.',
        ],
        'kpi_cuentas_porCobrar' => [
            'class' => KpiCuentasPorCobrar::class,
            'permission' => 'finanzas.facturacion.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Facturas por Cobrar',
            'description' => 'Monto total de facturas pendientes de cobro.',
        ],
        'kpi_ventas_periodo' => [
            'class' => KpiVentasPeriodo::class,
            'permission' => 'ventas.ventas.viewAny',
            'col_span' => 'col-span-1',
            'title' => 'Ventas del Período',
            'description' => 'Total acumulado de ventas cobradas este mes.',
        ],
    ];

    protected $listeners = [
        'widget-settings-updated' => 'saveWidgetSettings',
        'widgets-reordered' => 'reorderWidgets',
    ];

    public function mount(): void
    {
        $this->loadWidgets();
    }

    /**
     * Load widgets matching user's permissions, sorted by custom preferences.
     */
    public function loadWidgets(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $userWidgets = UserDashboardWidget::where('user_id', $user->id)
            ->orderBy('order_index')
            ->get()
            ->keyBy('widget_key');

        $loaded = [];
        $available = [];

        foreach ($this->widgetCatalog as $key => $config) {
            // Strictly check authorization using Spatie Gate
            if ($user->can($config['permission'])) {
                $userConfig = $userWidgets->get($key);
                $settings = $userConfig ? $userConfig->settings : [];
                $visible = isset($settings['visible']) ? (bool) $settings['visible'] : true;

                $widgetData = [
                    'widget_key' => $key,
                    'title' => $config['title'],
                    'description' => $config['description'],
                    'component_class' => $config['class'],
                    'col_span' => $config['col_span'],
                    'order_index' => $userConfig ? $userConfig->order_index : 99,
                    'settings' => $settings,
                    'visible' => $visible,
                ];

                $available[] = $widgetData;

                if ($visible) {
                    $loaded[] = $widgetData;
                }
            }
        }

        // Sort by order_index
        usort($loaded, fn ($a, $b) => $a['order_index'] <=> $b['order_index']);

        $this->enabledWidgets = $loaded;
        $this->availableWidgets = $available;
    }

    /**
     * Reorder widgets on drag and drop.
     */
    public function reorderWidgets(array $orderedKeys): void
    {
        $userId = Auth::id();
        if (! $userId) {
            return;
        }

        foreach ($orderedKeys as $index => $key) {
            UserDashboardWidget::updateOrCreate(
                ['user_id' => $userId, 'widget_key' => $key],
                ['order_index' => $index]
            );
        }

        $this->loadWidgets();
        $this->dispatch('toast-success', 'Dashboard layout updated successfully.');
    }

    /**
     * Persist widget settings.
     */
    public function saveWidgetSettings(string $widgetKey, array $settings): void
    {
        UserDashboardWidget::updateOrCreate(
            ['user_id' => Auth::id(), 'widget_key' => $widgetKey],
            ['settings' => $settings]
        );

        $this->loadWidgets();
    }

    /**
     * Toggle widget visibility.
     */
    public function toggleWidgetVisibility(string $widgetKey): void
    {
        $userId = Auth::id();
        if (! $userId) {
            return;
        }

        $userConfig = UserDashboardWidget::where('user_id', $userId)
            ->where('widget_key', $widgetKey)
            ->first();

        $settings = $userConfig ? $userConfig->settings : [];
        $currentVisible = isset($settings['visible']) ? (bool) $settings['visible'] : true;

        $settings['visible'] = ! $currentVisible;

        UserDashboardWidget::updateOrCreate(
            ['user_id' => $userId, 'widget_key' => $widgetKey],
            ['settings' => $settings]
        );

        $this->loadWidgets();

        $status = $settings['visible'] ? 'habilitado' : 'deshabilitado';
        $this->dispatch('toast-success', "Widget {$status} correctamente.");
    }

    public function render()
    {
        return view('livewire.quick-access-dashboard');
    }
}

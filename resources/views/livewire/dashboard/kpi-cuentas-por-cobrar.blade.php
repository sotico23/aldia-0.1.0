<div class="h-full">
    @component('livewire.dashboard.base-widget', [
        'widgetKey' => $widgetKey,
        'title' => 'Lo que me deben',
        'subtitle' => 'Facturas pendientes'
    ])
        <div class="flex items-center justify-between mt-2">
            <div class="text-3xl font-black text-violet-600 dark:text-violet-400">
                {{ \App\Helpers\MoneyHelper::formatWithSymbol($cuentasPorCobrar) }}
            </div>
            <div class="rounded-lg bg-violet-500/10 p-2 text-violet-600 dark:text-violet-400">
                <!-- Icono: credit-card -->
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>
                </svg>
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <a href="{{ route('facturacion.index') }}" class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 flex items-center gap-1 font-semibold">
                Ver Facturación
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75m6.75 6.75l-6.75 6.75"/>
                </svg>
            </a>
        </div>
    @endcomponent
</div>

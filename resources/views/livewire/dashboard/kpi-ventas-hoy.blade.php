<div class="h-full">
    @component('livewire.dashboard.base-widget', [
        'widgetKey' => $widgetKey,
        'title' => 'Lo que entró hoy',
        'subtitle' => 'Ventas del día'
    ])
        <div class="flex items-center justify-between mt-2">
            <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400">
                {{ \App\Helpers\MoneyHelper::formatWithSymbol($ingresosHoy) }}
            </div>
            <div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                <!-- Icono: arrow-trend-up -->
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/>
                </svg>
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <a href="{{ route('pos.index') }}" class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 flex items-center gap-1 font-semibold">
                Ir a Ventas
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75m6.75 6.75l-6.75 6.75"/>
                </svg>
            </a>
        </div>
    @endcomponent
</div>

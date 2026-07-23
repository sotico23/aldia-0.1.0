<div class="h-full">
    @component('livewire.dashboard.base-widget', [
        'widgetKey' => $widgetKey,
        'title' => 'Lo que salió hoy',
        'subtitle' => 'Compras del día'
    ])
        <div class="flex items-center justify-between mt-2">
            <div class="text-3xl font-black text-rose-600 dark:text-rose-400">
                {{ \App\Helpers\MoneyHelper::formatWithSymbol($gastosHoy) }}
            </div>
            <div class="rounded-lg bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400">
                <!-- Icono: arrow-trend-down -->
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306-3.09M21.75 6.75v5.25m0 0h-5.25m5.25 0l-5.25 5.25"/>
                </svg>
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <a href="{{ route('compras.index') }}" class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 flex items-center gap-1 font-semibold">
                Ir a Compras
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75m6.75 6.75l-6.75 6.75"/>
                </svg>
            </a>
        </div>
    @endcomponent
</div>

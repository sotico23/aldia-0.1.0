<div class="h-full">
    @component('livewire.dashboard.base-widget', [
        'widgetKey' => $widgetKey,
        'title' => 'Gastos del negocio',
        'subtitle' => 'Compras del mes'
    ])
        <div class="flex items-center justify-between mt-2">
            <div class="text-3xl font-black text-rose-600 dark:text-rose-400">
                {{ \App\Helpers\MoneyHelper::formatWithSymbol($gastosMes) }}
            </div>
            <div class="rounded-lg bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400">
                <!-- Icono: receipt-percent -->
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z"/>
                </svg>
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <a href="{{ route('compras.index') }}" class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 flex items-center gap-1 font-semibold">
                Ver Compras
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75m6.75 6.75l-6.75 6.75"/>
                </svg>
            </a>
        </div>
    @endcomponent
</div>

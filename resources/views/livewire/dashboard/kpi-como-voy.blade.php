@php
    $isPositive = str_starts_with($comoVoy, '+');
    $isNegative = str_starts_with($comoVoy, '-');
    $colorClass = $isPositive ? 'text-emerald-600 dark:text-emerald-400' : ($isNegative ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-600 dark:text-zinc-400');
    $bgColorClass = $isPositive ? 'bg-emerald-500/10' : ($isNegative ? 'bg-rose-500/10' : 'bg-zinc-500/10');
@endphp

<div class="h-full">
    @component('livewire.dashboard.base-widget', [
        'widgetKey' => $widgetKey,
        'title' => '¿Cómo voy?',
        'subtitle' => 'Vs. mes anterior'
    ])
        <div class="flex items-center justify-between mt-2">
            <div class="text-3xl font-black {{ $colorClass }}">
                {{ $comoVoy }}
            </div>
            <div class="rounded-lg {{ $bgColorClass }} p-2 {{ $colorClass }}">
                <!-- Icono: chart-bar -->
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                </svg>
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <a href="{{ route('dashboard') }}" class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 flex items-center gap-1 font-semibold">
                Ver Reportes
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75m6.75 6.75l-6.75 6.75"/>
                </svg>
            </a>
        </div>
    @endcomponent
</div>

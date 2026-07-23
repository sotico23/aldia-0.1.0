@props([
    'href' => '#',
    'accent' => 'emerald',
    'icon' => 'chart-bar',
    'title' => '',
    'subtitle' => '',
    'value' => '',
    'trendUp' => null,
    'trendDown' => null,
])

@php
$accentColors = [
    'emerald' => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-400', 'hover' => 'hover:border-emerald-500/60 hover:bg-emerald-500/20'],
    'rose' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400', 'hover' => 'hover:border-rose-500/60 hover:bg-rose-500/20'],
    'orange' => ['border' => 'border-orange-500/30', 'bg' => 'bg-orange-500/10', 'text' => 'text-orange-400', 'hover' => 'hover:border-orange-500/60 hover:bg-orange-500/20'],
    'amber' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400', 'hover' => 'hover:border-amber-500/60 hover:bg-amber-500/20'],
    'violet' => ['border' => 'border-violet-500/30', 'bg' => 'bg-violet-500/10', 'text' => 'text-violet-400', 'hover' => 'hover:border-violet-500/60 hover:bg-violet-500/20'],
    'cyan' => ['border' => 'border-cyan-500/30', 'bg' => 'bg-cyan-500/10', 'text' => 'text-cyan-400', 'hover' => 'hover:border-cyan-500/60 hover:bg-cyan-500/20'],
    'sky' => ['border' => 'border-sky-500/30', 'bg' => 'bg-sky-500/10', 'text' => 'text-sky-400', 'hover' => 'hover:border-sky-500/60 hover:bg-sky-500/20'],
];

$colors = $accentColors[$accent] ?? $accentColors['emerald'];

if ($trendUp === true) {
    $valueColor = 'text-emerald-400';
} elseif ($trendDown === true) {
    $valueColor = 'text-rose-400';
} else {
    $valueColor = 'text-white';
}

$icons = [
    'arrow-trend-up' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>',
    'arrow-trend-down' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306-3.09M21.75 6.75v5.25m0 0h-5.25m5.25 0l-5.25 5.25"/></svg>',
    'banknotes' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>',
    'chart-bar' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>',
    'calendar-days' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>',
    'truck' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>',
    'receipt-percent' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z"/></svg>',
    'users' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>',
    'credit-card' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>',
    'chart-line' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>',
];
@endphp

<a
    href="{{ $href }}"
    class="group flex flex-col gap-3 rounded-xl border {{ $colors['border'] }} {{ $colors['bg'] }}/5 bg-white/5 p-4 backdrop-blur-sm transition-all duration-200 {{ $colors['hover'] }}"
>
    <div class="flex items-start justify-between">
        <div class="rounded-lg {{ $colors['bg'] }} p-2 {{ $colors['text'] }}">
            {!! $icons[$icon] ?? $icons['chart-bar'] !!}
        </div>
        <svg class="h-4 w-4 shrink-0 text-gray-600 transition-all group-hover:translate-x-0.5 group-hover:text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75m6.75 6.75l-6.75 6.75"/>
        </svg>
    </div>
    <div>
        <p class="text-sm font-bold text-white/90">{{ $title }}</p>
        <p class="text-[11px] text-gray-500">{{ $subtitle }}</p>
    </div>
    <div class="mt-auto">
        <p class="text-xl font-black tracking-tight {{ $valueColor }}">{{ $value }}</p>
    </div>
</a>

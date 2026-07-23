<div 
    class="relative overflow-hidden rounded-2xl border border-zinc-200/50 bg-white/75 p-5 shadow-xs transition-all duration-200 hover:shadow-md dark:border-zinc-800/50 dark:bg-zinc-900/40 backdrop-blur-md flex flex-col justify-between h-full group"
    style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.8) 0%, rgba(240, 245, 255, 0.8) 100%);"
    wire:key="widget-wrapper-{{ $widgetKey }}"
>
    <!-- Widget Header -->
    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <!-- Reorder Drag Handle -->
            <div class="widget-drag-handle cursor-grab text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                </svg>
            </div>
            <div>
                <h3 class="text-xs font-bold tracking-wider text-zinc-600 dark:text-zinc-400 uppercase">{{ $title }}</h3>
                @if(isset($subtitle))
                    <p class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        
        <!-- Actions slot -->
        @if(isset($actions))
            <div class="flex items-center gap-1.5">
                {{ $actions }}
            </div>
        @endif
    </div>

    <!-- Widget Content -->
    <div class="flex-1 text-zinc-800 dark:text-zinc-100">
        {{ $slot }}
    </div>
</div>

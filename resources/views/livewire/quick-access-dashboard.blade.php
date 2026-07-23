<div 
    x-data="{
        showCustomizeModal: false,
        dragging: null,
        dropping: null,
        reorder() {
            let orderedKeys = Array.from($refs.widgetGrid.children).map(el => el.dataset.key);
            $wire.reorderWidgets(orderedKeys);
        }
    }"
    class="w-full space-y-4"
>
    <!-- Customization Toolbar -->
    <div class="flex items-center justify-between">
        <h2 class="text-xs font-bold tracking-wider text-zinc-500 uppercase">Panel Personalizado</h2>
        <button 
            type="button" 
            x-on:click="showCustomizeModal = true"
            class="flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 shadow-xs hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800/80 transition-colors"
        >
            <svg class="h-4 w-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
            </svg>
            Personalizar
        </button>
    </div>

    <!-- Grid container using Tailwind 4 utility grids -->
    <div 
        x-ref="widgetGrid"
        class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6"
    >
        @foreach($enabledWidgets as $widget)
            <div 
                data-key="{{ $widget['widget_key'] }}"
                class="{{ $widget['col_span'] }} transition-all duration-200"
                draggable="true"
                x-on:dragstart="dragging = '{{ $widget['widget_key'] }}'; $event.dataTransfer.setData('text/plain', '{{ $widget['widget_key'] }}')"
                x-on:dragover.prevent="dropping = '{{ $widget['widget_key'] }}'"
                x-on:drop="
                    if (dragging && dragging !== '{{ $widget['widget_key'] }}') {
                        let children = Array.from($refs.widgetGrid.children);
                        let dragIndex = children.findIndex(el => el.dataset.key === dragging);
                        let dropIndex = children.findIndex(el => el.dataset.key === '{{ $widget['widget_key'] }}');
                        if (dragIndex > -1 && dropIndex > -1) {
                            if (dragIndex < dropIndex) {
                                $refs.widgetGrid.insertBefore(children[dragIndex], children[dropIndex].nextSibling);
                            } else {
                                $refs.widgetGrid.insertBefore(children[dragIndex], children[dropIndex]);
                            }
                            reorder();
                        }
                    }
                    dragging = null;
                    dropping = null;
                "
                style="content-visibility: auto;"
            >
                @livewire($widget['component_class'], [
                    'widgetKey' => $widget['widget_key'],
                    'settings' => $widget['settings'] ?? [],
                    'orderIndex' => $widget['order_index']
                ], key($widget['widget_key']))
            </div>
        @endforeach
    </div>

    <!-- Customize Modal -->
    <div 
        x-show="showCustomizeModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;"
    >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-zinc-950/40 backdrop-blur-xs" x-on:click="showCustomizeModal = false"></div>

        <!-- Modal Content -->
        <div 
            class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-800 dark:bg-zinc-950 max-h-[85vh] flex flex-col"
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <div class="flex items-center justify-between border-b border-zinc-100 pb-4 dark:border-zinc-900">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-50">Gestionar Widgets</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Activa o desactiva las métricas que deseas ver en tu panel.</p>
                </div>
                <button type="button" x-on:click="showCustomizeModal = false" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-50 hover:text-zinc-600 dark:hover:bg-zinc-900 dark:hover:text-zinc-300 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- List of available widgets -->
            <div class="flex-1 overflow-y-auto py-4 space-y-3">
                @foreach($availableWidgets as $widget)
                    <div class="flex items-center justify-between rounded-xl border border-zinc-100 bg-zinc-50/50 p-3.5 dark:border-zinc-900 dark:bg-zinc-900/40">
                        <div class="flex-1 pr-4">
                            <h4 class="text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $widget['title'] }}</h4>
                            @if(isset($widget['description']))
                                <p class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">{{ $widget['description'] }}</p>
                            @endif
                        </div>
                        <button 
                            type="button" 
                            wire:click="toggleWidgetVisibility('{{ $widget['widget_key'] }}')"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden {{ $widget['visible'] ? 'bg-emerald-500' : 'bg-zinc-200 dark:bg-zinc-800' }}"
                        >
                            <span 
                                class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out {{ $widget['visible'] ? 'translate-x-4' : 'translate-x-0' }}"
                            ></span>
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-zinc-100 pt-4 dark:border-zinc-900 flex justify-end">
                <button 
                    type="button" 
                    x-on:click="showCustomizeModal = false"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-xs font-semibold text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-950 dark:hover:bg-zinc-200 transition-colors"
                >
                    Listo
                </button>
            </div>
        </div>
    </div>
</div>

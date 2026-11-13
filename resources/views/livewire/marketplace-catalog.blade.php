<div class="w-full space-y-5 lg:col-span-10">
    @if ($store && tabActivo('productos'))
        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col gap-4">
                {/* Header del Catálogo */}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Catálogo de Productos</h2>
                        <p class="text-xs text-slate-500">
                            {{ $productos->total() }} productos disponibles
                        </p>
                    </div>
                    <button
                        wire:click="cargarMas"
                        class="flex items-center gap-1.5 text-xs font-semibold border-slate-200 text-slate-700 hover:text-primary hover:border-primary/50 hover:bg-primary/5 transition-colors shadow-xs"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Cargar más productos
                    </button>
                </div>

                {/* Grid de productos */}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($productos as $producto)
                        @include('livewire.partials.product-card', ['producto' => $producto])
                    @empty
                        <div class="col-span-full py-8 text-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3 h-10 w-10 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                            <p class="text-sm">No hay productos disponibles</p>
                        </div>
                    @endforeach
                </div>

                {/* Indicador de carga / paginación */}
                @if ($productos->hasMorePages())
                    <div class="col-span-full py-8 text-center">
                        <button
                            wire:click="cargarMas"
                            class="flex items-center justify-center gap-2 w-full max-w-md mx-auto"
                        >
                            <span class="animate-ping absolute inline-flex h-4 w-4 rounded-full bg-emerald-400 opacity-75"></span>
                            <svg class="relative h-4 w-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-.9a1 1 0 000-1.666z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Cargando más...</span>
                        </button>
                    </div>
                @elseif ($productos->count() === 0)
                    <div class="col-span-full py-8 text-center text-slate-400">
                        <p class="text-sm">No hay más productos</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

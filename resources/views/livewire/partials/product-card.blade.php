<div class="group relative flex flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition-shadow hover:shadow-md">
    {{-- Imagen del producto --}}
    <button
        type="button"
        class="relative aspect-square w-full cursor-pointer overflow-hidden rounded-lg border border-slate-100 bg-slate-100 text-left"
    >
        @if ($producto->imagen)
            <img
                src="{{ asset('storage/' . $producto->imagen) }}"
                alt="{{ $producto->nombre }}"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            >
        @else
            <div class="flex h-full w-full items-center justify-center bg-slate-100 text-5xl font-bold text-slate-300">
                {{ Str::upper($producto->nombre[0] ?? '?') }}
            </div>
        @endif

        @if ($producto->is_service)
            <div class="absolute top-2 left-2">
                <span class="inline-flex items-center gap-1 rounded-full bg-violet-600/90 px-2 py-0.5 text-[10px] font-bold text-white backdrop-blur-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $producto->duracion }} min
                </span>
            </div>
        @endif
    </button>

    {{-- Info del producto --}}
    <div class="mt-3 flex-1">
        <button type="button" class="line-clamp-2 text-left text-sm leading-tight font-semibold text-slate-900 hover:text-primary">
            {{ $producto->nombre }}
        </button>
        <p class="mt-1 text-lg font-bold text-slate-900">
            ${{ number_format($producto->precio_venta) }}
        </p>
    </div>

    {{-- Controles de cantidad y agregar --}}
    <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
        <div class="flex items-center justify-between rounded-lg bg-slate-100 p-1">
            <button
                wire:click="decrementar({{ $producto->id }})"
                class="h-7 w-7 rounded-md hover:bg-slate-200"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                </svg>
            </button>

            <span class="text-sm font-semibold">{{ session('carrito.' . $producto->id, 0) }}</span>

            <button
                wire:click="incrementar({{ $producto->id }})"
                class="h-7 w-7 rounded-md hover:bg-slate-200"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </button>
        </div>

        <button
            wire:click="agregar({{ $producto->id }})"
            class="h-9 w-full rounded-lg bg-primary text-white text-sm font-semibold transition-colors hover:bg-primary/90"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            Agregar
        </button>
    </div>
</div>

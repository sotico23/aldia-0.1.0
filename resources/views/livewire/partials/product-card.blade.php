<div class="group flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
    {{-- Imagen --}}
    <div class="relative aspect-square w-full overflow-hidden bg-slate-100">
        @if ($imagen)
            <img
                src="{{ $imagen }}"
                alt="{{ $producto->nombre }}"
                loading="lazy"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            >
        @else
            <div class="flex h-full w-full items-center justify-center bg-slate-100 text-4xl font-bold text-slate-300">
                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($producto->nombre, 0, 1)) }}
            </div>
        @endif

        @if ($producto->is_service)
            <span class="absolute top-2 left-2 rounded-full bg-violet-600/90 px-2 py-0.5 text-[10px] font-bold text-white backdrop-blur-sm">
                Servicio{{ $producto->duracion ? ' · '.$producto->duracion.' min' : '' }}
            </span>
        @elseif ($stock <= 0)
            <span class="absolute top-2 left-2 rounded-full bg-slate-800/85 px-2 py-0.5 text-[10px] font-bold text-white backdrop-blur-sm">
                Sin stock
            </span>
        @endif
    </div>

    {{-- Información --}}
    <div class="flex flex-1 flex-col p-3">
        <h3 class="line-clamp-2 text-sm leading-tight font-semibold text-slate-900" title="{{ $producto->nombre }}">
            {{ $producto->nombre }}
        </h3>

        <p class="mt-1.5 text-lg font-bold text-slate-900">
            ${{ number_format((float) $producto->precio_venta, 0, ',', '.') }}
            @if ($producto->unidad_medida)
                <span class="text-[11px] font-normal text-slate-400">/ {{ $producto->unidad_medida }}</span>
            @endif
        </p>

        @if (! $producto->is_service)
            <p class="mt-0.5 text-[11px] font-medium {{ $stock > 0 ? 'text-emerald-600' : 'text-slate-400' }}">
                {{ $stock > 0 ? number_format($stock, 0, ',', '.').' disponibles' : 'Sin stock registrado' }}
            </p>
        @endif

        <div class="mt-auto pt-3">
            @if ($producto->is_service || $stock > 0)
                @php $enCarrito = (float) ($carrito[$producto->id] ?? 0); @endphp

                <div class="flex items-center gap-2">
                    <div class="flex flex-1 items-center justify-between rounded-lg border border-slate-200 bg-slate-50">
                        <button
                            type="button"
                            wire:click="decrementar({{ $producto->id }})"
                            class="flex h-8 w-8 items-center justify-center rounded-l-lg text-slate-600 transition-colors hover:bg-slate-200"
                            aria-label="Quitar una unidad de {{ $producto->nombre }}"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/>
                            </svg>
                        </button>

                        <span class="text-xs font-bold text-slate-800">
                            {{ rtrim(rtrim(number_format($enCarrito, 2, '.', ''), '0'), '.') ?: '0' }}
                        </span>

                        <button
                            type="button"
                            wire:click="incrementar({{ $producto->id }})"
                            class="flex h-8 w-8 items-center justify-center rounded-r-lg text-slate-600 transition-colors hover:bg-slate-200"
                            aria-label="Agregar una unidad de {{ $producto->nombre }}"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>

                    <button
                        type="button"
                        wire:click="agregar({{ $producto->id }})"
                        class="inline-flex h-8 items-center gap-1 rounded-lg bg-primary px-3 text-xs font-bold text-white transition-colors hover:bg-primary/90"
                        aria-label="Agregar {{ $producto->nombre }} al carrito"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Agregar
                    </button>
                </div>
            @else
                <button
                    type="button"
                    disabled
                    class="w-full cursor-not-allowed rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-400"
                >
                    Agotado
                </button>
            @endif
        </div>
    </div>
</div>

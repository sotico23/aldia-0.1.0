@php($producto = $productoDetalle)

<div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm" wire:keydown.escape.window="cerrarDetalle">
    <section class="max-h-[92dvh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="producto-detalle-titulo" x-trap.noscroll="true">
        <header class="relative overflow-hidden bg-gradient-to-r from-violet-700 via-purple-700 to-teal-700 px-6 py-6 text-white">
            <span aria-hidden="true" class="pointer-events-none absolute -right-2 top-1/2 -translate-y-1/2 select-none text-[130px] leading-none opacity-10">📦</span>
            <button type="button" wire:click="cerrarDetalle" aria-label="Cerrar detalle" class="absolute right-4 top-4 rounded-full bg-white/15 p-1.5 text-white transition hover:bg-white/30">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 0 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
            </button>
            <span class="inline-flex rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-widest">Detalle de producto</span>
            <h2 id="producto-detalle-titulo" class="mt-3 text-2xl font-black uppercase tracking-tight sm:text-3xl">{{ $producto->nombre }}</h2>
            <p class="mt-1 text-sm text-white/80">SKU {{ $producto->codigo ?: '—' }} · {{ $producto->categoria?->nombre ?: 'General' }}</p>
        </header>

        <div class="max-h-[62dvh] space-y-4 overflow-y-auto px-6 py-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-violet-500">Precio de venta</p>
                    <p class="mt-2 text-2xl font-black text-violet-900">{{ $money($producto->precio_venta) }}</p>
                    <p class="mt-1 text-xs text-violet-700">Compra: {{ $money($producto->precio_compra) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Stock total</p>
                    <p class="mt-2 text-2xl font-black text-emerald-800">{{ number_format((float) $producto->stock_total, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-emerald-700">Mínimo: {{ number_format((float) $producto->stock_minimo, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-teal-600">Estado</p>
                    <p class="mt-2 font-bold uppercase text-teal-800">{{ $producto->activo ? 'Activo' : 'Inactivo' }}</p>
                    <p class="mt-1 text-xs text-teal-700">{{ $producto->unidad_medida ?: 'unidad' }} · {{ $producto->tiene_variantes ? 'Con variantes' : 'Sin variantes' }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Imagen representativa</p>
                    <div class="flex h-44 items-center justify-center overflow-hidden rounded-lg border border-slate-100 bg-slate-50">
                        @if($producto->imagen)
                            <img class="h-full w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($producto->imagen) }}" alt="{{ $producto->nombre }}">
                        @else
                            <span class="text-6xl opacity-40" aria-hidden="true">📦</span>
                        @endif
                    </div>
                    <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ $producto->descripcion ?: 'Sin descripción.' }}</p>
                </div>

                <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-violet-500">Datos del producto</p>
                    <dl class="space-y-2 text-sm text-violet-900">
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Categoría</dt><dd class="font-semibold">{{ $producto->categoria?->nombre ?: 'General' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Margen</dt><dd class="font-semibold">{{ $money($producto->margen_absoluto ?? 0) }} ({{ number_format((float) ($producto->margen_ganancia ?? 0), 1) }}%)</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Comisión cajero</dt><dd class="font-semibold">{{ $money($producto->comision_cajero_por_unidad) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Unidad</dt><dd class="font-semibold">{{ $producto->unidad_medida ?: 'unidad' }} @if($producto->medida_pesable)(pesable)@endif</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Vencimiento</dt><dd class="font-semibold">{{ $producto->fecha_vencimiento?->format('d-m-Y') ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Envase retornable</dt><dd class="font-semibold">{{ $producto->envase_retornable ? ($producto->envaseProducto?->nombre ?: 'Sí') : 'No' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Marketplace</dt><dd class="font-semibold">{{ $producto->mostrar_en_perfil ? 'Publicado' : 'Oculto' }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-emerald-600">Stock por almacén</p>
                @forelse($producto->inventarios as $inventario)
                    <div class="flex justify-between gap-3 border-b border-emerald-100 py-2 text-sm text-emerald-900 last:border-0">
                        <span>{{ $inventario->almacen?->nombre ?: 'Almacén' }}</span>
                        <span class="font-semibold">{{ number_format((float) $inventario->cantidad, 0, ',', '.') }} / mín {{ number_format((float) $inventario->cantidad_minima, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-emerald-700">Sin inventario asignado.</p>
                @endforelse
            </div>

            @if($producto->tiene_variantes && $producto->skus->isNotEmpty())
                <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-teal-600">Variantes y SKUs</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-[10px] uppercase tracking-wider text-teal-600">
                                <tr><th class="pb-2">SKU</th><th class="pb-2">Variante</th><th class="pb-2 text-right">Precio</th><th class="pb-2 text-right">Stock</th></tr>
                            </thead>
                            <tbody class="divide-y divide-teal-100">
                                @foreach($producto->skus as $sku)
                                    <tr>
                                        <td class="py-2 font-semibold text-teal-900">{{ $sku->sku }}</td>
                                        <td class="py-2 text-teal-800">{{ $sku->valores->map(fn ($valor) => $valor->varianteValor?->valor)->filter()->join(' / ') ?: '—' }}</td>
                                        <td class="py-2 text-right text-teal-900">{{ $money($sku->precio_venta ?: $producto->precio_venta) }}</td>
                                        <td class="py-2 text-right text-teal-900">{{ number_format((float) $sku->stock, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                @can('comercial.productos.edit')
                    <button type="button" class="{{ $primary }}" wire:click="editar({{ $producto->id }})">Editar producto</button>
                @endcan
            </div>
            <button type="button" class="{{ $button }} bg-white" wire:click="cerrarDetalle">Cerrar detalle</button>
        </footer>
    </section>
</div>

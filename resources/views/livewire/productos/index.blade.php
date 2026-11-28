@php
    $control = 'min-h-9 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-ring';
    $button = 'inline-flex min-h-9 items-center justify-center rounded-md border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50';
    $primary = 'inline-flex min-h-9 items-center justify-center rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50';
    $money = static fn ($amount) => \Illuminate\Support\Number::currency((float) $amount, in: 'CLP', locale: 'es_CL');
    $filtrosExport = array_filter(['search' => $busqueda, 'categoria_id' => $categoriaId, 'almacen_id' => $almacenId, 'stock_bajo' => $stockBajo ? '1' : '']);
@endphp

<div class="mx-auto max-w-screen-2xl space-y-5 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Catálogo de productos</h1>
            <p class="text-sm text-muted-foreground">Gestión de SKUs, inventario, variantes y precios.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('comercial.productos.export')
                <a class="{{ $button }}" href="{{ route('productos.export', $filtrosExport) }}">Exportar CSV</a>
                <a class="{{ $button }}" href="{{ route('productos.exportExcel', $filtrosExport) }}">Exportar Excel</a>
            @endcan
            @can('comercial.productos.import')
                <form class="flex items-end gap-2" wire:submit="importar">
                    <input class="{{ $button }}" type="file" wire:model="archivo" accept=".csv,.txt,.xls,.xlsx" required>
                    <button type="submit" class="{{ $button }}" wire:loading.attr="disabled" wire:target="archivo,importar">Importar</button>
                    @error('archivo') <span class="text-xs text-destructive" role="alert">{{ $message }}</span> @enderror
                </form>
            @endcan
            @can('comercial.productos.create')
                <button type="button" class="{{ $primary }}" wire:click="nueva">Nuevo producto</button>
            @endcan
        </div>
    </div>

    @if($mensaje)
        <p class="rounded-md border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-700" role="status">{{ $mensaje }}</p>
    @endif

    <section class="space-y-4 rounded-lg border border-border bg-card p-4" aria-labelledby="listado-productos">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 id="listado-productos" class="text-lg font-semibold">Productos registrados</h2>
                <p class="text-sm text-muted-foreground" aria-live="polite">{{ $productos->total() }} registros</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="{{ $vista === 'tabla' ? $primary : $button }}" wire:click="$set('vista', 'tabla')" aria-pressed="{{ $vista === 'tabla' ? 'true' : 'false' }}">Tabla</button>
                <button type="button" class="{{ $vista === 'tarjetas' ? $primary : $button }}" wire:click="$set('vista', 'tarjetas')" aria-pressed="{{ $vista === 'tarjetas' ? 'true' : 'false' }}">Tarjetas</button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="grid gap-1 text-sm">Buscar
                <input class="{{ $control }} w-full" type="search" placeholder="Nombre, código o descripción" wire:model.live.debounce.350ms="busqueda">
            </label>
            <label class="grid gap-1 text-sm">Categoría
                <select class="{{ $control }} w-full" wire:model.live="categoriaId">
                    <option value="">Todas</option>
                    @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm">Almacén
                <select class="{{ $control }} w-full" wire:model.live="almacenId">
                    <option value="">Todos</option>
                    @foreach($almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end gap-2">
                <label class="flex flex-1 items-center gap-2 rounded-md border border-input px-3 py-2 text-sm">
                    <input type="checkbox" wire:model.live="stockBajo"> Stock bajo
                </label>
                <button type="button" class="{{ $button }}" wire:click="limpiarFiltros">Limpiar</button>
            </div>
        </div>

        @if($productos->isEmpty())
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">No hay productos que coincidan con estos filtros.</p>
        @elseif($vista === 'tabla')
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-muted/50">
                        <tr>
                            @foreach(['nombre' => 'SKU / Producto', 'categoria' => 'Categoría', 'descripcion' => 'Descripción', 'precio_venta' => 'Precio', 'stock' => 'Stock', 'estado' => 'Estado'] as $campo => $etiqueta)
                                <th scope="col" class="whitespace-nowrap px-3 py-2">
                                    <button type="button" wire:click="ordenar('{{ $campo }}')" class="hover:underline">
                                        {{ $etiqueta }} @if($orden === $campo) {{ $direccion === 'asc' ? '↑' : '↓' }} @endif
                                    </button>
                                </th>
                            @endforeach
                            <th scope="col" class="px-3 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($productos as $producto)
                            <tr wire:key="producto-{{ $producto->id }}">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($producto->imagen)
                                            <img class="size-10 shrink-0 rounded-md object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($producto->imagen) }}" alt="" loading="lazy">
                                        @else
                                            <span class="text-xl" aria-hidden="true">📦</span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="break-words font-medium">{{ $producto->nombre }}</p>
                                            <p class="text-xs text-muted-foreground">{{ $producto->codigo ?: 'Sin código' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3"><span class="rounded-full bg-violet-50 px-2 py-1 text-xs font-semibold text-violet-700">{{ $producto->categoria?->nombre ?: 'General' }}</span></td>
                                <td class="max-w-xs truncate px-3 py-3 text-muted-foreground">{{ $producto->descripcion ?: '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 font-semibold">{{ $money($producto->precio_venta) }}</td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <span class="font-semibold {{ $producto->stock_total <= $producto->stock_minimo ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format((float) $producto->stock_total, 0, ',', '.') }}</span>
                                    <span class="text-xs text-muted-foreground">/ mín {{ number_format((float) $producto->stock_minimo, 0, ',', '.') }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $producto->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $producto->activo ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="px-3 py-3">@include('livewire.productos.acciones')</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($productos as $producto)
                    <article class="space-y-3 rounded-xl border border-border bg-background p-4" wire:key="tarjeta-{{ $producto->id }}">
                        <div class="flex items-start gap-3">
                            @if($producto->imagen)
                                <img class="size-14 rounded-md object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($producto->imagen) }}" alt="" loading="lazy">
                            @else
                                <span class="text-3xl" aria-hidden="true">📦</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="break-words font-semibold">{{ $producto->nombre }}</p>
                                <p class="text-xs text-muted-foreground">{{ $producto->codigo ?: 'Sin código' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <span class="rounded-full bg-violet-50 px-2 py-1 text-xs font-semibold text-violet-700">{{ $producto->categoria?->nombre ?: 'General' }}</span>
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $producto->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $producto->activo ? 'Activo' : 'Inactivo' }}</span>
                        </div>
                        <p class="line-clamp-2 text-sm text-muted-foreground">{{ $producto->descripcion ?: 'Sin descripción' }}</p>
                        <div class="flex justify-between text-sm">
                            <span class="font-bold text-emerald-700">{{ $money($producto->precio_venta) }}</span>
                            <span class="text-muted-foreground">Stock {{ number_format((float) $producto->stock_total, 0, ',', '.') }}</span>
                        </div>
                        @include('livewire.productos.acciones')
                    </article>
                @endforeach
            </div>
        @endif

        {{ $productos->links() }}
    </section>

    @if($formOpen)
        @include('livewire.productos.formulario')
    @endif

    @if($detalle && $productoDetalle)
        @include('livewire.productos.detalle')
    @endif
</div>

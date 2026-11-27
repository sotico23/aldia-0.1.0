@php
    $control = 'min-h-9 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-ring';
    $button = 'inline-flex min-h-9 items-center justify-center rounded-md border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50';
    $primary = 'inline-flex min-h-9 items-center justify-center rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50';
    $money = static fn ($amount, $code = null) => \Illuminate\Support\Number::currency((float) $amount, in: $code ?: $moneda, locale: 'es_CL');
    $states = ['pagada' => 'Pagada', 'pendiente' => 'Pendiente', 'cancelada' => 'Cancelada', 'anulada' => 'Anulada', 'confirmado' => 'Confirmada', 'completada' => 'Completada', 'enviado' => 'Enviada', 'entregado' => 'Entregada', 'cancelado' => 'Cancelada'];
    $exportFilters = array_filter(['busqueda' => $busqueda, 'estado' => $estado, 'cliente_id' => $cliente, 'fechaDesde' => $desde, 'fechaHasta' => $hasta]);
    $maximo = max(1, $serie->max('monto'));
@endphp

<div class="mx-auto max-w-screen-2xl space-y-5 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Ventas</h1>
        <div class="flex flex-wrap gap-2">
            @can('ventas.ventas.export')
                <a class="{{ $button }}" href="{{ route('ventas.export', $exportFilters) }}">Exportar CSV</a>
                <a class="{{ $button }}" href="{{ route('ventas.export_excel', $exportFilters) }}">Exportar Excel</a>
            @endcan
            @can('create', \App\Models\Venta::class)
                <button type="button" class="{{ $primary }}" wire:click="nueva">Nueva venta</button>
            @endcan
        </div>
    </div>

    @if($mensaje)
        <p class="rounded-md border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-700 dark:text-emerald-300" role="status">{{ $mensaje }}</p>
    @endif
    @unless($formOpen)
        @error('estado') <p class="text-sm text-destructive" role="alert">{{ $message }}</p> @enderror
    @endunless

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach(['Ventas' => $resumen->total, 'Ingresos pagados' => $money($resumen->ingresos), 'Pagadas' => $resumen->pagadas, 'Pendientes' => $resumen->pendientes] as $label => $value)
            <div class="rounded-lg border border-border bg-card p-4">
                <p class="text-sm text-muted-foreground">{{ $label }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <section class="space-y-3 rounded-lg border border-border bg-card p-4" aria-labelledby="evolucion-ventas">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="evolucion-ventas" class="font-semibold">Evolución de ventas</h2>
            <select wire:model.live="periodo" class="{{ $control }}" aria-label="Periodo del gráfico">
                <option value="mes">Por mes</option>
                <option value="semana">Por semana</option>
                <option value="dia">Por día</option>
            </select>
        </div>
        <div class="grid gap-2 sm:grid-cols-6">
            @foreach($serie as $punto)
                <div class="space-y-1 text-xs">
                    <div class="flex flex-wrap justify-between gap-x-2">
                        <span class="text-muted-foreground">{{ $punto['label'] }}</span>
                        <span class="font-semibold tabular-nums">{{ $money($punto['monto']) }}</span>
                    </div>
                    <progress class="h-2 w-full overflow-hidden rounded-full accent-primary" value="{{ max(0, $punto['monto']) }}" max="{{ $maximo }}" aria-hidden="true"></progress>
                </div>
            @endforeach
        </div>
    </section>

    <section class="space-y-4 rounded-lg border border-border bg-card p-4" aria-labelledby="listado-ventas">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 id="listado-ventas" class="text-lg font-semibold">Listado de ventas</h2>
                <p class="text-sm text-muted-foreground" aria-live="polite">{{ $ventas->total() }} registros</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="{{ $vista === 'tabla' ? $primary : $button }}" wire:click="$set('vista', 'tabla')" aria-pressed="{{ $vista === 'tabla' ? 'true' : 'false' }}">Tabla</button>
                <button type="button" class="{{ $vista === 'tarjetas' ? $primary : $button }}" wire:click="$set('vista', 'tarjetas')" aria-pressed="{{ $vista === 'tarjetas' ? 'true' : 'false' }}">Tarjetas</button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <label class="grid gap-1 text-sm">Buscar
                <input class="{{ $control }} w-full" type="search" placeholder="Número, cliente o notas" wire:model.live.debounce.350ms="busqueda">
            </label>
            <label class="grid gap-1 text-sm">Estado
                <select class="{{ $control }} w-full" wire:model.live="estado">
                    <option value="">Todos los estados</option>
                    @foreach($states as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm">Cliente
                <select class="{{ $control }} w-full" wire:model.live="cliente">
                    <option value="">Todos los clientes</option>
                    @foreach($clientes as $opcion)
                        <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm">Desde
                <input class="{{ $control }} w-full" type="date" wire:model.live="desde">
            </label>
            <label class="grid gap-1 text-sm">Hasta
                <input class="{{ $control }} w-full" type="date" wire:model.live="hasta">
            </label>
            <button type="button" class="{{ $button }} self-end" wire:click="limpiarFiltros">Limpiar filtros</button>
        </div>

        @if($ventas->isEmpty())
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">No hay ventas que coincidan con estos filtros.</p>
        @elseif($vista === 'tarjetas')
            @foreach($ventas->getCollection()->groupBy('estado') as $grupo => $registros)
                <div class="space-y-2" wire:key="grupo-{{ $grupo }}">
                    <h3 class="font-semibold">{{ $states[$grupo] ?? ucfirst($grupo) }} <span class="text-sm font-normal text-muted-foreground">({{ $registros->count() }})</span></h3>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($registros as $venta)
                            <article class="space-y-3 rounded-lg border border-border bg-background p-4" wire:key="tarjeta-{{ $venta->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="break-all text-sm font-semibold">{{ $venta->numero }}</p>
                                        <p class="text-sm text-muted-foreground">{{ $venta->cliente?->nombre ?: 'Cliente general' }}</p>
                                    </div>
                                    @include('livewire.ventas.estado')
                                </div>
                                <dl class="space-y-1 text-sm">
                                    <div class="flex justify-between gap-2"><dt class="text-muted-foreground">Fecha</dt><dd>{{ $venta->fecha?->format('d-m-Y') }}</dd></div>
                                    <div class="flex justify-between gap-2"><dt class="text-muted-foreground">Total</dt><dd class="font-semibold">{{ $money($venta->total, $venta->currency) }}</dd></div>
                                    <div class="flex justify-between gap-2"><dt class="text-muted-foreground">Almacén</dt><dd>{{ $venta->almacen?->nombre ?: '—' }}</dd></div>
                                </dl>
                                @if($venta->notas)
                                    <p class="line-clamp-2 text-sm text-muted-foreground">{{ $venta->notas }}</p>
                                @endif
                                @include('livewire.ventas.acciones')
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-muted/50">
                        <tr>
                            @foreach(['numero' => 'Número', 'cliente' => 'Cliente', 'fecha' => 'Fecha', 'total' => 'Total', 'estado' => 'Estado'] as $campo => $label)
                                <th scope="col" class="whitespace-nowrap px-3 py-2">
                                    @if($campo === 'cliente')
                                        {{ $label }}
                                    @else
                                        <button type="button" wire:click="ordenar('{{ $campo }}')" class="hover:underline">
                                            {{ $label }} @if($orden === $campo) {{ $direccion === 'asc' ? '↑' : '↓' }} @endif
                                        </button>
                                    @endif
                                </th>
                            @endforeach
                            <th scope="col" class="px-3 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($ventas as $venta)
                            <tr wire:key="fila-{{ $venta->id }}">
                                <td class="px-3 py-3 font-medium">{{ $venta->numero }}</td>
                                <td class="px-3 py-3">{{ $venta->cliente?->nombre ?: 'Cliente general' }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ $venta->fecha?->format('d-m-Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-3 font-semibold">{{ $money($venta->total, $venta->currency) }}</td>
                                <td class="px-3 py-3">@include('livewire.ventas.estado')</td>
                                <td class="px-3 py-3">@include('livewire.ventas.acciones')</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{ $ventas->links() }}
    </section>

    @can('ventas.ventas.import')
        <details class="rounded-lg border border-border bg-card p-4">
            <summary class="cursor-pointer font-semibold">Importar ventas</summary>
            <form class="mt-3 flex flex-wrap items-end gap-3" wire:submit="importar">
                <label class="grid min-w-0 gap-1 text-sm">Archivo CSV o Excel
                    <input class="{{ $control }} max-w-full" type="file" wire:model="archivo" accept=".csv,.txt,.xls,.xlsx" required>
                </label>
                <button type="submit" class="{{ $button }}" wire:loading.attr="disabled">Importar</button>
                <span role="status" class="text-sm text-muted-foreground" wire:loading wire:target="archivo,importar">Procesando archivo…</span>
                @error('archivo') <p class="w-full text-sm text-destructive" role="alert">{{ $message }}</p> @enderror
            </form>
        </details>
    @endcan

    @if($formOpen)
        @include('livewire.ventas.formulario')
    @endif

    @if($ventaDetalle)
        @include('livewire.ventas.detalle')
    @endif

    @if($ventaEntrega !== null)
        @include('livewire.ventas.entrega')
    @endif
</div>

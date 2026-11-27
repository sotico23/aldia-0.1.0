@php
    $control = 'min-h-9 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-ring';
    $button = 'inline-flex min-h-9 items-center justify-center rounded-md border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50';
    $primary = 'inline-flex min-h-9 items-center justify-center rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50';
    $etiquetasTipo = ['producto' => 'Producto', 'cliente' => 'Cliente', 'proveedor' => 'Proveedor', 'servicio' => 'Servicio'];
@endphp

<div class="mx-auto max-w-screen-2xl space-y-5 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Categorías</h1>
            <p class="text-sm text-muted-foreground">Clasificación y configuración de visualización.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('comercial.categorias.export')
                <a class="{{ $button }}" href="{{ route('categorias.export', array_filter(['search' => $busqueda, 'tipo' => $tipo, 'marketplace' => $marketplace])) }}">Exportar CSV</a>
                <a class="{{ $button }}" href="{{ route('categorias.exportExcel', array_filter(['search' => $busqueda, 'tipo' => $tipo, 'marketplace' => $marketplace])) }}">Exportar Excel</a>
            @endcan
            @can('comercial.categorias.create')
                <button type="button" class="{{ $primary }}" wire:click="nueva">Nueva categoría</button>
            @endcan
        </div>
    </div>

    @if($mensaje)
        <p class="rounded-md border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-700" role="status">{{ $mensaje }}</p>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach(['Total' => $resumen['total'], 'Activas' => $resumen['activas'], 'En marketplace' => $resumen['marketplace'], 'Tipos' => $resumen['tipos']] as $label => $value)
            <div class="rounded-lg border border-border bg-card p-4">
                <p class="text-sm text-muted-foreground">{{ $label }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if($porTipo->isNotEmpty())
        <section class="space-y-3" aria-labelledby="resumen-tipos">
            <h2 id="resumen-tipos" class="text-lg font-semibold">Resumen por tipo</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($porTipo as $grupo)
                    <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-violet-500">{{ $etiquetasTipo[$grupo['tipo']] ?? ucfirst($grupo['tipo']) }}</p>
                        <p class="mt-2 text-2xl font-black text-violet-900">{{ $grupo['total'] }}</p>
                        <p class="mt-1 text-xs text-violet-700">{{ $grupo['activas'] }} activas</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="space-y-4 rounded-lg border border-border bg-card p-4" aria-labelledby="listado-categorias">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 id="listado-categorias" class="text-lg font-semibold">Listado de categorías</h2>
                <p class="text-sm text-muted-foreground" aria-live="polite">{{ $categorias->total() }} registros</p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="grid gap-1 text-sm">Buscar
                <input class="{{ $control }} w-full" type="search" placeholder="Nombre, descripción o ID" wire:model.live.debounce.350ms="busqueda">
            </label>
            <label class="grid gap-1 text-sm">Tipo
                <select class="{{ $control }} w-full" wire:model.live="tipo">
                    <option value="">Todos los tipos</option>
                    @foreach($tiposDisponibles as $opcionTipo)
                        <option value="{{ $opcionTipo['value'] }}">{{ $opcionTipo['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm">Marketplace
                <select class="{{ $control }} w-full" wire:model.live="marketplace">
                    <option value="">Todas</option>
                    <option value="1">Publicadas</option>
                    <option value="0">No publicadas</option>
                </select>
            </label>
            <button type="button" class="{{ $button }} self-end" wire:click="limpiarFiltros">Limpiar filtros</button>
        </div>

        @if($categorias->isEmpty())
            <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">No hay categorías que coincidan con estos filtros.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-muted/50">
                        <tr>
                            <th scope="col" class="px-3 py-2">
                                <button type="button" wire:click="ordenar('nombre')" class="hover:underline">Nombre @if($orden === 'nombre') {{ $direccion === 'asc' ? '↑' : '↓' }} @endif</button>
                            </th>
                            <th scope="col" class="px-3 py-2">Tipo</th>
                            <th scope="col" class="px-3 py-2">Descripción</th>
                            <th scope="col" class="px-3 py-2">Estado</th>
                            <th scope="col" class="px-3 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($categorias as $categoria)
                            <tr wire:key="fila-categoria-{{ $categoria->id }}">
                                <td class="px-3 py-3 font-medium">
                                    <div class="flex items-center gap-2">
                                        @if($categoria->imagen)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($categoria->imagen) }}" alt="" class="h-8 w-8 rounded-md object-cover">
                                        @endif
                                        <span>{{ $categoria->nombre }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full bg-violet-50 px-2 py-1 text-xs font-semibold text-violet-700">{{ $etiquetasTipo[$categoria->tipo] ?? ucfirst($categoria->tipo ?? 'General') }}</span>
                                </td>
                                <td class="max-w-xs truncate px-3 py-3 text-muted-foreground">{{ $categoria->descripcion ?: '—' }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $categoria->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $categoria->activo ? 'Activa' : 'Inactiva' }}</span>
                                        @if($categoria->mostrar_en_perfil && $categoria->public_profile_id)
                                            <span class="rounded-full bg-teal-50 px-2 py-1 text-xs font-semibold text-teal-700">Marketplace</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3">@include('livewire.categorias.acciones')</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{ $categorias->links() }}
    </section>

    @if($formOpen)
        @include('livewire.categorias.formulario')
    @endif
</div>

@php
    $input = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm';
    $button = 'inline-flex items-center rounded-md border border-input px-3 py-2 text-sm hover:bg-muted disabled:opacity-50';
    $primary = 'inline-flex items-center rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50';
    $filtros = array_filter(['search' => $busqueda, 'estado' => $estado, 'almacen_id' => $almacen]);
@endphp
<div class="space-y-5 p-4 sm:p-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Empleados</h1>
        <div class="flex flex-wrap gap-2">
            @can('rrhh.empleados.export')
                <a class="{{ $button }}" href="{{ route('empleados.export', $filtros) }}">Exportar CSV</a>
                <a class="{{ $button }}" href="{{ route('empleados.exportExcel', $filtros) }}">Exportar Excel</a>
            @endcan
            @can('rrhh.empleados.create')
                <button class="{{ $primary }}" type="button" wire:click="nuevo">Nuevo empleado</button>
            @endcan
        </div>
    </header>

    @if($mensaje)
        <p class="rounded-md bg-primary/10 p-3 text-sm text-primary" role="status">{{ $mensaje }}</p>
    @endif

    <section class="space-y-4 rounded-lg border border-border bg-card p-4" aria-labelledby="empleados-listado">
        <div>
            <h2 class="font-semibold" id="empleados-listado">Listado de empleados</h2>
            <p class="text-sm text-muted-foreground" aria-live="polite">{{ $empleados->total() }} registros</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="space-y-1 text-sm">Buscar
                <input class="{{ $input }}" type="search" placeholder="Nombre, RUT o correo" wire:model.live.debounce.350ms="busqueda">
            </label>
            <label class="space-y-1 text-sm">Estado
                <select class="{{ $input }}" wire:model.live="estado">
                    <option value="">Todos</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                    <option value="licencia">Licencia</option>
                    <option value="vacaciones">Vacaciones</option>
                </select>
            </label>
            <label class="space-y-1 text-sm">Almacén
                <select class="{{ $input }}" wire:model.live="almacen">
                    <option value="">Todos</option>
                    @foreach($almacenes as $opcion)
                        <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50"><tr>
                    <th scope="col" class="p-3">Empleado</th>
                    <th scope="col" class="p-3">Cargo</th>
                    <th scope="col" class="p-3">Almacén</th>
                    <th scope="col" class="p-3">Estado</th>
                    <th scope="col" class="p-3">Acciones</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                @forelse($empleados as $empleado)
                    <tr wire:key="empleado-{{ $empleado->id }}">
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                @if($empleado->foto)
                                    <img class="size-9 shrink-0 rounded-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($empleado->foto) }}" alt="" loading="lazy">
                                @endif
                                <div>
                                    <p class="font-medium">{{ $empleado->nombre_completo }}</p>
                                    <p class="break-all text-xs text-muted-foreground">{{ $empleado->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">{{ $empleado->cargo ?: '—' }}</td>
                        <td class="p-3">{{ $empleado->almacen?->nombre ?: '—' }}</td>
                        <td class="p-3 capitalize">{{ $empleado->estado }}</td>
                        <td class="p-3">
                            <div class="flex gap-2">
                                <button class="{{ $button }}" type="button" wire:click="ver({{ $empleado->id }})">Ver</button>
                                @can('rrhh.empleados.edit')
                                    <button class="{{ $button }}" type="button" wire:click="editar({{ $empleado->id }})">Editar</button>
                                @endcan
                                @can('rrhh.empleados.delete')
                                    <button class="{{ $button }} text-destructive" type="button" wire:click="eliminar({{ $empleado->id }})" wire:confirm="¿Eliminar este empleado y su acceso asociado?" wire:loading.attr="disabled">Eliminar</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-muted-foreground">No hay empleados que coincidan con los filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $empleados->links() }}
    </section>

    @can('rrhh.empleados.import')
        <details class="rounded-lg border border-border bg-card p-4">
            <summary class="cursor-pointer font-semibold">Importar empleados</summary>
            <form class="mt-3 flex flex-wrap items-end gap-3" wire:submit="importar">
                <label class="min-w-0 text-sm">Archivo CSV o Excel<input class="{{ $input }}" type="file" wire:model="archivo" accept=".csv,.txt,.xls,.xlsx" required></label>
                <button class="{{ $button }}" type="submit" wire:loading.attr="disabled" wire:target="archivo,importar">Importar</button>
                @error('archivo') <p class="w-full text-sm text-destructive" role="alert">{{ $message }}</p> @enderror
            </form>
        </details>
    @endcan

    @if($formulario)
        @include('livewire.empleados.formulario')
    @endif

    @if($detalle && $empleadoDetalle)
        @include('livewire.empleados.detalle')
    @endif
</div>

@php
    $tone = match ($venta->estado) {
        'pagada' => 'bg-emerald-600 text-white',
        'pendiente' => 'bg-amber-100 text-amber-900 dark:bg-amber-900 dark:text-amber-100',
        'cancelada', 'cancelado', 'anulada' => 'bg-destructive text-destructive-foreground',
        default => 'bg-muted text-foreground',
    };
@endphp
@can('update', $venta)
    <select aria-label="Estado de venta {{ $venta->numero }}" class="rounded-md border-0 px-2 py-1 text-xs font-semibold {{ $tone }}" wire:change="cambiarEstado({{ $venta->id }}, $event.target.value)" wire:loading.attr="disabled" wire:target="cambiarEstado">
        @unless(in_array($venta->estado, ['pagada', 'pendiente', 'cancelada'], true))
            <option value="{{ $venta->estado }}" selected disabled>{{ $states[$venta->estado] ?? ucfirst($venta->estado) }}</option>
        @endunless
        @foreach(['pagada', 'pendiente', 'cancelada'] as $estadoOpcion)
            <option class="bg-background text-foreground" value="{{ $estadoOpcion }}" @selected($venta->estado === $estadoOpcion)>{{ $states[$estadoOpcion] }}</option>
        @endforeach
    </select>
@else
    <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $tone }}">{{ $states[$venta->estado] ?? ucfirst($venta->estado) }}</span>
@endcan

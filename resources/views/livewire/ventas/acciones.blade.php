<div class="flex flex-wrap items-center gap-1.5">
    <button type="button" class="{{ $button }}" wire:click="ver({{ $venta->id }})">Ver</button>
    <a class="{{ $button }}" href="{{ route('ventas.download', $venta) }}">PDF formal SII</a>
    <a class="{{ $button }}" href="{{ route('ventas.download-informal', $venta) }}">PDF informal</a>
    @if(\Illuminate\Support\Facades\Route::has('ventas.ticket'))
        <a class="{{ $button }}" href="{{ route('ventas.ticket', $venta) }}" target="_blank" rel="noopener">Ticket térmico</a>
    @endif
    @if(! $venta->entrega_exists && in_array($venta->estado, \App\Models\Venta::ESTADOS_PARA_ENTREGA, true))
        @if(auth()->user()->can('flota.entregas.viewAny') && auth()->user()->can('flota.entregas.create'))
            <button type="button" class="{{ $button }}" wire:click="prepararEntrega({{ $venta->id }})">Enviar a reparto</button>
        @endif
    @endif
    @can('update', $venta)
        <button type="button" class="{{ $button }}" wire:click="editar({{ $venta->id }})">Editar</button>
    @endcan
    @can('delete', $venta)
        <button type="button" class="{{ $button }} text-destructive" wire:click="eliminar({{ $venta->id }})" wire:confirm="¿Eliminar esta venta?" wire:loading.attr="disabled">Eliminar</button>
    @endcan
</div>

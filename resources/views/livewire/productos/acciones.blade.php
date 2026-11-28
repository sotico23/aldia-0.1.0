<div class="flex flex-wrap items-center gap-1.5">
    <button type="button" class="{{ $button }}" wire:click="ver({{ $producto->id }})">Ver</button>
    @can('comercial.productos.edit')
        <button type="button" class="{{ $button }}" wire:click="editar({{ $producto->id }})">Editar</button>
    @endcan
    @can('comercial.productos.delete')
        <button type="button" class="{{ $button }} text-destructive" wire:click="eliminar({{ $producto->id }})" wire:confirm="¿Eliminar este producto?" wire:loading.attr="disabled">Eliminar</button>
    @endcan
</div>

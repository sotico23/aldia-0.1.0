<div class="flex flex-wrap items-center gap-1.5">
    @if($tiendaSlug && $categoria->mostrar_en_perfil && $categoria->public_profile_id)
        <a class="{{ $button }}" href="{{ url('/tienda/'.$tiendaSlug.'/categoria/'.$categoria->id) }}" target="_blank" rel="noopener">Ver en tienda</a>
    @endif
    @can('comercial.categorias.edit')
        <button type="button" class="{{ $button }}" wire:click="editar({{ $categoria->id }})">Editar</button>
    @endcan
    @can('comercial.categorias.delete')
        <button type="button" class="{{ $button }} text-destructive" wire:click="eliminar({{ $categoria->id }})" wire:confirm="¿Eliminar esta categoría?" wire:loading.attr="disabled">Eliminar</button>
    @endcan
</div>

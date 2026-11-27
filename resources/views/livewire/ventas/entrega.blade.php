<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" wire:keydown.escape.window="cerrarEntrega">
    <section class="max-h-[92dvh] w-full max-w-xl overflow-auto rounded-xl border border-border bg-card p-4 shadow-xl sm:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-envio-venta" x-trap.noscroll="true">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 id="titulo-envio-venta" class="text-xl font-bold">Enviar a reparto</h2>
            <button type="button" class="{{ $button }}" wire:click="cerrarEntrega">Cerrar</button>
        </div>
        <form wire:submit="enviarEntrega" class="space-y-3">
            <label class="grid gap-1 text-sm">Destinatario
                <input class="{{ $control }}" wire:model="envio.cliente" maxlength="255">
            </label>
            <label class="grid gap-1 text-sm">Dirección de entrega
                <input class="{{ $control }}" wire:model="envio.direccion" maxlength="1000" required>
            </label>
            <label class="grid gap-1 text-sm">Fecha de entrega
                <input class="{{ $control }}" type="date" wire:model="envio.fecha_entrega" required>
            </label>
            <label class="grid gap-1 text-sm">Asignación
                <select class="{{ $control }}" wire:model.live="envio.estado_asignacion">
                    <option value="asignado">Asignar un repartidor</option>
                    <option value="libre">Publicar en la bolsa de entregas</option>
                </select>
            </label>
            @if($envio['estado_asignacion'] === 'asignado')
                <label class="grid gap-1 text-sm">Repartidor
                    <select class="{{ $control }}" wire:model="envio.repartidor_id" required>
                        <option value="">{{ count($repartidores) ? 'Selecciona un repartidor' : 'Sin repartidores disponibles' }}</option>
                        @foreach($repartidores as $repartidor)
                            <option value="{{ $repartidor['id'] }}">{{ $repartidor['nombre'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="grid gap-1 text-sm">Instrucciones de entrega
                <textarea class="{{ $control }}" wire:model="envio.notas" rows="2" maxlength="1000"></textarea>
            </label>
            @if($errors->any())
                <ul class="list-inside list-disc text-sm text-destructive" role="alert">
                    @foreach(array_unique($errors->all()) as $mensajeError)
                        <li>{{ $mensajeError }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="flex flex-wrap justify-end gap-2 border-t border-border pt-3">
                <a class="{{ $button }}" href="{{ route('entregas.index', ['venta_id' => $ventaEntrega]) }}">Más opciones en Entregas</a>
                <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="enviarEntrega">Enviar</button>
            </div>
        </form>
    </section>
</div>

<div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-3" wire:keydown.escape.window="cerrarDetalle">
    <section class="max-h-[92dvh] w-full max-w-3xl space-y-4 overflow-auto rounded-xl border border-border bg-card p-4 shadow-xl sm:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-venta-detalle" x-trap.noscroll="true">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="titulo-venta-detalle" class="text-xl font-bold">{{ $ventaDetalle->numero }}</h2>
                <p class="text-sm text-muted-foreground">{{ $ventaDetalle->cliente?->nombre ?: 'Cliente general' }} · {{ $ventaDetalle->fecha?->format('d-m-Y') }}</p>
            </div>
            <button type="button" class="{{ $button }}" wire:click="cerrarDetalle">Cerrar</button>
        </div>
        @if($ventaDetalle->cliente?->telefono)
            <a class="{{ $button }}" href="https://wa.me/{{ preg_replace('/\D/', '', $ventaDetalle->cliente->telefono) }}" target="_blank" rel="noopener noreferrer">Contactar por WhatsApp</a>
        @endif
        <p class="text-sm"><strong>Almacén:</strong> {{ $ventaDetalle->almacenes->pluck('nombre')->join(', ') ?: ($ventaDetalle->almacen?->nombre ?: '—') }}</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50"><tr><th class="p-2">Producto</th><th class="p-2">Cantidad</th><th class="p-2">Precio</th><th class="p-2">Subtotal</th></tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse($ventaDetalle->detalleVentas as $linea)
                        <tr>
                            <td class="p-2">{{ $linea->producto?->nombre ?: 'Producto eliminado' }}</td>
                            <td class="p-2">{{ $linea->cantidad }}</td>
                            <td class="whitespace-nowrap p-2">{{ $money($linea->precio_unitario, $ventaDetalle->currency) }}</td>
                            <td class="whitespace-nowrap p-2">{{ $money($linea->subtotal, $ventaDetalle->currency) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-3 text-muted-foreground">Esta venta no tiene productos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <dl class="space-y-1 text-right text-sm">
            <div><dt class="inline text-muted-foreground">Subtotal</dt> <dd class="inline">{{ $money($ventaDetalle->subtotal, $ventaDetalle->currency) }}</dd></div>
            <div><dt class="inline text-muted-foreground">IVA</dt> <dd class="inline">{{ $money($ventaDetalle->iva, $ventaDetalle->currency) }}</dd></div>
            <div><dt class="inline text-muted-foreground">Descuento</dt> <dd class="inline">{{ $money($ventaDetalle->monto_descuento, $ventaDetalle->currency) }}</dd></div>
            <div class="font-bold"><dt class="inline">Total</dt> <dd class="inline">{{ $money($ventaDetalle->total, $ventaDetalle->currency) }}</dd></div>
        </dl>
        @if($ventaDetalle->notas)
            <p class="whitespace-pre-wrap rounded-md bg-muted p-3 text-sm">{{ $ventaDetalle->notas }}</p>
        @endif
        <div class="flex flex-wrap justify-end gap-2">
            <a class="{{ $button }}" href="{{ route('ventas.download', $ventaDetalle) }}">PDF formal SII</a>
            <a class="{{ $button }}" href="{{ route('ventas.download-informal', $ventaDetalle) }}">PDF informal</a>
        </div>
    </section>
</div>


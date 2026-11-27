@php
    $cliente = $ventaDetalle->cliente;
    $estadoLabel = $states[$ventaDetalle->estado] ?? ucfirst($ventaDetalle->estado);
    $tipoDocumento = [
        'factura' => 'Factura electrónica',
        'boleta' => 'Boleta electrónica',
        'nota_credito' => 'Nota de crédito',
        'cotizacion' => 'Cotización',
    ][$ventaDetalle->tipo_documento] ?? 'Orden de venta';
    $almacenesLabel = $ventaDetalle->almacenes->pluck('nombre')->join(', ')
        ?: ($ventaDetalle->almacen?->nombre ?: '—');
@endphp

<div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm" wire:keydown.escape.window="cerrarDetalle">
    <section class="max-h-[92dvh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="titulo-venta-detalle" x-trap.noscroll="true">
        <header class="relative overflow-hidden bg-gradient-to-r from-violet-700 via-purple-700 to-teal-700 px-6 py-6 text-white">
            <span aria-hidden="true" class="pointer-events-none absolute -right-2 top-1/2 -translate-y-1/2 select-none text-[130px] leading-none opacity-10">🧾</span>
            <button type="button" wire:click="cerrarDetalle" aria-label="Cerrar detalle" class="absolute right-4 top-4 rounded-full bg-white/15 p-1.5 text-white transition hover:bg-white/30">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 0 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
            <span class="inline-flex rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-widest">Detalle de venta</span>
            <h2 id="titulo-venta-detalle" class="mt-3 text-2xl font-black uppercase tracking-tight sm:text-3xl">{{ $ventaDetalle->numero }}</h2>
            <p class="mt-1 text-sm text-white/80">{{ $cliente?->nombre ?: 'Cliente general' }} · {{ $ventaDetalle->fecha?->format('d-m-Y') }}</p>
        </header>

        <div class="max-h-[62dvh] space-y-5 overflow-y-auto px-6 py-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-violet-500">Tipo de documento</p>
                    <p class="mt-2 font-bold text-violet-900">{{ $tipoDocumento }}</p>
                    @if($ventaDetalle->folio)
                        <p class="mt-1 text-xs text-violet-700">Folio {{ $ventaDetalle->folio }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Estado</p>
                    <p class="mt-2 font-bold uppercase text-emerald-700">{{ $estadoLabel }}</p>
                </div>
                <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-teal-600">Total de la venta</p>
                    <p class="mt-2 text-xl font-black text-teal-800">{{ $money($ventaDetalle->total, $ventaDetalle->currency) }}</p>
                    <p class="mt-1 text-xs text-teal-700">{{ $ventaDetalle->currency }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <h3 class="font-bold text-slate-800">Productos</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $ventaDetalle->detalleVentas->count() }} ítems</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-[10px] uppercase tracking-wider text-slate-500">
                                <tr><th class="pb-2">Producto</th><th class="pb-2 text-center">Cant.</th><th class="pb-2 text-right">Precio</th><th class="pb-2 text-right">Subtotal</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($ventaDetalle->detalleVentas as $linea)
                                    <tr>
                                        <td class="py-2 pr-2 text-slate-800">{{ $linea->producto?->nombre ?: 'Producto eliminado' }}</td>
                                        <td class="py-2 text-center text-slate-600">{{ $linea->cantidad }}</td>
                                        <td class="whitespace-nowrap py-2 text-right text-slate-600">{{ $money($linea->precio_unitario, $ventaDetalle->currency) }}</td>
                                        <td class="whitespace-nowrap py-2 text-right font-semibold text-slate-800">{{ $money($linea->subtotal, $ventaDetalle->currency) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-4 text-center text-sm text-slate-500">Esta venta no tiene productos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-3 border-b border-slate-100 pb-3 font-bold text-slate-800">Cliente</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Nombre</dt><dd class="text-right font-semibold text-slate-800">{{ $cliente?->nombre ?: 'Cliente general' }}</dd></div>
                            @if($cliente?->rut)
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">RUT</dt><dd class="font-semibold text-slate-800">{{ $cliente->rut }}</dd></div>
                            @endif
                            @if($cliente?->telefono)
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Teléfono</dt><dd class="font-semibold text-slate-800">{{ $cliente->telefono }}</dd></div>
                            @endif
                            @if($cliente?->email)
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="break-all text-right font-semibold text-slate-800">{{ $cliente->email }}</dd></div>
                            @endif
                            @if($cliente?->direccion || $cliente?->comuna)
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Dirección</dt><dd class="text-right font-semibold text-slate-800">{{ trim(($cliente->direccion ?: '').' '.($cliente->comuna ?: '')) ?: '—' }}</dd></div>
                            @endif
                        </dl>
                        @if($cliente?->telefono)
                            <a class="{{ $button }} mt-4 w-full justify-center" href="https://wa.me/{{ preg_replace('/\D/', '', $cliente->telefono) }}" target="_blank" rel="noopener noreferrer">Contactar por WhatsApp</a>
                        @endif
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-3 border-b border-slate-100 pb-3 font-bold text-slate-800">Resumen</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Fecha</dt><dd class="font-semibold text-slate-800">{{ $ventaDetalle->fecha?->format('d-m-Y H:i') }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Almacén</dt><dd class="text-right font-semibold text-slate-800">{{ $almacenesLabel }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Método de pago</dt><dd class="font-semibold text-slate-800">{{ $ventaDetalle->metodo_pago ?: '—' }}</dd></div>
                            <div class="flex justify-between gap-3 border-t border-slate-100 pt-2"><dt class="text-slate-500">Subtotal</dt><dd class="font-semibold text-slate-800">{{ $money($ventaDetalle->subtotal, $ventaDetalle->currency) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">IVA</dt><dd class="font-semibold text-slate-800">{{ $money($ventaDetalle->iva, $ventaDetalle->currency) }}</dd></div>
                            @if($ventaDetalle->monto_descuento > 0)
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Descuento</dt><dd class="font-semibold text-rose-600">-{{ $money($ventaDetalle->monto_descuento, $ventaDetalle->currency) }}</dd></div>
                            @endif
                            @if($ventaDetalle->delivery_cost > 0)
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Despacho</dt><dd class="font-semibold text-slate-800">{{ $money($ventaDetalle->delivery_cost, $ventaDetalle->currency) }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3 border-t border-slate-200 pt-2 text-base"><dt class="font-bold text-slate-700">Total</dt><dd class="font-black text-slate-900">{{ $money($ventaDetalle->total, $ventaDetalle->currency) }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>

            @if($ventaDetalle->delivery_address || $ventaDetalle->delivery_recipient_name)
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-sky-600">Entrega a domicilio</p>
                    <p class="mt-2 text-sm text-sky-900">
                        {{ $ventaDetalle->delivery_recipient_name ?: $cliente?->nombre ?: 'Cliente general' }}
                        @if($ventaDetalle->delivery_phone) · {{ $ventaDetalle->delivery_phone }} @endif
                    </p>
                    <p class="mt-1 text-sm text-sky-800">{{ trim($ventaDetalle->delivery_address.' '.($ventaDetalle->delivery_commune ?: '')) }}</p>
                </div>
            @endif

            @if($ventaDetalle->notas)
                <div class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <span class="text-lg text-amber-500" aria-hidden="true">✅</span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-amber-700">Notas de la venta</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-amber-900">{{ $ventaDetalle->notas }}</p>
                    </div>
                </div>
            @endif
        </div>

        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <div class="flex flex-wrap gap-2">
                <a class="{{ $button }}" href="{{ route('ventas.download', $ventaDetalle) }}">PDF formal SII</a>
                <a class="{{ $button }}" href="{{ route('ventas.download-informal', $ventaDetalle) }}">PDF informal</a>
                @if(\Illuminate\Support\Facades\Route::has('ventas.ticket'))
                    <a class="{{ $primary }}" href="{{ route('ventas.ticket', $ventaDetalle) }}" target="_blank" rel="noopener">Ticket térmico</a>
                @endif
            </div>
            <button type="button" class="{{ $button }} bg-white" wire:click="cerrarDetalle">Cerrar detalle</button>
        </footer>
    </section>
</div>

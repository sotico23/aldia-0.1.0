@php
    $tituloModal = $editando ? 'Editar venta' : 'Nueva venta';
    $etiquetaModal = $editando ? 'Edición de venta' : 'Registro de venta';
@endphp

<div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm" wire:keydown.escape.window="cerrarFormulario">
    <section class="max-h-[92dvh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="titulo-formulario-venta" x-trap.noscroll="true">
        <header class="relative overflow-hidden bg-gradient-to-r from-violet-700 via-purple-700 to-teal-700 px-6 py-6 text-white">
            <span aria-hidden="true" class="pointer-events-none absolute -right-2 top-1/2 -translate-y-1/2 select-none text-[130px] leading-none opacity-10">✎</span>
            <button type="button" wire:click="cerrarFormulario" aria-label="Cerrar formulario" class="absolute right-4 top-4 rounded-full bg-white/15 p-1.5 text-white transition hover:bg-white/30">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 0 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
            <span class="inline-flex rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-widest">{{ $etiquetaModal }}</span>
            <h2 id="titulo-formulario-venta" class="mt-3 text-2xl font-black uppercase tracking-tight sm:text-3xl">{{ $tituloModal }}</h2>
            <p class="mt-1 text-sm text-white/80">Completa la información comercial, los productos y los datos de entrega.</p>
        </header>

        <form wire:submit="guardar">
            <div class="max-h-[62dvh] space-y-4 overflow-y-auto px-6 py-5">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-4 border-b border-slate-100 pb-3 text-sm font-bold uppercase tracking-wide text-slate-700">Datos generales</h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="grid gap-1 text-sm">Número de venta
                            <input class="{{ $control }}" wire:model="form.numero_factura" required maxlength="50">
                        </label>
                        <label class="grid gap-1 text-sm">Fecha
                            <input class="{{ $control }}" type="date" wire:model="form.fecha" required>
                        </label>
                        <label class="grid gap-1 text-sm">Estado
                            <select class="{{ $control }}" wire:model="form.estado">
                                @unless(in_array($form['estado'], ['pagada', 'pendiente', 'cancelada'], true))
                                    <option value="{{ $form['estado'] }}">{{ $states[$form['estado']] ?? ucfirst($form['estado']) }}</option>
                                @endunless
                                @foreach(['pagada', 'pendiente', 'cancelada'] as $opcionEstado)
                                    <option value="{{ $opcionEstado }}">{{ $states[$opcionEstado] }}</option>
                                @endforeach
                            </select>
                        </label>
                        @unless($editando)
                            <label class="grid gap-1 text-sm">Documento
                                <select class="{{ $control }}" wire:model="form.tipo_documento">
                                    <option value="boleta">Boleta</option>
                                    <option value="factura">Factura</option>
                                    <option value="nota_credito">Nota de crédito</option>
                                    <option value="cotizacion">Cotización</option>
                                </select>
                            </label>
                            <label class="grid gap-1 text-sm">Tipo de cliente
                                <select class="{{ $control }}" wire:model.live="form.cliente_tipo">
                                    <option value="existente">Existente</option>
                                    <option value="generico">Genérico</option>
                                </select>
                            </label>
                        @endunless
                        @if($form['cliente_tipo'] === 'generico')
                            @foreach(['cliente_nombre' => 'Nombre o razón social', 'cliente_rut' => 'RUT', 'cliente_telefono' => 'Teléfono', 'cliente_direccion' => 'Dirección'] as $campoCliente => $label)
                                <label class="grid gap-1 text-sm">{{ $label }}
                                    <input class="{{ $control }}" wire:model="form.{{ $campoCliente }}" @required($campoCliente === 'cliente_nombre')>
                                </label>
                            @endforeach
                        @else
                            <label class="grid gap-1 text-sm">Cliente
                                <select class="{{ $control }}" wire:model="form.cliente_id" @required(! $editando)>
                                    <option value="">Selecciona un cliente</option>
                                    @foreach($clientes as $opcion)
                                        <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="grid gap-1 text-sm">Moneda
                            <select class="{{ $control }}" wire:model.live="form.currency">
                                @foreach(\App\Enums\Currency::cases() as $opcionMoneda)
                                    <option value="{{ $opcionMoneda->value }}">{{ $opcionMoneda->value }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 border-b border-slate-100 pb-3 text-sm font-bold uppercase tracking-wide text-slate-700">Almacenes</h3>
                    <div class="flex flex-wrap gap-3">
                        @forelse($almacenes as $almacen)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="form.almacen_ids" value="{{ $almacen->id }}">
                                {{ $almacen->nombre }}
                            </label>
                        @empty
                            <p class="text-sm text-muted-foreground">No hay almacenes activos.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 border-b border-slate-100 pb-3 text-sm font-bold uppercase tracking-wide text-slate-700">Productos</h3>
                    <div class="space-y-2">
                        @foreach($form['productos'] ?? [] as $indice => $item)
                            @php($productoElegido = $productos->firstWhere('id', (int) $item['producto_id']))
                            <div class="grid items-end gap-2 rounded-lg border border-slate-200 bg-slate-50/70 p-3 sm:grid-cols-2 lg:grid-cols-5" wire:key="form-producto-{{ $indice }}">
                                <label class="grid min-w-0 gap-1 text-sm">Producto
                                    <select class="{{ $control }} w-full" wire:model.live="form.productos.{{ $indice }}.producto_id" required>
                                        <option value="">Selecciona un producto</option>
                                        @foreach($productos as $producto)
                                            <option value="{{ $producto->id }}">{{ $producto->nombre }} · {{ $producto->codigo }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="grid gap-1 text-sm">Cantidad
                                    <input class="{{ $control }} w-full" type="number" min="0.001" step="0.001" wire:model.blur="form.productos.{{ $indice }}.cantidad" required>
                                </label>
                                <label class="grid gap-1 text-sm">Precio unitario
                                    <input class="{{ $control }} w-full" type="number" min="0" step="0.01" wire:model.blur="form.productos.{{ $indice }}.precio_unitario" required>
                                </label>
                                @if($productoElegido?->envase_retornable)
                                    <label class="grid gap-1 text-sm">Envases devueltos
                                        <input class="{{ $control }} w-full" type="number" min="0" step="1" wire:model="form.productos.{{ $indice }}.cantidad_retornada">
                                    </label>
                                @endif
                                <button type="button" class="{{ $button }} text-destructive" wire:click="quitarProducto({{ $indice }})">Quitar</button>
                            </div>
                        @endforeach
                        <button type="button" class="{{ $button }}" wire:click="agregarProducto">Agregar producto</button>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-4 border-b border-slate-100 pb-3 text-sm font-bold uppercase tracking-wide text-slate-700">Descuentos y notas</h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="grid gap-1 text-sm">Tipo de descuento
                            <select class="{{ $control }}" wire:model="form.tipo_descuento">
                                <option value="monto">Monto</option>
                                <option value="porcentaje">Porcentaje</option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm">Descuento
                            <input type="number" min="0" step="0.01" class="{{ $control }}" wire:model="form.valor_descuento">
                        </label>
                        @unless($editando)
                            <label class="grid gap-1 text-sm">Cupón
                                <input class="{{ $control }}" wire:model="form.cupon_codigo" maxlength="50">
                            </label>
                        @endunless
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="form.incluye_iva"> Incluir IVA
                        </label>
                    </div>
                    <label class="mt-3 grid gap-1 text-sm">Notas
                        <textarea class="{{ $control }}" wire:model="form.notas" rows="2"></textarea>
                    </label>
                </div>

                @if($errors->any())
                    <ul class="list-inside list-disc rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive" role="alert">
                        @foreach(array_unique($errors->all()) as $mensajeError)
                            <li>{{ $mensajeError }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <p class="text-sm text-muted-foreground">Productos: <strong class="text-foreground">{{ $money(collect($form['productos'] ?? [])->sum(fn ($item) => (float) $item['cantidad'] * (float) $item['precio_unitario']), $form['currency']) }}</strong></p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="{{ $button }} bg-white" wire:click="cerrarFormulario">Cerrar</button>
                    <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">Guardar venta</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </footer>
        </form>
    </section>
</div>

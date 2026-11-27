<div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-3" wire:keydown.escape.window="cerrarFormulario">
    <section class="max-h-[92dvh] w-full max-w-4xl overflow-auto rounded-xl border border-border bg-card p-4 shadow-xl sm:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-formulario-venta" x-trap.noscroll="true">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 id="titulo-formulario-venta" class="text-xl font-bold">{{ $editando ? 'Editar venta' : 'Nueva venta' }}</h2>
            <button type="button" class="{{ $button }}" wire:click="cerrarFormulario">Cerrar</button>
        </div>
        <form wire:submit="guardar" class="space-y-4">
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

            <fieldset class="space-y-2">
                <legend class="font-semibold">Almacenes</legend>
                <div class="flex flex-wrap gap-3">
                    @forelse($almacenes as $almacen)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="form.almacen_ids" value="{{ $almacen->id }}">
                            {{ $almacen->nombre }}
                        </label>
                    @empty
                        <p class="text-sm text-muted-foreground">No hay almacenes activos.</p>
                    @endforelse
                </div>
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="font-semibold">Productos</legend>
                @foreach($form['productos'] ?? [] as $indice => $item)
                    @php($productoElegido = $productos->firstWhere('id', (int) $item['producto_id']))
                    <div class="grid items-end gap-2 rounded-lg border border-border p-3 sm:grid-cols-2 lg:grid-cols-5" wire:key="form-producto-{{ $indice }}">
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
            </fieldset>

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
            <label class="grid gap-1 text-sm">Notas
                <textarea class="{{ $control }}" wire:model="form.notas" rows="2"></textarea>
            </label>

            @if($errors->any())
                <ul class="list-inside list-disc rounded-md border border-destructive/30 p-3 text-sm text-destructive" role="alert">
                    @foreach(array_unique($errors->all()) as $mensajeError)
                        <li>{{ $mensajeError }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
                <p class="text-sm text-muted-foreground">Productos: <strong class="text-foreground">{{ $money(collect($form['productos'] ?? [])->sum(fn ($item) => (float) $item['cantidad'] * (float) $item['precio_unitario']), $form['currency']) }}</strong></p>
                <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Guardar venta</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>
        </form>
    </section>
</div>

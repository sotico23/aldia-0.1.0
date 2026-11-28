@php
    $tituloModal = $editando ? 'Editar producto' : 'Nuevo producto';
    $etiquetaModal = $editando ? 'Modificación de ficha' : 'Alta de producto';
@endphp
<div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm" wire:keydown.escape.window="cerrar">
    <section class="max-h-[92dvh] w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="producto-formulario-titulo" x-trap.noscroll="true">
        <header class="relative overflow-hidden bg-gradient-to-r from-violet-700 via-purple-700 to-teal-700 px-6 py-6 text-white">
            <span aria-hidden="true" class="pointer-events-none absolute -right-2 top-1/2 -translate-y-1/2 select-none text-[130px] leading-none opacity-10">📦</span>
            <button type="button" wire:click="cerrar" aria-label="Cerrar formulario" class="absolute right-4 top-4 rounded-full bg-white/15 p-1.5 text-white transition hover:bg-white/30">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 0 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
            </button>
            <span class="inline-flex rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-widest">{{ $etiquetaModal }}</span>
            <h2 id="producto-formulario-titulo" class="mt-3 text-2xl font-black uppercase tracking-tight sm:text-3xl">{{ $tituloModal }}</h2>
            <p class="mt-1 text-sm text-white/80">Complete identificación, precios, inventario, variantes e imágenes.</p>
        </header>

        <form wire:submit="guardar">
            <div class="max-h-[62dvh] space-y-4 overflow-y-auto px-6 py-5">
                @if($mensaje)
                    <p class="rounded-md border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-700" role="status">{{ $mensaje }}</p>
                @endif

                <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-violet-500">Identificación</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="grid gap-1 text-sm text-violet-900">Código / SKU
                            <div class="flex gap-2">
                                <input class="{{ $control }} w-full" wire:model="form.codigo" maxlength="50" placeholder="Ej: PRO-0001">
                                <button type="button" class="{{ $button }}" wire:click="buscarCodigo" wire:loading.attr="disabled" wire:target="buscarCodigo">Buscar</button>
                            </div>
                            @error('form.codigo') <span class="text-xs text-destructive" role="alert">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm text-violet-900">Nombre comercial *
                            <input class="{{ $control }}" wire:model="form.nombre" required maxlength="255">
                            @error('form.nombre') <span class="text-xs text-destructive" role="alert">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm text-violet-900">Categoría
                            <select class="{{ $control }}" wire:model="form.categoria_id">
                                <option value="">Sin categoría</option>
                                @foreach($categorias as $categoria)
                                    <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm text-violet-900 sm:col-span-2 lg:col-span-3">Descripción
                            <textarea class="{{ $control }}" wire:model="form.descripcion" rows="2"></textarea>
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-emerald-600">Precios e inventario</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="grid gap-1 text-sm text-emerald-900">Precio de compra
                            <input class="{{ $control }}" type="number" min="0" step="0.01" wire:model="form.precio_compra">
                            @error('form.precio_compra') <span class="text-xs text-destructive" role="alert">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm text-emerald-900">Precio de venta
                            <input class="{{ $control }}" type="number" min="0" step="0.01" wire:model="form.precio_venta">
                            @error('form.precio_venta') <span class="text-xs text-destructive" role="alert">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm text-emerald-900">Comisión por unidad
                            <input class="{{ $control }}" type="number" min="0" step="0.01" wire:model="form.comision_cajero_por_unidad">
                        </label>
                        <label class="grid gap-1 text-sm text-emerald-900">Stock mínimo
                            <input class="{{ $control }}" type="number" min="0" wire:model="form.stock_minimo">
                        </label>
                        <label class="grid gap-1 text-sm text-emerald-900">Stock inicial
                            <input class="{{ $control }}" type="number" min="0" step="0.001" wire:model="form.stock" @disabled($form['tiene_variantes'] ?? false)>
                        </label>
                        <label class="grid gap-1 text-sm text-emerald-900">Fecha de vencimiento
                            <input class="{{ $control }}" type="date" wire:model="form.fecha_vencimiento">
                        </label>
                    </div>
                    <div class="mt-4">
                        <p class="mb-2 text-xs font-semibold text-emerald-800">Almacenes</p>
                        <div class="flex flex-wrap gap-2">
                            @forelse($almacenes as $almacen)
                                <label class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm text-emerald-900">
                                    <input type="checkbox" value="{{ $almacen->id }}" wire:model="form.warehouse_ids"> {{ $almacen->nombre }}
                                </label>
                            @empty
                                <p class="text-sm text-emerald-700">No hay almacenes activos.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-teal-600">Unidad, medida y envase</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="grid gap-1 text-sm text-teal-900">Unidad de medida
                            <select class="{{ $control }}" wire:model.live="form.unidad_medida">
                                <option value="unidad">Unidad</option>
                                <option value="kg">Kilogramo</option>
                                <option value="lt">Litro</option>
                            </select>
                        </label>
                        <label class="flex items-center gap-2 self-end text-sm text-teal-900">
                            <input type="checkbox" wire:model.live="form.medida_pesable"> Producto pesable / métrico
                        </label>
                        <label class="grid gap-1 text-sm text-teal-900">Tipo de medida
                            <select class="{{ $control }}" wire:model="form.tipo_medida">
                                <option value="unidad">Unidad</option>
                                <option value="kilo">Kilo</option>
                                <option value="litro">Litro</option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm text-teal-900">Cantidad por medida
                            <input class="{{ $control }}" type="number" min="0" step="0.01" wire:model="form.cantidad_medida">
                        </label>
                        <label class="grid gap-1 text-sm text-teal-900">Contenido por unidad
                            <input class="{{ $control }}" type="number" min="0" step="0.01" wire:model="form.contenido_por_unidad">
                        </label>
                        <label class="grid gap-1 text-sm text-teal-900">Peso base
                            <input class="{{ $control }}" type="number" min="0" step="0.01" wire:model="form.peso_base">
                        </label>
                        <label class="flex items-center gap-2 text-sm text-teal-900">
                            <input type="checkbox" wire:model.live="form.envase_retornable"> Envase retornable
                        </label>
                        <div class="col-span-full space-y-3 rounded-lg border border-slate-200 p-3">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="form.envio_gratis">
                                Envío gratis
                            </label>
                            @if($form['envio_gratis'] ?? false)
                                <label class="block">
                                    Comuna con envío gratis
                                    <input class="{{ $control }}" wire:model.live.blur="form.envio_gratis_comuna" maxlength="100" placeholder="Vacío para todas las comunas">
                                </label>
                                @error('form.envio_gratis_comuna') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @endif
                            @if(!($form['envio_gratis'] ?? false) || filled($form['envio_gratis_comuna'] ?? null))
                                <label class="block">
                                    {{ ($form['envio_gratis'] ?? false) ? 'Tarifa para otras comunas (CLP)' : 'Tarifa de envío (CLP)' }}
                                    <input class="{{ $control }}" type="number" min="0" max="99999999" step="1" wire:model="form.precio_envio">
                                </label>
                                @error('form.precio_envio') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @endif
                        </div>
                        @if($form['envase_retornable'] ?? false)
                            <label class="grid gap-1 text-sm text-teal-900">Producto envase
                                <select class="{{ $control }}" wire:model="form.envase_producto_id">
                                    <option value="">Asignar automáticamente</option>
                                    <option value="self">El mismo producto</option>
                                    @foreach($productosEnvase as $envase)
                                        <option value="{{ $envase->id }}">{{ $envase->nombre }} · {{ $envase->codigo }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="grid gap-1 text-sm text-teal-900">Tipo de envase
                                <input class="{{ $control }}" wire:model="form.tipo_envase" maxlength="50">
                            </label>
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <input type="checkbox" wire:model.live="form.tiene_variantes"> Este producto tiene variantes
                    </label>

                    @if($form['tiene_variantes'] ?? false)
                        <div class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                            <div>
                                <p class="mb-2 text-xs font-semibold text-slate-700">Variantes existentes</p>
                                <div class="flex flex-wrap gap-2">
                                    @forelse($variantesDisponibles as $variante)
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                            <input type="checkbox" value="{{ $variante->id }}" wire:model.live="form.variante_ids"> {{ $variante->nombre }}
                                            <span class="text-xs text-muted-foreground">({{ $variante->valores->pluck('valor')->join(', ') }})</span>
                                        </label>
                                    @empty
                                        <p class="text-sm text-muted-foreground">No hay variantes creadas.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold text-slate-700">Nuevas variantes</p>
                                    <button type="button" class="{{ $button }}" wire:click="agregarVariante">Agregar variante</button>
                                </div>
                                @foreach($form['variantes'] ?? [] as $i => $variante)
                                    <div class="grid gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[1fr_120px_2fr_auto]" wire:key="variante-{{ $i }}">
                                        <input class="{{ $control }}" wire:model="form.variantes.{{ $i }}.nombre" placeholder="Nombre (Ej: Color)">
                                        <select class="{{ $control }}" wire:model="form.variantes.{{ $i }}.tipo">
                                            <option value="texto">Texto</option>
                                            <option value="color">Color</option>
                                            <option value="numero">Número</option>
                                        </select>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($variante['valores'] ?? [] as $j => $valor)
                                                <div class="flex items-center gap-1">
                                                    <input class="{{ $control }} w-28" wire:model="form.variantes.{{ $i }}.valores.{{ $j }}" placeholder="Valor">
                                                    <button type="button" class="text-xs text-destructive" wire:click="quitarValor({{ $i }}, {{ $j }})">×</button>
                                                </div>
                                            @endforeach
                                            <button type="button" class="{{ $button }}" wire:click="agregarValor({{ $i }})">+ Valor</button>
                                        </div>
                                        <button type="button" class="text-xs text-destructive" wire:click="quitarVariante({{ $i }})">Quitar</button>
                                    </div>
                                @endforeach
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-semibold text-slate-700">Combinaciones SKU ({{ count($form['skus'] ?? []) }})</p>
                                    <button type="button" class="{{ $button }}" wire:click="regenerarSkus">Generar combinaciones</button>
                                </div>
                                @if(empty($form['skus']))
                                    <p class="text-sm text-muted-foreground">Selecciona variantes y genera las combinaciones.</p>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead class="border-b border-slate-200 text-slate-500"><tr><th class="p-2">SKU</th><th class="p-2">Variante</th><th class="p-2">Precio</th><th class="p-2">Compra</th><th class="p-2">Stock</th><th class="p-2">Mín.</th><th class="p-2">Almacén</th></tr></thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach($form['skus'] as $i => $sku)
                                                    <tr wire:key="sku-{{ $i }}">
                                                        <td class="p-1"><input class="{{ $control }} w-36" wire:model="form.skus.{{ $i }}.sku"></td>
                                                        <td class="p-2 text-slate-700">{{ $sku['etiqueta'] ?? '—' }}</td>
                                                        <td class="p-1"><input class="{{ $control }} w-24" type="number" min="0" step="0.01" wire:model="form.skus.{{ $i }}.precio_venta"></td>
                                                        <td class="p-1"><input class="{{ $control }} w-24" type="number" min="0" step="0.01" wire:model="form.skus.{{ $i }}.precio_compra"></td>
                                                        <td class="p-1"><input class="{{ $control }} w-20" type="number" min="0" step="0.001" wire:model="form.skus.{{ $i }}.stock"></td>
                                                        <td class="p-1"><input class="{{ $control }} w-20" type="number" min="0" step="0.001" wire:model="form.skus.{{ $i }}.stock_minimo"></td>
                                                        <td class="p-1">
                                                            <select class="{{ $control }} w-32" wire:model="form.skus.{{ $i }}.almacen_id">
                                                                <option value="">—</option>
                                                                @foreach($almacenes as $almacen)
                                                                    <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-amber-700">Imágenes, video y publicación</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach(['imagen' => 'Imagen principal', 'imagen2' => 'Imagen 2', 'imagen3' => 'Imagen 3', 'imagen4' => 'Imagen 4', 'imagen5' => 'Imagen 5'] as $key => $label)
                            <label class="grid gap-1 text-sm text-amber-900">{{ $label }}
                                @if($editando && ! empty($imagenesExistentes[$key]))
                                    <label class="flex items-center gap-2 text-xs">
                                        <input type="checkbox" value="{{ $imagenesExistentes[$key] }}" wire:model="imagenesExistentes.{{ $key }}"> Conservar actual
                                    </label>
                                @endif
                                <input class="{{ $control }}" type="file" accept="image/*" wire:model="imagenes.{{ $loop->index }}">
                                @error('imagenes.'.$loop->index) <span class="text-xs text-destructive" role="alert">{{ $message }}</span> @enderror
                            </label>
                        @endforeach
                        <label class="grid gap-1 text-sm text-amber-900">Video
                            <input class="{{ $control }}" type="file" accept="video/*" wire:model="video">
                        </label>
                        <label class="grid gap-1 text-sm text-amber-900">Estado
                            <select class="{{ $control }}" wire:model="form.activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm text-amber-900">Marketplace
                            <select class="{{ $control }}" wire:model="form.mostrar_en_perfil">
                                <option value="1">Publicado en perfil</option>
                                <option value="0">Oculto</option>
                            </select>
                        </label>
                    </div>
                </div>

                @if($errors->any())
                    <ul class="list-inside list-disc rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive" role="alert">
                        @foreach(array_unique($errors->all()) as $mensajeError)
                            <li>{{ $mensajeError }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" class="{{ $button }} bg-white" wire:click="cerrar">Cerrar</button>
                <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Guardar producto</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </footer>
        </form>
    </section>
</div>

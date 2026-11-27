@php
    $tituloModal = $editando === null ? 'Nueva categoría' : 'Editar categoría';
    $etiquetaModal = $editando === null ? 'Registro de categoría' : 'Edición de categoría';
@endphp

<div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm" wire:keydown.escape.window="cerrarFormulario">
    <section class="max-h-[92dvh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="titulo-formulario-categoria" x-trap.noscroll="true">
        <header class="relative overflow-hidden bg-gradient-to-r from-violet-700 via-purple-700 to-teal-700 px-6 py-6 text-white">
            <span aria-hidden="true" class="pointer-events-none absolute -right-2 top-1/2 -translate-y-1/2 select-none text-[130px] leading-none opacity-10">🏷️</span>
            <button type="button" wire:click="cerrarFormulario" aria-label="Cerrar formulario" class="absolute right-4 top-4 rounded-full bg-white/15 p-1.5 text-white transition hover:bg-white/30">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 0 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
            <span class="inline-flex rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-widest">{{ $etiquetaModal }}</span>
            <h2 id="titulo-formulario-categoria" class="mt-3 text-2xl font-black uppercase tracking-tight sm:text-3xl">{{ $tituloModal }}</h2>
            <p class="mt-1 text-sm text-white/80">Define la clasificación, el estado y su visibilidad en el marketplace.</p>
        </header>

        <form wire:submit="guardar">
            <div class="max-h-[62dvh] space-y-4 overflow-y-auto px-6 py-5">
                <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-violet-500">Datos de la categoría</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm">Nombre
                            <input class="{{ $control }}" wire:model="form.nombre" required maxlength="255" placeholder="Ej: Bebidas">
                            @error('form.nombre') <span class="text-xs text-destructive" role="alert">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm">Tipo
                            <select class="{{ $control }}" wire:model="form.tipo" required>
                                @foreach(\App\Models\Categoria::TIPOS as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            @error('form.tipo') <span class="text-xs text-destructive" role="alert">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm sm:col-span-2">Descripción
                            <textarea class="{{ $control }}" wire:model="form.descripcion" rows="3" placeholder="Descripción visible en la tienda"></textarea>
                        </label>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Estado</p>
                        <label class="mt-3 flex items-center gap-2 text-sm text-emerald-900">
                            <input type="checkbox" wire:model="form.activo"> Categoría activa
                        </label>
                    </div>
                    <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-teal-600">Marketplace</p>
                        <label class="mt-3 flex items-center gap-2 text-sm text-teal-900">
                            <input type="checkbox" wire:model="form.mostrar_en_perfil"> Mostrar en el perfil público
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Imagen representativa</p>
                    <input class="{{ $control }} mt-3 w-full" type="file" wire:model="imagen" accept="image/*">
                    @error('imagen') <span class="text-xs text-destructive" role="alert">{{ $message }}</span> @enderror
                    <p class="mt-2 text-xs text-muted-foreground">JPG, PNG o WEBP · máximo 2 MB.</p>
                    <div class="mt-2" wire:loading wire:target="imagen" role="status">Procesando imagen…</div>
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
                <button type="button" class="{{ $button }} bg-white" wire:click="cerrarFormulario">Cerrar</button>
                <button type="submit" class="{{ $primary }}" wire:loading.attr="disabled" wire:target="guardar">
                    <span wire:loading.remove wire:target="guardar">Guardar categoría</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </footer>
        </form>
    </section>
</div>

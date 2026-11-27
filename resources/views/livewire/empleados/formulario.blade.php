@php
    $grupos = [
        'Datos personales' => [
            'nombre' => ['Nombre'], 'apellido' => ['Apellido'], 'rut' => ['RUT'],
            'fecha_nacimiento' => ['Fecha de nacimiento', 'date'], 'nacionalidad' => ['Nacionalidad'], 'estado_civil' => ['Estado civil'],
            'email' => ['Correo electrónico', 'email'], 'telefono' => ['Teléfono', 'tel'], 'direccion' => ['Dirección'], 'comuna' => ['Comuna'],
        ],
        'Contrato y jornada' => [
            'departamento' => ['Departamento'], 'tipo_contrato' => ['Tipo de contrato'], 'fecha_contratacion' => ['Fecha de contratación', 'date'],
            'salario' => ['Sueldo bruto', 'number'], 'sueldo_liquido_pactado' => ['Sueldo líquido pactado', 'number'],
            'hora_entrada' => ['Hora de entrada', 'time'], 'hora_salida' => ['Hora de salida', 'time'],
        ],
        'Previsión y datos bancarios' => [
            'afp' => ['AFP'], 'sistema_salud' => ['Sistema de salud'], 'isapre_nombre' => ['Isapre'],
            'banco_nombre' => ['Banco'], 'banco_tipo_cuenta' => ['Tipo de cuenta'], 'banco_numero_cuenta' => ['Número de cuenta'],
        ],
    ];
    $estilos = [
        ['border-violet-200 bg-violet-50', 'text-violet-500', 'text-violet-900'],
        ['border-emerald-200 bg-emerald-50', 'text-emerald-600', 'text-emerald-900'],
        ['border-teal-200 bg-teal-50', 'text-teal-600', 'text-teal-900'],
    ];
    $tituloModal = $empleadoId ? 'Editar empleado' : 'Nuevo empleado';
    $etiquetaModal = $empleadoId ? 'Edición de empleado' : 'Registro de empleado';
@endphp
<div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm" wire:keydown.escape.window="cerrar">
    <section class="max-h-[92dvh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="empleado-formulario" x-trap.noscroll="true">
        <header class="relative overflow-hidden bg-gradient-to-r from-violet-700 via-purple-700 to-teal-700 px-6 py-6 text-white">
            <span aria-hidden="true" class="pointer-events-none absolute -right-2 top-1/2 -translate-y-1/2 select-none text-[130px] leading-none opacity-10">👤</span>
            <button class="absolute right-4 top-4 rounded-full bg-white/15 p-1.5 text-white transition hover:bg-white/30" type="button" wire:click="cerrar" aria-label="Cerrar formulario">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 0 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
            <span class="inline-flex rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-widest">{{ $etiquetaModal }}</span>
            <h2 class="mt-3 text-2xl font-black uppercase tracking-tight sm:text-3xl" id="empleado-formulario">{{ $tituloModal }}</h2>
            <p class="mt-1 text-sm text-white/80">Datos personales, contrato, previsión y acceso a la plataforma.</p>
        </header>

        <form wire:submit="guardar">
            <div class="max-h-[62dvh] space-y-4 overflow-y-auto px-6 py-5">
                @foreach($grupos as $indice => $campos)
                    @php($estilo = $estilos[$loop->index % count($estilos)])
                    <fieldset class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-3 {{ $estilo[0] }}">
                        <legend class="px-1 text-[10px] font-bold uppercase tracking-widest {{ $estilo[1] }}">{{ $indice }}</legend>
                        @foreach($campos as $campo => $config)
                            <label class="space-y-1 text-sm {{ $estilo[2] }}">{{ $config[0] }}
                                <input class="{{ $input }}" type="{{ $config[1] ?? 'text' }}" wire:model="form.{{ $campo }}"
                                    @if(($config[1] ?? '') === 'number') min="0" step="0.01" @endif
                                    @required(in_array($campo, ['nombre','apellido','email'], true))>
                            </label>
                        @endforeach
                    </fieldset>
                @endforeach

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Cargo y ubicación</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="space-y-1 text-sm">Cargo
                            <select class="{{ $input }}" wire:model="form.cargo">
                                <option value="">Sin cargo asignado</option>
                                @if(! empty($form['cargo']) && ! in_array($form['cargo'], ['repartidor', 'cajero'], true))
                                    <option value="{{ $form['cargo'] }}">{{ $form['cargo'] }}</option>
                                @endif
                                <option value="repartidor">Repartidor</option>
                                <option value="cajero">Cajero</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-sm">Almacén
                            <select class="{{ $input }}" wire:model="form.almacen_id">
                                <option value="">Sin almacén</option>
                                @foreach($almacenes as $opcion)
                                    <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="space-y-1 text-sm">Estado
                            <input class="{{ $input }}" list="estados-empleado" wire:model="form.estado" required>
                            <datalist id="estados-empleado"><option value="activo"><option value="inactivo"><option value="licencia"><option value="vacaciones"></datalist>
                        </label>
                        <label class="space-y-1 text-sm">Foto
                            <input class="{{ $input }}" type="file" wire:model="foto" accept="image/*">
                        </label>
                    </div>
                </div>

                <fieldset class="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <legend class="px-1 text-[10px] font-bold uppercase tracking-widest text-amber-700">Acceso a la plataforma</legend>
                    <label class="flex gap-2 text-sm text-amber-900"><input type="checkbox" wire:model.live="form.crear_usuario" @disabled($form['es_repartidor'])> Habilitar acceso</label>
                    <label class="flex gap-2 text-sm text-amber-900"><input type="checkbox" wire:model.live="form.es_repartidor"> Habilitar como repartidor</label>
                    @if($form['crear_usuario'])
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1 text-sm">Contraseña
                                <input class="{{ $input }}" type="password" wire:model="form.password" autocomplete="new-password" minlength="6">
                            </label>
                            @unless($form['es_repartidor'])
                                <label class="space-y-1 text-sm">Rol
                                    <select class="{{ $input }}" wire:model="form.rol_id" required>
                                        <option value="">Seleccionar rol</option>
                                        @foreach($roles as $rol)
                                            <option value="{{ $rol->id }}">{{ $rol->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endunless
                        </div>
                    @endif
                </fieldset>

                <label class="block space-y-1 text-sm rounded-xl border border-slate-200 bg-white p-4 shadow-sm">Notas<textarea class="{{ $input }}" rows="2" wire:model="form.notas"></textarea></label>

                @if($errors->any())
                    <ul class="list-inside list-disc rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive" role="alert">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button class="{{ $button }} bg-white" type="button" wire:click="cerrar">Cerrar</button>
                <button class="{{ $primary }}" type="submit" wire:loading.attr="disabled" wire:target="foto,guardar">
                    <span wire:loading.remove wire:target="foto,guardar">Guardar empleado</span>
                    <span wire:loading wire:target="foto,guardar">Guardando…</span>
                </button>
            </footer>
        </form>
    </section>
</div>

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
@endphp
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" wire:keydown.escape.window="cerrar">
    <section class="max-h-[92dvh] w-full max-w-4xl overflow-auto rounded-xl border border-border bg-card p-4 shadow-xl sm:p-6" role="dialog" aria-modal="true" aria-labelledby="empleado-formulario" x-trap.noscroll="true">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-xl font-bold" id="empleado-formulario">{{ $empleadoId ? 'Editar empleado' : 'Nuevo empleado' }}</h2>
            <button class="{{ $button }}" type="button" wire:click="cerrar">Cerrar</button>
        </div>
        <form wire:submit="guardar" class="space-y-5">
            @foreach($grupos as $grupo => $campos)
                <fieldset class="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-2 lg:grid-cols-3">
                    <legend class="px-1 font-semibold">{{ $grupo }}</legend>
                    @foreach($campos as $campo => $config)
                        <label class="space-y-1 text-sm">{{ $config[0] }}
                            <input class="{{ $input }}" type="{{ $config[1] ?? 'text' }}" wire:model="form.{{ $campo }}"
                                @if(($config[1] ?? '') === 'number') min="0" step="0.01" @endif
                                @required(in_array($campo, ['nombre','apellido','email'], true))>
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
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
            <fieldset class="space-y-3 rounded-lg border border-border p-3">
                <legend class="px-1 font-semibold">Acceso a la plataforma</legend>
                <label class="flex gap-2 text-sm"><input type="checkbox" wire:model.live="form.crear_usuario" @disabled($form['es_repartidor'])> Habilitar acceso</label>
                <label class="flex gap-2 text-sm"><input type="checkbox" wire:model.live="form.es_repartidor"> Habilitar como repartidor</label>
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
            <label class="space-y-1 text-sm">Notas<textarea class="{{ $input }}" rows="2" wire:model="form.notas"></textarea></label>
            @if($errors->any())
                <ul class="list-inside list-disc text-sm text-destructive" role="alert">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="flex justify-end">
                <button class="{{ $primary }}" type="submit" wire:loading.attr="disabled" wire:target="foto,guardar">Guardar empleado</button>
            </div>
        </form>
    </section>
</div>

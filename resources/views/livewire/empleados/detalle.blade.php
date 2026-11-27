@php
    $empleado = $empleadoDetalle;
    $roles = $empleado->user?->roles->pluck('name')->join(', ') ?: 'Sin rol asignado';
    $telefono = $empleado->telefono;
@endphp
<div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm" wire:keydown.escape.window="cerrarDetalle">
    <section class="max-h-[92dvh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="empleado-detalle-titulo" x-trap.noscroll="true">
        <header class="relative overflow-hidden bg-gradient-to-r from-violet-700 via-purple-700 to-teal-700 px-6 py-6 text-white">
            <span aria-hidden="true" class="pointer-events-none absolute -right-2 top-1/2 -translate-y-1/2 select-none text-[130px] leading-none opacity-10">👤</span>
            <button type="button" wire:click="cerrarDetalle" aria-label="Cerrar detalle" class="absolute right-4 top-4 rounded-full bg-white/15 p-1.5 text-white transition hover:bg-white/30">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 0 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
            <span class="inline-flex rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-widest">Detalle de empleado</span>
            <h2 id="empleado-detalle-titulo" class="mt-3 text-2xl font-black uppercase tracking-tight sm:text-3xl">{{ $empleado->nombre_completo }}</h2>
            <p class="mt-1 text-sm text-white/80">{{ $empleado->email ?: 'Sin correo' }} · {{ $empleado->rut ?: 'Sin RUT' }}</p>
        </header>

        <div class="max-h-[62dvh] space-y-4 overflow-y-auto px-6 py-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-violet-500">Estado</p>
                    <p class="mt-2 font-bold uppercase text-violet-900">{{ $empleado->estado ?: '—' }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Cargo</p>
                    <p class="mt-2 font-bold text-emerald-800">{{ $empleado->cargo ?: 'Sin cargo' }}</p>
                    <p class="mt-1 text-xs text-emerald-700">{{ $empleado->departamento ?: 'Sin departamento' }}</p>
                </div>
                <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-teal-600">Almacén</p>
                    <p class="mt-2 font-bold text-teal-800">{{ $empleado->almacen?->nombre ?: 'Sin almacén' }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Imagen representativa</p>
                    <div class="flex h-44 items-center justify-center overflow-hidden rounded-lg border border-slate-100 bg-slate-50">
                        @if($empleado->foto)
                            <img class="h-full w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($empleado->foto) }}" alt="{{ $empleado->nombre_completo }}">
                        @else
                            <span class="text-6xl opacity-40" aria-hidden="true">👤</span>
                        @endif
                    </div>
                    @if($empleado->email || $telefono)
                        <div class="mt-4 space-y-2 text-sm">
                            @if($empleado->email)
                                <a class="flex items-center gap-2 text-slate-700 hover:text-emerald-700" href="mailto:{{ $empleado->email }}">✉️ {{ $empleado->email }}</a>
                            @endif
                            @if($telefono)
                                <a class="flex items-center gap-2 text-slate-700 hover:text-emerald-700" href="https://wa.me/{{ preg_replace('/\D/', '', $telefono) }}" target="_blank" rel="noopener noreferrer">📞 {{ $telefono }}</a>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-violet-500">Datos personales</p>
                    <dl class="space-y-2 text-sm text-violet-900">
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">RUT</dt><dd class="font-semibold">{{ $empleado->rut ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Nacimiento</dt><dd class="font-semibold">{{ $empleado->fecha_nacimiento?->format('d-m-Y') ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Nacionalidad</dt><dd class="font-semibold">{{ $empleado->nacionalidad ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Estado civil</dt><dd class="font-semibold">{{ $empleado->estado_civil ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-violet-600">Dirección</dt><dd class="text-right font-semibold">{{ trim(($empleado->direccion ?: '').' '.($empleado->comuna ?: '')) ?: '—' }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-emerald-600">Contrato y jornada</p>
                    <dl class="space-y-2 text-sm text-emerald-900">
                        <div class="flex justify-between gap-3"><dt class="text-emerald-600">Tipo de contrato</dt><dd class="font-semibold">{{ $empleado->tipo_contrato ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-emerald-600">Contratación</dt><dd class="font-semibold">{{ $empleado->fecha_contratacion?->format('d-m-Y') ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-emerald-600">Sueldo bruto</dt><dd class="font-semibold">{{ number_format((float) $empleado->salario, 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-emerald-600">Sueldo líquido</dt><dd class="font-semibold">{{ number_format((float) $empleado->sueldo_liquido_pactado, 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-emerald-600">Jornada</dt><dd class="font-semibold">{{ $empleado->hora_entrada?->format('H:i') ?: '—' }} – {{ $empleado->hora_salida?->format('H:i') ?: '—' }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-widest text-teal-600">Previsión y datos bancarios</p>
                    <dl class="space-y-2 text-sm text-teal-900">
                        <div class="flex justify-between gap-3"><dt class="text-teal-600">AFP</dt><dd class="font-semibold">{{ $empleado->afp ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-teal-600">Salud</dt><dd class="font-semibold">{{ $empleado->sistema_salud ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-teal-600">Isapre</dt><dd class="font-semibold">{{ $empleado->isapre_nombre ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-teal-600">Banco</dt><dd class="font-semibold">{{ $empleado->banco_nombre ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-teal-600">Cuenta</dt><dd class="font-semibold">{{ $empleado->banco_tipo_cuenta ?: '—' }} {{ $empleado->banco_numero_cuenta ?: '' }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-[10px] font-bold uppercase tracking-widest text-amber-700">Acceso a la plataforma</p>
                <div class="mt-2 grid gap-2 text-sm text-amber-900 sm:grid-cols-3">
                    <p><strong>Acceso:</strong> {{ $empleado->user ? 'Habilitado' : 'Deshabilitado' }}</p>
                    <p><strong>Rol:</strong> {{ $roles }}</p>
                    <p><strong>Repartidor:</strong> {{ $empleado->user?->hasRole(\App\Models\Repartidor::defaultRole()) ? 'Sí' : 'No' }}</p>
                </div>
            </div>

            @if($empleado->notas)
                <div class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <span class="text-lg text-amber-500" aria-hidden="true">✅</span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-amber-700">Notas</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-amber-900">{{ $empleado->notas }}</p>
                    </div>
                </div>
            @endif
        </div>

        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                @can('rrhh.empleados.edit')
                    <button type="button" class="{{ $primary }}" wire:click="editar({{ $empleado->id }})">Editar empleado</button>
                @endcan
            </div>
            <button type="button" class="{{ $button }} bg-white" wire:click="cerrarDetalle">Cerrar detalle</button>
        </footer>
    </section>
</div>

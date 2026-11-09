<div class="space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                Configuración del sistema
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-foreground">
                Ajustes del Servidor y Base de Datos
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                Estos valores se guardan en la base de datos, se cifran cuando son sensibles y se
                inyectan en la configuración de Laravel en tiempo de ejecución.
            </p>
        </div>

        <a
            href="{{ route('configuracion-web.index') }}"
            class="inline-flex items-center justify-center rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
        >
            ← Volver a Configuración Web
        </a>
    </header>

    @if (session('system-settings-status'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-600 dark:text-emerald-400">
            {{ session('system-settings-status') }}
        </div>
    @endif

    @error('database')
        <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-medium text-rose-600 dark:text-rose-400">
            {{ $message }}
        </div>
    @enderror

    <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
        <strong class="font-semibold">Importante:</strong> los cambios de base de datos, Redis y Reverb se aplican en la
        siguiente petición. Los valores públicos de <code class="font-mono">VITE_*</code> se entregan dinámicamente
        al navegador; recarga la página para aplicar los cambios. Reinicia los procesos de larga duración afectados.
    </div>

    <form wire:submit="save" class="space-y-6">
        @foreach ($groups as $groupId => $group)
            <section class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-xs">
                <div class="border-b border-border/60 bg-muted/30 px-5 py-4 sm:px-6">
                    <h2 class="text-base font-bold text-foreground">{{ $group['label'] ?? $groupId }}</h2>

                    @if (! empty($group['description']))
                        <p class="mt-1 text-sm text-muted-foreground">{{ $group['description'] }}</p>
                    @endif
                </div>

                <div class="space-y-5 p-5 sm:p-6">
                    @if ($groupId === 'database')
                        <label class="flex items-start gap-3 rounded-xl border border-border/60 bg-muted/20 p-4">
                            <input
                                type="checkbox"
                                wire:model="databaseOverrideEnabled"
                                class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary"
                            >
                            <span>
                                <span class="block text-sm font-semibold text-foreground">
                                    Usar estas credenciales para conectar la aplicación
                                </span>
                                <span class="mt-0.5 block text-xs text-muted-foreground">
                                    Al activarlo, las credenciales se validan antes de guardarse y se inyectan al
                                    arrancar la aplicación. Si la conexión falla, no se guardará nada.
                                </span>
                            </span>
                        </label>
                    @endif

                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($group['fields'] as $key => $field)
                            @php
                                $isSecret = (bool) ($field['secret'] ?? false);
                                $type = $field['type'] ?? 'text';
                                $errorKey = $isSecret ? "secrets.{$key}" : "values.{$key}";
                                $hasError = $errors->has($errorKey);
                            @endphp

                            <div @class(['sm:col-span-2' => $type === 'select' && in_array($key, ['DB_CONNECTION', 'MAIL_MAILER', 'BROADCAST_CONNECTION'], true)])>
                                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                                    <label for="setting-{{ $key }}" class="text-sm font-semibold text-foreground">
                                        {{ $field['label'] ?? $key }}
                                    </label>

                                    @if ($isSecret)
                                        <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold tracking-wide text-muted-foreground uppercase">
                                            Cifrado
                                        </span>

                                        @if ($hasStoredSecret[$key] ?? false)
                                            <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wide text-emerald-600 uppercase dark:text-emerald-400">
                                                Guardado
                                            </span>
                                        @elseif ($envConfigured[$key] ?? false)
                                            <span class="rounded-full bg-sky-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wide text-sky-600 uppercase dark:text-sky-400">
                                                Desde .env
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                @if ($type === 'select')
                                    <select
                                        id="setting-{{ $key }}"
                                        wire:model="values.{{ $key }}"
                                        @class([
                                            'w-full rounded-lg border bg-background px-3 py-2 text-sm text-foreground shadow-xs transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none',
                                            'border-rose-500/60' => $hasError,
                                            'border-border' => ! $hasError,
                                        ])
                                    >
                                        <option value="">— Usar valor de .env —</option>

                                        @foreach ($field['options'] ?? [] as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($isSecret)
                                    <div class="flex gap-2">
                                        <input
                                            id="setting-{{ $key }}"
                                            type="password"
                                            wire:model="secrets.{{ $key }}"
                                            autocomplete="new-password"
                                            placeholder="{{ ($hasStoredSecret[$key] ?? false) ? '•••••••• (guardado)' : 'Sin configurar' }}"
                                            @class([
                                                'w-full rounded-lg border bg-background px-3 py-2 text-sm text-foreground shadow-xs transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none',
                                                'border-rose-500/60' => $hasError,
                                                'border-border' => ! $hasError,
                                            ])
                                        >

                                        @if ($hasStoredSecret[$key] ?? false)
                                            <button
                                                type="button"
                                                wire:click="clearSecret('{{ $key }}')"
                                                wire:confirm="¿Eliminar este valor almacenado? Se usará el valor del archivo .env si existe."
                                                class="shrink-0 rounded-lg border border-rose-500/40 px-3 py-2 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-500/10 dark:text-rose-400"
                                            >
                                                Eliminar
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <input
                                        id="setting-{{ $key }}"
                                        type="{{ $type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text') }}"
                                        wire:model="values.{{ $key }}"
                                        @class([
                                            'w-full rounded-lg border bg-background px-3 py-2 text-sm text-foreground shadow-xs transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none',
                                            'border-rose-500/60' => $hasError,
                                            'border-border' => ! $hasError,
                                        ])
                                    >
                                @endif

                                @if (! empty($field['help']))
                                    <p class="mt-1.5 text-xs text-muted-foreground">{{ $field['help'] }}</p>
                                @endif

                                @if ($isSecret)
                                    <p class="mt-1.5 text-xs text-muted-foreground">
                                        Déjalo vacío para conservar el valor actual. Nunca se muestra en texto plano.
                                    </p>
                                @endif

                                @error($errorKey)
                                    <p class="mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-muted-foreground">
                Los secretos se cifran con la <code class="font-mono">APP_KEY</code> de la aplicación.
            </p>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading.remove>Guardar Configuración</span>
                <span wire:loading>Guardando...</span>
            </button>
        </div>
    </form>
</div>

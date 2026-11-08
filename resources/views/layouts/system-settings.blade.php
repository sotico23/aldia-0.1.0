@php
    $webSettings = \App\Models\WebSetting::getSettings();
    $theme = $webSettings->themeColors();
    $appearance = $appearance ?? 'system';
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class(['dark' => $appearance === 'dark'])
    style="--brand-primary: {{ $theme['primary'] }}; --brand-secondary: {{ $theme['secondary'] }}; --brand-accent: {{ $theme['accent'] }};"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Ajustes del Servidor' }} — {{ $webSettings->app_name ?: config('app.name', 'Laravel') }}</title>

    <script>
        (function () {
            if ('{{ $appearance }}' === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body class="min-h-svh bg-background font-sans text-foreground antialiased">
    <header class="border-b border-border/60 bg-card/80 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-sm font-black text-primary-foreground">
                    {{ substr($webSettings->app_name ?: config('app.name', 'L'), 0, 1) }}
                </span>
                <div class="leading-tight">
                    <p class="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                        {{ $webSettings->app_name ?: config('app.name', 'Laravel') }}
                    </p>
                    <p class="text-sm font-semibold text-foreground">Administración del sistema</p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Entorno: <span class="font-semibold text-foreground">{{ app()->environment() }}</span>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>

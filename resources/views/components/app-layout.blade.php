<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0f766e">

        <title>{{ $title ?? 'RedCliente' }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-screen-2xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <a href="/" class="flex items-center gap-2 text-base font-bold text-slate-900">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-sm font-black text-white">
                        RC
                    </span>
                    <span class="hidden sm:inline">RedCliente</span>
                </a>

                <nav class="flex items-center gap-1 text-sm">
                    <a href="/tienda" class="rounded-lg px-3 py-1.5 font-semibold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900">
                        Tienda
                    </a>

                    @auth
                        <a href="/dashboard" class="rounded-lg px-3 py-1.5 font-semibold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900">
                            Mi panel
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg bg-primary px-3 py-1.5 font-semibold text-white transition-colors hover:bg-primary/90">
                            Ingresar
                        </a>
                    @endauth
                </nav>
            </div>

            @if (isset($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs) > 0)
                <nav class="mx-auto max-w-screen-2xl px-4 pb-2 sm:px-6 lg:px-8" aria-label="Migas de pan">
                    <ol class="flex flex-wrap items-center gap-1 text-[11px] text-slate-500">
                        @foreach ($breadcrumbs as $index => $breadcrumb)
                            @if ($index < count($breadcrumbs) - 1)
                                <li class="flex items-center gap-1">
                                    <a href="{{ $breadcrumb['href'] }}" class="text-primary hover:underline">{{ $breadcrumb['title'] }}</a>
                                    <span class="text-slate-300">/</span>
                                </li>
                            @else
                                <li class="font-medium text-slate-700">{{ $breadcrumb['title'] }}</li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif
        </header>

        {{ $slot }}

        @livewireScripts
    </body>
</html>

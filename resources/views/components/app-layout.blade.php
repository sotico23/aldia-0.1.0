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

        <div class="mx-auto grid max-w-screen-2xl items-start gap-4 px-4 py-4 sm:px-6 lg:grid-cols-[13rem_minmax(0,1fr)] lg:px-8 xl:grid-cols-[13rem_minmax(0,1fr)_15rem]">
            <aside aria-label="Navegación principal" class="rounded-xl border border-slate-200 bg-white p-4 lg:sticky lg:top-28">
                <details open>
                    <summary class="cursor-pointer text-sm font-bold">Navegación</summary>
                    <nav class="mt-3 flex flex-col gap-1 text-sm">
                        <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Inicio</a>
                        <a href="{{ route('marketplace.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Marketplace</a>
                        @auth
                            @can('ver dashboard')
                                <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Dashboard</a>
                            @endcan
                            <a href="{{ route('comunidad.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Comunidad</a>
                            <a href="{{ route('mi-informacion.show') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Mi información</a>
                        @endauth
                    </nav>
                </details>
            </aside>

            <main class="min-w-0">{{ $slot }}</main>

            <aside aria-label="Mi cuenta" class="rounded-xl border border-slate-200 bg-white p-4 lg:col-start-2 xl:sticky xl:top-28 xl:col-start-auto">
                @auth
                    @php($authenticatedUser = auth()->user())
                    <div class="flex items-center gap-3">
                        <img src="{{ $authenticatedUser->profilePhotoUrl() }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold">{{ $authenticatedUser->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $authenticatedUser->email }}</p>
                        </div>
                    </div>
                    <nav class="mt-4 flex flex-col gap-1 text-sm" aria-label="Opciones de la cuenta">
                        <a href="{{ route('profile.edit') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Perfil</a>
                        <a href="{{ route('user-password.edit') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Contraseña</a>
                        <a href="{{ route('notifications.preferences') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Notificaciones</a>
                        @if ($authenticatedUser->publicProfile)
                            <a href="{{ route('marketplace.show', $authenticatedUser->publicProfile->slug) }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Mi tienda</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-red-600 hover:bg-red-50">Cerrar sesión</button>
                        </form>
                    </nav>
                @else
                    <p class="text-sm font-bold">Mi cuenta</p>
                    <p class="mt-2 text-sm text-slate-500">Ingresa para ver tu perfil y tu tienda.</p>
                    <a href="{{ route('login') }}" class="mt-4 inline-flex rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white">Ingresar</a>
                @endauth
            </aside>
        </div>

        @livewireScripts
    </body>
</html>

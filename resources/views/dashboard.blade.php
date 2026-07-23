@php
    $cspNonce = $cspNonce ?? Str::random(40);
    $appearance = $appearance ?? 'system';
    $userName = $userName ?? 'Usuario';
    $stats = $stats ?? collect();
    $topProductos = $topProductos ?? collect();
    $siiStats = $siiStats ?? null;
    $productosCriticos = $productosCriticos ?? collect();
    $mensajesSinLeer = $mensajesSinLeer ?? 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script nonce="{{ $cspNonce }}">
        (function () {
            const appearance = '{{ $appearance ?? "system" }}';
            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    <style nonce="{{ $cspNonce }}">
        html {
            background-color: oklch(0.145 0 0);
        }
    </style>

    @php
        $webSettings = \App\Models\WebSetting::getSettings();
    @endphp

    <title>{{ $webSettings->app_title ?: config('app.name', 'Laravel') }} — Panel</title>

    <meta name="description" content="{{ $webSettings->app_description }}">
    <meta name="keywords" content="{{ $webSettings->app_keywords }}">
    <meta name="author" content="{{ $webSettings->app_author }}">
    <meta name="application-name" content="{{ $webSettings->app_name }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900" rel="stylesheet" />

    @vite('resources/css/app.css')
    @livewireStyles
</head>

<body class="font-sans antialiased bg-background text-foreground">
    <div class="flex min-h-svh">
        {{-- Sidebar --}}
        @auth
        <aside
            class="group fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-sidebar-border bg-sidebar md:flex">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-border/50 px-5">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-sidebar-primary text-xs font-black text-sidebar-primary-foreground">
                    A</div>
                <div class="flex flex-col leading-tight">
                    <span
                        class="text-sm font-bold text-sidebar-foreground">{{ $webSettings->app_name ?: config('app.name', 'Laravel') }}</span>
                    <span class="text-[10px] font-semibold tracking-wider text-sidebar-foreground/40 uppercase">Panel de
                        Control</span>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-3 py-4">
                {{-- COMERCIAL --}}
                <div class="mb-2 px-2 text-[10px] font-black tracking-widest text-sidebar-foreground/40 uppercase">
                    Comercial</div>
                <div class="mb-4 space-y-0.5">
                    @can('comercial.cotizaciones.viewAny')
                    <x-nav-link href="{{ route('cotizaciones.index') }}"
                        :active="request()->routeIs('cotizaciones*')">Cotizaciones</x-nav-link>
                    @endcan
                    @can('comercial.productos.viewAny')
                    <x-nav-link href="#">Productos</x-nav-link>
                    @endcan
                    @can('ventas.ventas.viewAny')
                    <x-nav-link href="#">Marketplace</x-nav-link>
                    @endcan
                </div>

                {{-- OPERACIONES --}}
                <div class="mb-2 px-2 text-[10px] font-black tracking-widest text-sidebar-foreground/40 uppercase">
                    Operaciones</div>
                <div class="mb-4 space-y-0.5">
                    @can('comercial.clientes.viewAny')
                    <x-nav-link href="{{ route('clientes.index') }}" :active="request()->routeIs('clientes*')">CRM /
                        Clientes</x-nav-link>
                    @endcan
                    @can('inventario.proveedores.viewAny')
                    <x-nav-link href="{{ route('proveedors.index') }}"
                        :active="request()->routeIs('proveedors*')">Proveedores</x-nav-link>
                    @endcan
                    @can('inventario.compras.viewAny')
                    <x-nav-link href="{{ route('compras.index') }}"
                        :active="request()->routeIs('compras*')">Compras</x-nav-link>
                    @endcan
                    @can('ventas.ventas.viewAny')
                    <x-nav-link href="{{ route('ventas.index') }}"
                        :active="request()->routeIs('ventas*')">Ventas</x-nav-link>
                    @endcan
                </div>

                {{-- FACTURACION --}}
                <div class="mb-2 px-2 text-[10px] font-black tracking-widest text-sidebar-foreground/40 uppercase">
                    Facturación</div>
                <div class="mb-4 space-y-0.5">
                    @can('finanzas.sii.viewAny')
                    <x-nav-link href="{{ route('sii.documentos') }}"
                        :active="request()->routeIs('sii.documentos*')">Documentos SII</x-nav-link>
                    @endcan
                    @can('finanzas.facturacion.viewAny')
                    <x-nav-link href="{{ route('facturacion.index') }}"
                        :active="request()->routeIs('facturacion*')">Facturación</x-nav-link>
                    @endcan
                </div>

                {{-- RRHH --}}
                <div class="mb-2 px-2 text-[10px] font-black tracking-widest text-sidebar-foreground/40 uppercase">
                    Configuración</div>
                <div class="space-y-0.5">
                    <x-nav-link href="#">Mi Información</x-nav-link>
                    @canany(['admin.web-settings.viewAny', 'admin.configuracion.viewAny'])
                    <x-nav-link href="{{ route('configuracion-web.index') }}">Configuración Web</x-nav-link>
                    @endcanany
                </div>
            </div>

            <div class="border-t border-sidebar-border/50 px-3 py-3">
                <a href="{{ route('logout') }}"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                    class="flex items-center gap-2 rounded-md px-2 py-2 text-sm text-sidebar-foreground/60 transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    <span>Cerrar sesión</span>
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
            </div>
        </aside>
        @endauth

        {{-- Mobile header --}}
        <div
            class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-sidebar-border/50 bg-sidebar px-4 md:hidden">
            <div class="flex items-center gap-2">
                <button type="button" onclick="document.getElementById('mobile-nav').classList.toggle('hidden')"
                    class="rounded-md p-1.5 text-sidebar-foreground/60 hover:bg-sidebar-accent">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <span
                    class="text-sm font-bold text-sidebar-foreground">{{ $webSettings->app_name ?: config('app.name', 'Laravel') }}</span>
            </div>
            <div class="flex items-center gap-1 text-xs text-sidebar-foreground/60">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                {{ $userName }}
            </div>
        </div>

        {{-- Mobile nav overlay --}}
        @auth
        <div id="mobile-nav" class="fixed inset-0 z-50 hidden md:hidden">
            <div class="absolute inset-0 bg-black/60"
                onclick="document.getElementById('mobile-nav').classList.add('hidden')"></div>
            <div class="relative ml-auto h-full w-72 max-w-[80vw] overflow-y-auto bg-sidebar p-4 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <span class="text-sm font-bold text-sidebar-foreground">Navegación</span>
                    <button type="button" onclick="document.getElementById('mobile-nav').classList.add('hidden')"
                        class="rounded-md p-1.5 text-sidebar-foreground/60 hover:bg-sidebar-accent">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mb-2 px-2 text-[10px] font-black tracking-widest text-sidebar-foreground/40 uppercase">
                    Comercial</div>
                <div class="mb-4 space-y-0.5">
                    @can('comercial.cotizaciones.viewAny')
                    <x-nav-link href="{{ route('cotizaciones.index') }}">Cotizaciones</x-nav-link>
                    @endcan
                    @can('comercial.productos.viewAny')
                    <x-nav-link href="#">Productos</x-nav-link>
                    @endcan
                </div>

                <div class="mb-2 px-2 text-[10px] font-black tracking-widest text-sidebar-foreground/40 uppercase">
                    Operaciones</div>
                <div class="mb-4 space-y-0.5">
                    @can('comercial.clientes.viewAny')
                    <x-nav-link href="{{ route('clientes.index') }}">CRM / Clientes</x-nav-link>
                    @endcan
                    @can('inventario.proveedores.viewAny')
                    <x-nav-link href="{{ route('proveedors.index') }}">Proveedores</x-nav-link>
                    @endcan
                    @can('inventario.compras.viewAny')
                    <x-nav-link href="{{ route('compras.index') }}">Compras</x-nav-link>
                    @endcan
                    @can('ventas.ventas.viewAny')
                    <x-nav-link href="{{ route('ventas.index') }}">Ventas</x-nav-link>
                    @endcan
                </div>

                <div class="mb-2 px-2 text-[10px] font-black tracking-widest text-sidebar-foreground/40 uppercase">
                    Facturación</div>
                <div class="mb-4 space-y-0.5">
                    @can('finanzas.sii.viewAny')
                    <x-nav-link href="{{ route('sii.documentos') }}">Documentos SII</x-nav-link>
                    @endcan
                    @can('finanzas.facturacion.viewAny')
                    <x-nav-link href="{{ route('facturacion.index') }}">Facturación</x-nav-link>
                    @endcan
                </div>

                <div class="mb-2 px-2 text-[10px] font-black tracking-widest text-sidebar-foreground/40 uppercase">
                    Configuración</div>
                <div class="space-y-0.5">
                    <x-nav-link href="#">Mi Información</x-nav-link>
                    @canany(['admin.web-settings.viewAny', 'admin.configuracion.viewAny'])
                    <x-nav-link href="{{ route('configuracion-web.index') }}">Configuración Web</x-nav-link>
                    @endcanany
                </div>

                <div class="mt-6 border-t border-sidebar-border/50 pt-4">
                    <a href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form-mobile').submit();"
                        class="flex items-center gap-2 rounded-md px-2 py-2 text-sm text-sidebar-foreground/60 transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        <span>Cerrar sesión</span>
                    </a>
                    <form id="logout-form-mobile" action="{{ route('logout') }}" method="POST" class="hidden">@csrf
                    </form>
                </div>
            </div>
        </div>
        @endauth

        {{-- Main content --}}
        <main class="flex min-h-svh flex-1 flex-col md:ml-64">
            {{-- Header bar --}}
            <header
                class="flex h-16 shrink-0 items-center justify-between border-b border-sidebar-border/50 bg-background px-6 md:px-8">
                <div>
                    <h1 class="text-lg font-bold text-foreground">Bienvenido, {{ $userName }}</h1>
                    <p class="text-xs text-muted-foreground">{{ now()->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    @if ($mensajesSinLeer > 0)
                        <span
                            class="flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                            <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                            {{ $mensajesSinLeer }} mensajes
                        </span>
                    @endif
                </div>
            </header>

            {{-- Dashboard content --}}
            <div class="flex-1 space-y-6 p-6 md:p-8">
                @php $roleData = $stats ?? []; @endphp

                {{-- KPI row (from controller data) --}}
                @if ($stats)
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        @foreach ($stats as $stat)
                            <div
                                class="rounded-xl border border-border/50 bg-card p-3 shadow-xs transition-colors hover:bg-accent/30">
                                <p class="text-[11px] font-semibold tracking-wider text-muted-foreground/70 uppercase">
                                    {{ $stat->label }}</p>
                                <p class="mt-1 text-lg font-black text-foreground">{{ $stat->value }}</p>
                                <p class="text-[10px] text-muted-foreground/60">{{ $stat->subValue }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Livewire quick-access dashboard --}}
                <livewire:quick-access-dashboard />

                {{-- Top productos (if any) --}}
                @if (count($topProductos) > 0)
                    <div class="rounded-xl border border-border/50 bg-card p-5">
                        <h3 class="mb-3 text-sm font-bold text-foreground">Top 5 Productos Más Vendidos</h3>
                        <div class="space-y-2">
                            @foreach ($topProductos as $producto)
                                <div class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2">
                                    <span class="text-sm font-medium text-foreground">{{ $producto->nombre }}</span>
                                    <span class="text-xs font-bold text-muted-foreground">{{ $producto->total_cantidad }} unid.
                                        / ${{ number_format($producto->total_venta, 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- SII Status --}}
                @if ($siiStats)
                    <div class="rounded-xl border border-border/50 bg-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-foreground">Estado SII</h3>
                                <p class="text-xs text-muted-foreground/70">
                                    {{ $siiStats['ambiente'] === 'produccion' ? 'Producción' : 'Certificación' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $siiStats['token_activo'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full {{ $siiStats['token_activo'] ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                                    Token {{ $siiStats['token_activo'] ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                        </div>
                        @if ($siiStats['emisor'])
                            <div class="mt-3 text-xs text-muted-foreground/70">
                                {{ $siiStats['emisor']['razon_social'] }} — RUT {{ $siiStats['emisor']['rut'] }}
                            </div>
                        @endif
                        @if (count($siiStats['folios_disponibles'] ?? []) > 0)
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($siiStats['folios_disponibles'] as $folio)
                                    <span class="rounded-md bg-muted/70 px-2 py-1 text-[10px] font-medium text-muted-foreground">
                                        {{ $folio['tipo'] }}: {{ $folio['restantes'] }} folios
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Productos críticos --}}
                @if ($productosCriticos->isNotEmpty())
                    <div class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-5">
                        <h3 class="mb-2 text-sm font-bold text-rose-400">Stock Crítico</h3>
                        <div class="space-y-1.5">
                            @foreach ($productosCriticos as $p)
                                <div class="flex items-center justify-between rounded-lg bg-rose-500/10 px-3 py-1.5">
                                    <span class="text-sm text-rose-300">{{ $p->nombre }} ({{ $p->codigo }})</span>
                                    <span class="text-xs font-bold text-rose-400">{{ $p->stock_actual }} /
                                        {{ $p->stock_minimo }} mín.</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </main>
    </div>

    @livewireScripts
</body>

</html>

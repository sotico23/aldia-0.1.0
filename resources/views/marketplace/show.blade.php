<x-app-layout
    :breadcrumbs="[
        ['title' => 'Marketplace', 'href' => '/tienda'],
        ['title' => $store->title, 'href' => '/tienda/' . $store->slug],
    ]"
>
    <head>
        <title>{{ $store->title }}</title>
    </head>

    {{-- Header Area --}}
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {{-- Cover Photo --}}
            <div class="group relative h-64 w-full overflow-hidden rounded-b-xl bg-gradient-to-r from-slate-200 to-slate-300 sm:h-80 md:h-96">
                @if ($store->user->cover_photo_url)
                    <img
                        src="{{ $store->user->cover_photo_url }}"
                        class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                    >
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-80"></div>
            </div>

            {{-- Profile Info Bar --}}
            <div class="relative -mt-12 mb-4 flex flex-col items-center px-4 pb-4 sm:-mt-16 sm:flex-row sm:items-end sm:justify-between sm:px-8">
                <div class="z-10 flex w-full flex-col items-center gap-4 sm:flex-row sm:items-end sm:gap-6">
                    {{-- Avatar --}}
                    <div class="relative h-32 w-32 shrink-0 overflow-hidden rounded-full border-4 border-white bg-white shadow-md sm:h-40 sm:w-40">
                        @if ($store->user->profile_photo_url)
                            <img src="{{ $store->user->profile_photo_url }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-slate-100 text-5xl font-bold text-slate-300">
                                {{ Str::upper($store->title[0] ?? '?') }}
                            </div>
                        @endif
                        <div class="absolute right-2 bottom-2 h-4 w-4 rounded-full border-2 border-white bg-green-500"></div>
                    </div>

                    {{-- Text Info --}}
                    <div class="flex-1 pb-2 text-center sm:pb-4 sm:text-left">
                        <h1 class="flex items-center gap-2 text-3xl font-bold tracking-tight text-slate-900 sm:justify-start">
                            {{ $store->title }}
                            @if ($store->is_verified)
                                <span class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-emerald-500 text-emerald-500" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Tienda verificada
                                </span>
                            @endif
                            @if ($store->is_official)
                                <span class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-amber-500 text-amber-500" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.91c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00.951-.69z" />
                                    </svg>
                                    Tienda Oficial
                                </span>
                            @endif
                        </h1>
                        <p class="mt-1 flex items-center gap-4 text-sm font-medium text-slate-500 sm:justify-start">
                            <span class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 fill-amber-400 text-amber-400" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 8.088 9.246 9.764 7 11.062c1.058 1.025 1.814 1.679 2.448 1.977 1.216.617 2.129 1.116 2.784 1.48.444.243.583.486.758.73.278.393.48.829.681 1.274m0-13c1.864 0 3.412.719 4.584 1.616a4.493 4.493 0 011.778 4.389m0-13a.256.256 0 01-.256.256A.256.256 0 006.912 10.798c0 .553.344 1.052.848 1.273a9 9 0 012.332-.347c3.383 0 6.112-1.893 7.5-4.784.877-1.842.47-3.842-.706-5.17a3.24 3.24 0 00-1.958-1.516c-1.292-.384-2.737.02-3.632.993m0 0c.645-1.073 1.488-2.018 2.45-2.627a4.05 4.05 0 013.248-.221m0 0c-.784 0-1.43.48-1.763 1.178a4.17 4.17 0 011.684 2.273m0 0c-.93.567-.717 1.76-.265 2.474a8 8 0 011.099 1.348c.265.351.523.714.758 1.083m0 0c-.294.44-.71 1.026-1.042 1.498m-6.842-1.293a1 1 0 00-1.438.495l-1.268 1.853c-.333.486-.507.994-.507 1.527v1.5c0 .61.195 1.178.507 1.527l1.268 1.853a1 1 0 001.438.495m6.842-1.293c1.866 0 3.412-.719 4.584-1.616a4.493 4.493 0 011.778-4.389m0 0A.256.256 0 0117.088 1.192a.256.256 0 00-.256-.256c-.553 0-1.052-.344-1.273-.848a9 9 0 00-2.332.347c-3.383 0-6.112 1.893-7.5 4.784-.877 1.842-.47 3.842.706 5.17a3.24 3.24 0 001.958 1.516c1.292.384 2.737-.02 3.632-.993m0 0c-.645 1.073-1.488 2.018-2.45 2.627a4.05 4.05 0 01-3.248.221m0 0c.784 0 1.43-.48 1.763-1.178a4.17 4.17 0 01-1.684-2.273m0 0c.93-.567.717-1.76.265-2.474a8 8 0 00-1.099-1.348c-.265-.351-.523-.714-.758-1.083m0 0c.294-.44.71-1.026 1.042-1.498m0 0 1.268-1.853a1 1 0 011.438-.495l1.268 1.853c.333-.486.507-.994.507-1.527v-1.5c0-.61-.195-1.178-.507-1.527l-1.268-1.853a1 1 0 01-1.438-.495" />
                                </svg>
                                {{ $averageRating ?? '5.0' }} ({{ $store->rating_count }} opiniones)
                            </span>
                            <span>•</span>
                            <span>{{ $store->likes_count }} me gusta</span>
                        </p>
                    </div>

                    {{-- Actions --}}
                    <div class="flex w-full shrink-0 justify-center gap-2 pb-2 sm:w-auto sm:pb-4">
                        <button
                            class="h-10 rounded-lg px-4 font-semibold bg-slate-200 text-slate-900 hover:bg-slate-300"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 31v-2a4 4 0 014-4h10a4 4 0 014 4v2m-6 0a2 2 0 110-4 2 2 0 010 4m-4 0a2 2 0 110-4 2 2 0 010 4m-4 0a2 2 0 110-4 2 2 0 010 4" />
                            </svg>
                            Me gusta
                        </button>
                        <a href="/tienda/{{ $store->slug }}/chat" class="inline-flex h-10 items-center justify-center rounded-lg bg-slate-200 px-4 font-semibold text-slate-900 hover:bg-slate-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Mensaje
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Navigation Tabs --}}
        <div class="flex border-t border-slate-200 px-4 pt-1 sm:px-8">
            <button wire:click="setTab('productos')" class="px-4 py-3 text-sm font-bold transition-colors {{ $tabActivo === 'productos' ? 'border-b-4 border-primary text-primary' : 'text-slate-500 hover:bg-slate-50' }} rounded-t-lg">
                Productos
            </button>
            <button wire:click="setTab('informacion')" class="px-4 py-3 text-sm font-bold transition-colors {{ $tabActivo === 'informacion' ? 'border-b-4 border-primary text-primary' : 'text-slate-500 hover:bg-slate-50' }} rounded-t-lg">
                Información
            </button>
            <button wire:click="setTab('opiniones')" class="px-4 py-3 text-sm font-bold transition-colors {{ $tabActivo === 'opiniones' ? 'border-b-4 border-primary text-primary' : 'text-slate-500 hover:bg-slate-50' }} rounded-t-lg">
                Opiniones
                @if (count($reviews) > 0)
                    <span class="ml-1.5 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] text-primary">{{ count($reviews) }}</span>
                @endif
            </button>
        </div>
    </div>

    {{-- Main Content Area --}}
    <div class="min-h-screen bg-[#f8fafc] pt-6 pb-16">
        <div class="mx-auto w-full max-w-screen-2xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-12">
                {{-- Columna Izquierda: Filtros --}}
                <aside class="w-full space-y-5 lg:col-span-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm transition-all hover:shadow-md">
                        <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17a1 1 0 01-1 1H4a1 1 0 01-1-1V4z" />
                                </svg>
                                Filtros
                            </h2>
                        </div>

                        {{-- Filtro por Almacenes --}}
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 10v10a2 2 0 002 2h12a2 2 0 002-2V10M3 10l2 2m-2 2v10a2 2 0 012 2h12a2 2 0 012-2V10M3 10l2-2M7 12h14m-7-10l7 7M5.455 17.053A1 1 0 016 17v-6a1 1 0 011.455-.894l3.78 2.106a1 1 0 010 1.788l-3.78 2.106a1 1 0 01-.894-.482z" />
                                </svg>
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Almacenes</span>
                            </div>
                            <div class="space-y-1.5">
                                @if (count($almacenes) > 0)
                                    <button
                                        wire:click="setFiltroAlmacen(null)"
                                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm text-left transition-colors {{ $filtroAlmacen === null ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-100' }}"
                                    >
                                        <span class="font-medium">Todos los almacenes</span>
                                        @if ($filtroAlmacen === null)
                                            <svg class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </button>
                                    @foreach ($almacenes as $almacen)
                                        <button
                                            wire:click="setFiltroAlmacen({{ $almacen->id }})"
                                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm text-left transition-colors {{ $filtroAlmacen === $almacen->id ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-100' }}"
                                        >
                                            <span class="font-medium truncate">{{ $almacen->nombre }}</span>
                                            @if ($filtroAlmacen === $almacen->id)
                                                <svg class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </button>
                                    @endforeach
                                @else
                                    <div class="py-4 text-center text-slate-400">
                                        <p class="text-xs">No hay almacenes disponibles</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Filtro por Categorías --}}
                        @if (count($store->categorias) > 0)
                            <div class="mt-5 space-y-3 pt-4 border-t border-slate-100">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Categorías</span>
                                </div>
                                <div class="space-y-1.5">
                                    <button
                                        wire:click="setFiltroCategoria(null)"
                                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm text-left transition-colors {{ $filtroCategoria === null ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-100' }}"
                                    >
                                        <span class="font-medium">Todas las categorías</span>
                                        @if ($filtroCategoria === null)
                                            <svg class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </button>
                                    @foreach ($store->categorias as $cat)
                                        <button
                                            wire:click="setFiltroCategoria({{ $cat->id }})"
                                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm text-left transition-colors {{ $filtroCategoria === $cat->id ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-100' }}"
                                        >
                                            <span class="font-medium truncate">{{ $cat->nombre }}</span>
                                            @if ($filtroCategoria === $cat->id)
                                                <svg class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </aside>

                {{-- Columna Central: Catálogo de Productos (Livewire) --}}
                <main class="w-full lg:col-span-10">
                    @livewire('marketplace-catalog', ['slug' => $store->slug])
                </main>
            </div>
        </div>
    </div>
</x-app-layout>

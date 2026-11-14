<div
    x-data="{ carritoAbierto: false }"
    x-cloak
    class="min-h-screen bg-slate-50 pb-20"
>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    {{-- ==================== CABECERA DE LA TIENDA ==================== --}}
    <div class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8">
            {{-- Portada --}}
            <div class="relative h-48 w-full overflow-hidden rounded-b-xl bg-gradient-to-r from-slate-200 to-slate-300 sm:h-64 md:h-80">
                @if ($store->user?->coverPhotoUrl())
                    <img
                        src="{{ $store->user->coverPhotoUrl() }}"
                        alt="Portada de {{ $store->title }}"
                        class="h-full w-full object-cover"
                    >
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
            </div>

            {{-- Datos del perfil --}}
            <div class="relative -mt-12 mb-4 flex flex-col items-center gap-4 px-2 pb-4 sm:-mt-16 sm:flex-row sm:items-end sm:px-6">
                <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-full border-4 border-white bg-white shadow-md sm:h-32 sm:w-32">
                    @if ($store->user?->profilePhotoUrl())
                        <img
                            src="{{ $store->user->profilePhotoUrl() }}"
                            alt="{{ $store->title }}"
                            class="h-full w-full object-cover"
                        >
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-slate-100 text-4xl font-bold text-slate-300">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($store->title, 0, 1)) }}
                        </div>
                    @endif
                    <span class="absolute right-1 bottom-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-500"></span>
                </div>

                <div class="min-w-0 flex-1 text-center sm:pb-2 sm:text-left">
                    <h1 class="flex flex-wrap items-center justify-center gap-2 text-2xl font-bold tracking-tight text-slate-900 sm:justify-start sm:text-3xl">
                        <span class="truncate">{{ $store->title }}</span>

                        @if ($store->is_verified)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-200">
                                Verificada
                            </span>
                        @endif

                        @if ($store->is_official)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700 ring-1 ring-amber-200">
                                Oficial
                            </span>
                        @endif
                    </h1>

                    <div class="mt-1 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm text-slate-500 sm:justify-start">
                        <span class="inline-flex items-center gap-1">
                            <svg class="h-4 w-4 text-amber-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.91c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.98 9.1c-.784-.57-.38-1.81.588-1.81h4.91a1 1 0 00.95-.69l1.62-4.674z"/>
                            </svg>
                            <span class="font-semibold text-slate-700">{{ $promedio }}</span>
                            <span>({{ $store->rating_count }} opiniones)</span>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <svg class="h-4 w-4 text-rose-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/>
                            </svg>
                            {{ $store->likes_count }} me gusta
                        </span>
                    </div>
                </div>

                <div class="flex w-full shrink-0 items-center justify-center gap-2 sm:w-auto sm:pb-2">
                    <button
                        type="button"
                        wire:click="darMeGusta"
                        @disabled(! $usuarioAutenticado)
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-slate-100 px-4 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                        </svg>
                        Me gusta
                    </button>

                    <a
                        href="{{ route('chat.start', $store->slug) }}"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-slate-100 px-4 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-200"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Mensaje
                    </a>
                </div>
            </div>
        </div>

        {{-- Pestañas --}}
        <div class="mx-auto flex max-w-screen-2xl gap-1 overflow-x-auto border-t border-slate-200 px-4 sm:px-6 lg:px-8">
            <button
                type="button"
                wire:click="setTab('productos')"
                class="shrink-0 rounded-t-lg px-4 py-3 text-sm font-bold transition-colors {{ $tabActivo === 'productos' ? 'border-b-4 border-primary text-primary' : 'text-slate-500 hover:bg-slate-50' }}"
            >
                Productos
            </button>
            <button
                type="button"
                wire:click="setTab('informacion')"
                class="shrink-0 rounded-t-lg px-4 py-3 text-sm font-bold transition-colors {{ $tabActivo === 'informacion' ? 'border-b-4 border-primary text-primary' : 'text-slate-500 hover:bg-slate-50' }}"
            >
                Información
            </button>
            <button
                type="button"
                wire:click="setTab('opiniones')"
                class="shrink-0 rounded-t-lg px-4 py-3 text-sm font-bold transition-colors {{ $tabActivo === 'opiniones' ? 'border-b-4 border-primary text-primary' : 'text-slate-500 hover:bg-slate-50' }}"
            >
                Opiniones
                @if ($reviews->isNotEmpty())
                    <span class="ml-1.5 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] text-primary">{{ $reviews->count() }}</span>
                @endif
            </button>
        </div>
    </div>

    {{-- ==================== CONTENIDO ==================== --}}
    <div class="mx-auto w-full max-w-screen-2xl px-4 pt-6 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-12">

            {{-- Filtros --}}
            <aside class="w-full lg:col-span-3 xl:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                            <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17a1 1 0 01-1 1H4a1 1 0 01-1-1V4z"/>
                            </svg>
                            Filtros
                        </h2>
                        @if ($filtroAlmacen || $filtroCategoria)
                            <button
                                type="button"
                                wire:click="setFiltroAlmacen(null); setFiltroCategoria(null)"
                                class="text-[11px] font-semibold text-primary hover:underline"
                            >
                                Limpiar
                            </button>
                        @endif
                    </div>

                    {{-- Almacenes --}}
                    <div>
                        <p class="mb-2 text-[11px] font-bold tracking-wider text-slate-500 uppercase">Almacenes</p>
                        <div class="space-y-1.5">
                            <button
                                type="button"
                                wire:click="setFiltroAlmacen(null)"
                                class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition-colors {{ $filtroAlmacen === null ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-100 bg-slate-50 text-slate-600 hover:bg-slate-100' }}"
                            >
                                <span class="font-medium">Todos los almacenes</span>
                            </button>

                            @foreach ($almacenes as $almacen)
                                <button
                                    type="button"
                                    wire:key="filtro-almacen-{{ $almacen->id }}"
                                    wire:click="setFiltroAlmacen({{ $almacen->id }})"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-colors {{ $filtroAlmacen === $almacen->id ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-100 bg-slate-50 text-slate-600 hover:bg-slate-100' }}"
                                >
                                    <span class="truncate font-medium">{{ $almacen->nombre }}</span>
                                    @if ($filtroAlmacen === $almacen->id)
                                        <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    @endif
                                </button>
                            @endforeach

                            @if ($almacenes->isEmpty())
                                <p class="py-2 text-center text-xs text-slate-400">Sin almacenes</p>
                            @endif
                        </div>
                    </div>

                    {{-- Categorías --}}
                    @if ($categorias->isNotEmpty())
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <p class="mb-2 text-[11px] font-bold tracking-wider text-slate-500 uppercase">Categorías</p>
                            <div class="space-y-1.5">
                                <button
                                    type="button"
                                    wire:click="setFiltroCategoria(null)"
                                    class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition-colors {{ $filtroCategoria === null ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-100 bg-slate-50 text-slate-600 hover:bg-slate-100' }}"
                                >
                                    <span class="font-medium">Todas las categorías</span>
                                </button>

                                @foreach ($categorias as $categoria)
                                    <button
                                        type="button"
                                        wire:key="filtro-categoria-{{ $categoria->id }}"
                                        wire:click="setFiltroCategoria({{ $categoria->id }})"
                                        class="flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-colors {{ $filtroCategoria === $categoria->id ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-100 bg-slate-50 text-slate-600 hover:bg-slate-100' }}"
                                    >
                                        <span class="truncate font-medium">{{ $categoria->nombre }}</span>
                                        @if ($filtroCategoria === $categoria->id)
                                            <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </aside>

            {{-- Columna principal --}}
            <main class="w-full space-y-5 lg:col-span-9 xl:col-span-10">

                {{-- -------------------- PRODUCTOS -------------------- --}}
                @if ($tabActivo === 'productos')
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-2 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">Catálogo</h2>
                                <p class="text-xs text-slate-500">
                                    {{ $totalProductos }} {{ $totalProductos === 1 ? 'producto' : 'productos' }} disponibles
                                    @if ($filtroAlmacen || $filtroCategoria)
                                        <span class="text-primary">(filtrados)</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if ($productos->isEmpty())
                            <div class="py-12 text-center text-slate-400">
                                <svg class="mx-auto mb-3 h-10 w-10 opacity-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                                </svg>
                                <p class="text-sm font-medium">No hay productos disponibles</p>
                                <p class="mt-1 text-xs">Prueba cambiando los filtros</p>
                            </div>
                        @else
                            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-4">
                                @foreach ($productos as $producto)
                                    @include('livewire.partials.product-card', [
                                        'producto' => $producto,
                                        'stock' => $this->stock($producto),
                                        'imagen' => $this->imagenProducto($producto),
                                    ])
                                @endforeach
                            </div>

                            {{-- Cargar más / scroll infinito --}}
                            @if ($productos->count() < $totalProductos)
                                <div
                                    x-data="{
                                        cargando: false,
                                        init() {
                                            const observer = new IntersectionObserver((entries) => {
                                                if (! entries[0].isIntersecting || this.cargando) {
                                                    return;
                                                }

                                                this.cargando = true;
                                                $wire.cargarMas().finally(() => {
                                                    this.cargando = false;
                                                });
                                            }, { rootMargin: '300px' });

                                            observer.observe(this.$refs.sentinel);
                                        }
                                    }"
                                    class="pt-6 text-center"
                                >
                                    <div x-ref="sentinel" class="h-1 w-full"></div>

                                    <button
                                        type="button"
                                        wire:click="cargarMas"
                                        wire:loading.attr="disabled"
                                        wire:target="cargarMas"
                                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary/50 hover:bg-primary/5 hover:text-primary disabled:opacity-60"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                        Cargar más productos
                                    </button>

                                    <p wire:loading wire:target="cargarMas" class="mt-2 text-xs text-slate-500">
                                        Cargando...
                                    </p>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                {{-- -------------------- INFORMACIÓN -------------------- --}}
                @if ($tabActivo === 'informacion')
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <h2 class="mb-4 text-lg font-bold text-slate-900">Información de la tienda</h2>

                        <div class="space-y-3 text-sm">
                            @if ($store->description)
                                <p class="whitespace-pre-line text-slate-600">{{ $store->description }}</p>
                            @endif

                            @if ($store->user?->comuna || $store->user?->region)
                                <p class="text-slate-600">
                                    <span class="font-semibold text-slate-800">Ubicación:</span>
                                    {{ $store->user->comuna ?: $store->user->region }}
                                </p>
                            @endif

                            @if ($store->email)
                                <p class="text-slate-600">
                                    <span class="font-semibold text-slate-800">Email:</span>
                                    {{ $store->email }}
                                </p>
                            @endif

                            @if ($store->phone)
                                <p class="text-slate-600">
                                    <span class="font-semibold text-slate-800">Teléfono:</span>
                                    {{ $store->phone }}
                                </p>
                            @endif
                        </div>

                        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                            <div class="mb-4 border-b border-slate-100 pb-3">
                                <h3 class="flex items-center gap-2 text-base font-bold text-slate-900">
                                    <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/>
                                    </svg>
                                    Almacenes y ubicación
                                </h3>
                            </div>

                            @if ($almacenes->isEmpty())
                                <div class="py-6 text-center text-slate-400">
                                    <p class="text-xs">Esta tienda aún no tiene sucursales configuradas</p>
                                </div>
                            @else
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach ($almacenes as $almacen)
                                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-3.5" wire:key="almacen-{{ $almacen->id }}">
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="text-sm font-bold text-slate-900">{{ $almacen->nombre }}</p>
                                                @if ($almacen->tipo)
                                                    <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-bold tracking-wider text-slate-500 uppercase ring-1 ring-slate-200">
                                                        {{ $almacen->tipo }}
                                                    </span>
                                                @endif
                                            </div>

                                            @if ($almacen->codigo)
                                                <p class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">{{ $almacen->codigo }}</p>
                                            @endif

                                            @if ($almacen->direccion)
                                                <p class="mt-2 text-sm break-words text-slate-700">{{ $almacen->direccion }}</p>
                                            @endif

                                            @if ($almacen->telefono)
                                                <p class="mt-1 text-sm text-slate-700">{{ $almacen->telefono }}</p>
                                            @endif

                                            @if ($almacen->responsable)
                                                <p class="mt-1 text-sm text-slate-700">
                                                    <span class="font-semibold text-slate-800">Responsable:</span>
                                                    {{ $almacen->responsable }}
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- -------------------- OPINIONES -------------------- --}}
                @if ($tabActivo === 'opiniones')
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <h2 class="mb-4 flex items-center justify-between text-lg font-bold text-slate-900">
                            Opiniones de clientes
                            <span class="text-sm font-normal text-slate-500">
                                {{ $reviews->count() }} {{ $reviews->count() === 1 ? 'opinión' : 'opiniones' }}
                            </span>
                        </h2>

                        @if ($usuarioAutenticado && $haComprado)
                            <form wire:submit="guardarOpinion" class="mb-5 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <label for="nuevoComentario" class="mb-1.5 block text-sm font-semibold text-slate-800">
                                    {{ $yaOpino ? 'Actualiza tu opinión' : 'Déjanos tu opinión' }}
                                </label>
                                <textarea
                                    id="nuevoComentario"
                                    wire:model="nuevoComentario"
                                    rows="3"
                                    class="w-full rounded-lg border border-slate-200 bg-white p-2.5 text-sm text-slate-800 focus:border-primary focus:outline-none"
                                    placeholder="Cuéntanos tu experiencia con esta tienda"
                                ></textarea>
                                @error('nuevoComentario')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                                <button
                                    type="submit"
                                    class="mt-2 inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary/90"
                                >
                                    Publicar opinión
                                </button>
                            </form>
                        @endif

                        @if ($reviews->isEmpty())
                            <div class="py-8 text-center text-slate-400">
                                <p class="font-medium">No hay opiniones aún</p>
                                <p class="mt-1 text-sm">Sé el primero en opinar</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($reviews as $review)
                                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-3.5" wire:key="review-{{ $review->id }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-bold text-slate-900">{{ $review->user?->name ?? 'Cliente' }}</p>
                                            <span class="text-[11px] text-slate-400">{{ $review->created_at?->diffForHumans() }}</span>
                                        </div>
                                        <p class="mt-1 text-sm whitespace-pre-line text-slate-600">{{ $review->comentario }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </main>
        </div>
    </div>

    {{-- ==================== CARRITO FLOTANTE ==================== --}}
    <button
        type="button"
        x-on:click="carritoAbierto = true"
        class="fixed right-4 bottom-4 z-40 inline-flex items-center gap-2 rounded-full bg-primary px-5 py-3 text-sm font-bold text-white shadow-lg transition-transform hover:scale-105 sm:right-6 sm:bottom-6"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
        </svg>
        Carrito
        @if ($unidadesCarrito > 0)
            <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-white px-1.5 text-xs font-bold text-primary">
                {{ rtrim(rtrim(number_format($unidadesCarrito, 2, '.', ''), '0'), '.') }}
            </span>
        @endif
    </button>

    {{-- Panel lateral del carrito --}}
    <div
        x-show="carritoAbierto"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-slate-900/40"
        x-on:click="carritoAbierto = false"
    ></div>

    <aside
        x-show="carritoAbierto"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 z-50 flex h-full w-full max-w-md flex-col bg-white shadow-2xl"
    >
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="text-base font-bold text-slate-900">Tu carrito</h2>
            <button type="button" x-on:click="carritoAbierto = false" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Cerrar carrito">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-3">
            @if (empty($itemsCarrito))
                <div class="flex h-full flex-col items-center justify-center text-center text-slate-400">
                    <svg class="mb-3 h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272"/>
                    </svg>
                    <p class="text-sm font-medium">Tu carrito está vacío</p>
                </div>
            @else
                <ul class="space-y-3">
                    @foreach ($itemsCarrito as $item)
                        <li class="flex gap-3 rounded-lg border border-slate-100 bg-slate-50 p-2.5" wire:key="carrito-{{ $item['producto']->id }}">
                            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-md bg-slate-200">
                                @if ($this->imagenProducto($item['producto']))
                                    <img src="{{ $this->imagenProducto($item['producto']) }}" alt="{{ $item['producto']->nombre }}" class="h-full w-full object-cover">
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 text-sm font-semibold text-slate-900">{{ $item['producto']->nombre }}</p>
                                <p class="text-xs text-slate-500">${{ number_format((float) $item['producto']->precio_venta, 0, ',', '.') }} c/u</p>

                                <div class="mt-1.5 flex items-center gap-2">
                                    <div class="inline-flex items-center rounded-md border border-slate-200 bg-white">
                                        <button type="button" wire:click="decrementar({{ $item['producto']->id }})" class="px-2 py-1 text-slate-600 hover:bg-slate-100" aria-label="Quitar una unidad">−</button>
                                        <span class="min-w-8 text-center text-xs font-semibold">{{ rtrim(rtrim(number_format($item['cantidad'], 2, '.', ''), '0'), '.') }}</span>
                                        <button type="button" wire:click="incrementar({{ $item['producto']->id }})" class="px-2 py-1 text-slate-600 hover:bg-slate-100" aria-label="Agregar una unidad">+</button>
                                    </div>

                                    <button type="button" wire:click="quitar({{ $item['producto']->id }})" class="text-[11px] font-semibold text-rose-600 hover:underline">
                                        Quitar
                                    </button>
                                </div>
                            </div>

                            <p class="shrink-0 text-sm font-bold text-slate-900">
                                ${{ number_format($item['subtotal'], 0, ',', '.') }}
                            </p>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3">
                    <span class="text-sm font-semibold text-slate-600">Total</span>
                    <span class="text-lg font-bold text-slate-900">${{ number_format($totalCarrito, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        @if (! empty($itemsCarrito))
            <div class="max-h-[55%] overflow-y-auto border-t border-slate-200 px-4 py-3">
                @if ($usuarioAutenticado)
                    <form method="POST" action="{{ route('tienda.checkout', $store->slug) }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="public_profile_id" value="{{ $store->id }}">

                        @foreach ($itemsCarrito as $item)
                            <input type="hidden" name="items[{{ $loop->index }}][producto_id]" value="{{ $item['producto']->id }}">
                            <input type="hidden" name="items[{{ $loop->index }}][cantidad]" value="{{ $item['cantidad'] }}">
                        @endforeach

                        <p class="text-xs font-bold tracking-wider text-slate-500 uppercase">Datos de entrega</p>

                        <input
                            type="text"
                            name="nombre_cliente"
                            value="{{ auth()->user()->name }}"
                            required
                            placeholder="Nombre completo"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                        >

                        <input
                            type="text"
                            name="telefono_cliente"
                            placeholder="Teléfono"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                        >

                        <input
                            type="text"
                            name="direccion_cliente"
                            placeholder="Dirección de entrega"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                        >

                        <select
                            name="metodo_pago"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                        >
                            @foreach ($metodosPago as $metodo)
                                <option value="{{ $metodo }}">{{ ucfirst($metodo) }}</option>
                            @endforeach
                        </select>

                        <button
                            type="submit"
                            class="mt-1 w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary/90"
                        >
                            Confirmar pedido
                        </button>
                    </form>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="block w-full rounded-lg bg-primary px-4 py-2.5 text-center text-sm font-bold text-white transition-colors hover:bg-primary/90"
                    >
                        Inicia sesión para comprar
                    </a>
                @endif

                <button
                    type="button"
                    wire:click="vaciar"
                    class="mt-2 w-full rounded-lg px-4 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-100"
                >
                    Vaciar carrito
                </button>
            </div>
        @endif
    </aside>
</div>

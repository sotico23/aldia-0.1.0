<aside aria-label="Navegación principal" class="fixed inset-y-0 left-0 z-50 hidden w-64 flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground lg:flex" :class="{ '!flex': leftSidebarOpen }">
    <div class="flex items-center justify-between p-4">
        <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="flex w-full items-center justify-center">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $appName }}" class="h-20 w-full rounded-xl object-contain">
            @else
                <span class="text-xl font-bold text-primary">{{ $appName }}</span>
            @endif
        </a>
        <button type="button" @click="leftSidebarOpen = false" class="p-2 lg:hidden" aria-label="Cerrar navegación"><x-sidebar-icon name="X" /></button>
    </div>
    <nav class="min-h-0 flex-1 space-y-4 overflow-y-auto px-2 pb-4">
        @foreach ($groups as $group => $items)
            <section wire:key="navigation-{{ $group }}">
                <h2 class="px-2 py-3 text-[10px] font-black tracking-widest uppercase opacity-50">{{ $group }}</h2>
                @foreach ($items as $item)
                    <x-sidebar-menu-item :item="$item" />
                @endforeach
            </section>
        @endforeach
    </nav>
    @auth
        <footer class="border-t border-sidebar-border p-2">
            <x-sidebar-menu-item :item="['title' => 'Mi Información', 'href' => route('mi-informacion.show'), 'icon' => 'User']" />
            @if ($canConfigureWeb)
                <x-sidebar-menu-item :item="['title' => 'Configuración Web', 'href' => url('/configuracion-web'), 'icon' => 'Settings']" />
            @endif
        </footer>
    @endauth
</aside>

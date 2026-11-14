<aside aria-label="Mi cuenta" class="fixed inset-y-0 right-0 z-50 hidden w-64 flex-col border-l border-sidebar-border bg-sidebar text-sidebar-foreground xl:flex" :class="{ '!flex': rightSidebarOpen }" @if ($user) wire:poll.60s @endif>
    <div class="flex items-center justify-between px-4 pt-4">
        <h2 class="text-xs font-medium">{{ $user ? 'Usuario' : 'Mi cuenta' }}</h2>
        <button type="button" @click="rightSidebarOpen = false" class="p-2 xl:hidden" aria-label="Cerrar cuenta"><x-sidebar-icon name="X" /></button>
    </div>
    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-2">
        @if ($user)
            <section class="rounded-lg bg-sidebar-accent/50 p-3">
                <img src="{{ $user->profilePhotoUrl() }}" alt="" class="mb-1 size-8 rounded-full object-cover">
                <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                <p class="truncate text-xs text-muted-foreground">{{ $user->email }}</p>
                @if ($user->publicProfile)
                    <form action="{{ route('public-profile.toggle-active') }}" method="POST" class="mt-1">
                        @csrf
                        <button type="submit" class="flex items-center gap-1 text-xs text-primary"><x-sidebar-icon name="Store" class="size-3" />{{ $user->publicProfile->is_active ? 'Tienda activa' : 'Activar tienda' }}</button>
                    </form>
                @endif
                <a href="{{ route('public-profile.edit') }}" class="mt-3 flex items-center gap-1 text-xs text-primary hover:underline"><x-sidebar-icon name="Store" class="size-3" />Configurar Tienda</a>
            </section>
            <nav aria-label="Opciones de la cuenta" class="space-y-3 border-t border-sidebar-border pt-4">
                @foreach ($menus as $menu)
                    <div wire:key="account-{{ $menu['title'] }}"><x-sidebar-menu-item :item="$menu" /></div>
                @endforeach
            </nav>
            @if ($canViewSales && $pendingOrders > 0)
                <section class="border-t border-sidebar-border pt-4">
                    <h3 class="flex items-center gap-2 px-2 text-xs"><x-sidebar-icon name="ShoppingCart" />Pedidos Pendientes <span class="rounded-full bg-amber-500 px-2 text-white">{{ $pendingOrders }}</span></h3>
                    <a href="{{ url('/pedidos-recibidos') }}" class="block p-2 text-xs text-primary">Ver pedidos</a>
                    @include('livewire.partials.sidebar-notifications', ['notifications' => $orderNotifications, 'fallbackUrl' => url('/pedidos-recibidos')])
                </section>
            @endif
            <section class="border-t border-sidebar-border pt-4">
                <h3 class="flex items-center gap-2 px-2 text-xs"><x-sidebar-icon name="MessageSquare" />Mensajes del Chat</h3>
                @include('livewire.partials.sidebar-notifications', ['notifications' => $chatNotifications, 'fallbackUrl' => url('/chat'), 'emptyMessage' => 'No hay mensajes nuevos'])
            </section>
            <section class="border-t border-sidebar-border pt-4">
                <h3 class="flex items-center gap-2 px-2 text-xs"><x-sidebar-icon name="Bell" />Notificaciones sociales</h3>
                @include('livewire.partials.sidebar-notifications', ['notifications' => $socialNotifications, 'fallbackUrl' => route('comunidad.index'), 'emptyMessage' => 'No tienes notificaciones'])
                @if ($socialNotifications->isNotEmpty())
                    <button type="button" wire:click="markNotificationsAsRead" wire:loading.attr="disabled" class="w-full p-2 text-center text-[10px] text-primary hover:underline">Marcar todas como leídas</button>
                @endif
            </section>
            <section class="border-t border-sidebar-border pt-4">
                <h3 class="flex items-center gap-2 px-2 text-xs"><x-sidebar-icon name="MessageSquare" />Conversaciones @if ($unreadMessages) <span class="rounded-full bg-primary px-2 text-white">{{ $unreadMessages }}</span> @endif</h3>
                @forelse ($conversations as $message)
                    @php($otherUser = $message->sender_id === $user->id ? $message->receiver : $message->sender)
                    <a wire:key="conversation-{{ $message->id }}" href="{{ route('mensajes.index') }}" class="mt-2 block rounded-lg bg-sidebar-accent/30 p-2.5 hover:bg-sidebar-accent/50">
                        <p class="truncate text-[11px] font-medium">{{ $otherUser?->name ?? 'Usuario' }}</p>
                        <p class="truncate text-[10px] text-muted-foreground">{{ $message->contenido }}</p>
                    </a>
                @empty
                    <p class="py-4 text-center text-xs text-muted-foreground italic">Sin conversaciones activas</p>
                @endforelse
                <a href="{{ url('/chat') }}" class="mt-2 block rounded-lg bg-sidebar-accent px-3 py-1.5 text-center text-xs font-medium">Ir al Chat</a>
            </section>
            <form action="{{ route('logout') }}" method="POST" class="border-t border-sidebar-border pt-4">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-sm hover:bg-sidebar-accent"><x-sidebar-icon name="LogOut" />Cerrar sesión</button>
            </form>
        @else
            <p class="p-3 text-sm text-muted-foreground">Ingresa para ver tu perfil y tu tienda.</p>
            <a href="{{ route('login') }}" class="block rounded-lg bg-primary px-3 py-2 text-center text-sm font-semibold text-primary-foreground">Ingresar</a>
        @endif
    </div>
</aside>

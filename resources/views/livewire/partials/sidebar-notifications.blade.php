<div class="mt-3 space-y-2">
    @forelse ($notifications as $notification)
        <div wire:key="notification-{{ $notification->id }}" @class(['relative rounded-lg border border-sidebar-border p-2 pr-8 text-xs', 'bg-primary/5' => ! $notification->read_at])>
            <a href="{{ $notification->data['link'] ?? $fallbackUrl }}" class="block">
                <span class="font-semibold">{{ $notification->data['user_name'] ?? $notification->data['title'] ?? 'Notificación' }}</span>
                <p class="mt-1 line-clamp-2 text-muted-foreground">{{ $notification->data['message'] ?? '' }}</p>
                <time class="text-[9px] text-muted-foreground" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
            </a>
            <button type="button" wire:click="deleteNotification('{{ $notification->id }}')" wire:loading.attr="disabled" class="absolute top-1 right-1 rounded-full p-1 hover:bg-destructive/10 hover:text-destructive" aria-label="Eliminar notificación"><x-sidebar-icon name="X" class="size-3" /></button>
        </div>
    @empty
        @isset($emptyMessage)<p class="py-4 text-center text-xs text-muted-foreground">{{ $emptyMessage }}</p>@endisset
    @endforelse
</div>

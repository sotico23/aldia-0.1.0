<?php

namespace App\Livewire;

use App\Models\Mensaje;
use App\Models\Pedido;
use App\Support\SidebarNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AppRightSidebar extends Component
{
    public function deleteNotification(string $id): void
    {
        abort_unless(Auth::check(), 403);
        Auth::user()->notifications()->findOrFail($id)->delete();
    }

    public function markNotificationsAsRead(): void
    {
        abort_unless(Auth::check(), 403);
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $user = Auth::user();
        $notifications = $user?->notifications()->latest()->limit(10)->get() ?? collect();
        $orderTypes = ['nuevo_pedido', 'actualizacion_pedido'];
        $chatTypes = ['mensaje_chat_pedido', 'mensaje_chat'];
        $conversations = collect();
        $unreadMessages = 0;
        $menus = SidebarNavigation::filter(config('sidebar-account'), $user);

        if (SidebarNavigation::allows($user, 'general.comunidad.edit')) {
            $userId = $user->id;
            $latestMessages = Mensaje::query()->selectRaw('MAX(id)')
                ->where(fn ($query) => $query->where('sender_id', $userId)->orWhere('receiver_id', $userId))
                ->groupByRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END', [$userId]);
            $conversations = Mensaje::query()->whereIn('id', $latestMessages)
                ->with(['sender:id,name', 'receiver:id,name'])->latest()->limit(5)->get();
            $unreadMessages = Mensaje::where('receiver_id', $userId)->where('leido', false)->count();
        }

        return view('livewire.app-right-sidebar', [
            'user' => $user,
            'menus' => $menus,
            'canViewSales' => SidebarNavigation::allows($user, 'comercial.oportunidades.viewAny'),
            'pendingOrders' => $user && SidebarNavigation::allows($user, 'comercial.oportunidades.viewAny')
                ? Pedido::where('user_id', $user->id)->where('estado', 'pendiente')->count() : 0,
            'orderNotifications' => $notifications->filter(fn ($notification): bool => in_array($notification->data['tipo'] ?? '', $orderTypes, true))->take(3),
            'chatNotifications' => $notifications->filter(fn ($notification): bool => in_array($notification->data['tipo'] ?? '', $chatTypes, true))->take(5),
            'socialNotifications' => $notifications->reject(fn ($notification): bool => in_array($notification->data['tipo'] ?? '', [...$orderTypes, ...$chatTypes], true))->take(5),
            'conversations' => $conversations,
            'unreadMessages' => $unreadMessages,
        ]);
    }
}

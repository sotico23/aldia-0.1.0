<?php

namespace App\Livewire;

use App\Models\WebSetting;
use App\Support\SidebarNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AppSidebar extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        $items = $user ? SidebarNavigation::filter(config('sidebar-navigation'), $user) : [
            ['title' => 'Inicio', 'href' => route('home'), 'icon' => 'Building2'],
            ['title' => 'Marketplace', 'href' => route('marketplace.index'), 'icon' => 'Store'],
        ];
        $settings = WebSetting::getSettings();

        return view('livewire.app-sidebar', [
            'groups' => collect($items)->groupBy(fn (array $item): string => $item['group'] ?? 'GENERAL'),
            'logo' => $user?->businessLogoUrl() ?: $settings->app_logo,
            'appName' => $settings->app_name ?: config('app.name'),
            'canConfigureWeb' => SidebarNavigation::allows($user, ['admin.web-settings.viewAny', 'admin.configuracion.viewAny']),
        ]);
    }
}

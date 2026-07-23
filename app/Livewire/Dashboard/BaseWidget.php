<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

abstract class BaseWidget extends Component
{
    public string $widgetKey;

    public array $settings = [];

    public int $orderIndex = 0;

    /**
     * Define the Spatie permission(s) required to view this widget.
     * Must return a string or an array of permission strings.
     */
    abstract public function permission(): string|array;

    /**
     * Mount initial settings and verify authorization.
     */
    public function mount(string $widgetKey, array $settings = [], int $orderIndex = 0): void
    {
        $this->widgetKey = $widgetKey;
        $this->settings = $settings;
        $this->orderIndex = $orderIndex;

        $this->authorizeWidget();
    }

    /**
     * Check if the user is strictly authorized to view this widget using Spatie.
     */
    protected function authorizeWidget(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Unauthenticated user.');
        }

        $permission = $this->permission();

        if (is_array($permission)) {
            if (! $user->hasAnyPermission($permission)) {
                abort(403, "No autorizado para ver el widget: {$this->widgetKey}");
            }
        } else {
            if (! $user->can($permission)) {
                abort(403, "No autorizado para ver el widget: {$this->widgetKey}");
            }
        }
    }

    /**
     * Update configuration and notify parent dashboard component.
     */
    public function updateWidgetSettings(array $newSettings): void
    {
        $this->settings = array_merge($this->settings, $newSettings);

        $this->dispatch('widget-settings-updated', [
            'widgetKey' => $this->widgetKey,
            'settings' => $this->settings,
        ]);
    }
}

<?php

namespace App\Services;

use App\Jobs\SendDeliveryPush;
use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Models\PushDevice;
use App\Models\Repartidor;
use App\Scopes\OwnerScope;
use App\Support\SystemSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DeliveryPushService
{
    /** @return array{enabled: bool, secret_api_key: string, app_id: string|null} */
    private function settings(): array
    {
        $values = SystemSettings::values();

        return [
            'enabled' => filter_var($values['PUSHY_ENABLED'] ?? config('services.pushy.fallback.enabled', false), FILTER_VALIDATE_BOOLEAN),
            'secret_api_key' => (string) ($values['PUSHY_SECRET_API_KEY'] ?? config('services.pushy.fallback.secret_api_key', '')),
            'app_id' => $values['PUSHY_APP_ID'] ?? config('services.pushy.fallback.app_id'),
        ];
    }

    public function isEnabled(): bool
    {
        $settings = $this->settings();

        return $settings['enabled'] && $settings['secret_api_key'] !== '';
    }

    /** @return array{enabled: bool, app_id: string|null} */
    public function publicConfiguration(): array
    {
        return ['enabled' => $this->isEnabled(), 'app_id' => $this->settings()['app_id']];
    }

    public function queueAlerts(Pedido $pedido): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        $pedido = $pedido->fresh();
        if (! $pedido || ! in_array($pedido->estado, ['pendiente', 'preparando'], true)) {
            return;
        }
        $type = $pedido->repartidor_id ? 'assigned' : 'pool';
        $key = $type === 'assigned' ? $pedido->assignmentAlertKey() : $pedido->poolAlertKey();
        Repartidor::withoutGlobalScope(OwnerScope::class)
            ->where('approval_status', Repartidor::APPROVAL_APPROVED)
            ->when($type === 'assigned', fn ($query) => $query->where('user_id', $pedido->repartidor_id))
            ->when($type === 'pool', fn ($query) => $query->where('is_online', true)->where('estado', 'disponible'))
            ->whereHas('user', fn ($query) => $query->where('is_active', true)->whereNull('banned_at'))
            ->with('user:id,is_active,banned_at')
            ->chunkById(100, function ($drivers) use ($pedido, $type, $key): void {
                foreach ($drivers as $driver) {
                    if (! $this->canReceive($pedido, $driver, $type, true)) {
                        continue;
                    }
                    foreach (PushDevice::where('user_id', $driver->user_id)->get() as $device) {
                        $job = new SendDeliveryPush($device->id, $device->user_id, $pedido->id, $type, $key);
                        if ($type === 'pool' && $pedido->pool_visible_at?->isFuture()) {
                            $job->delay($pedido->pool_visible_at);
                        }
                        dispatch($job);
                    }
                }
            });
    }

    public function canReceive(Pedido $pedido, Repartidor $driver, string $type, bool $ignoreVisibleUntil = false): bool
    {
        if (! $driver->isApproved() || ! $driver->user?->is_active || $driver->user?->banned_at !== null
            || ! in_array($pedido->estado, ['pendiente', 'preparando'], true)) {
            return false;
        }
        if ($type === 'assigned') {
            return (int) $pedido->repartidor_id === (int) $driver->user_id;
        }
        if ($type !== 'pool' || ! $driver->is_online || ! $driver->isDisponible()
            || $pedido->repartidor_id !== null || $pedido->hora_aceptado !== null || $pedido->pool_bloqueado
            || (! $ignoreVisibleUntil && $pedido->pool_visible_at?->isFuture())) {
            return false;
        }
        if (DeliveryConfig::withoutGlobalScope(OwnerScope::class)->where('owner_id', $driver->owner_id)->value('modo') === 'manual') {
            return false;
        }
        $sameOwner = (int) $pedido->owner_id === (int) $driver->owner_id;
        if (! $sameOwner && ! ($driver->isFreelancer() && $pedido->dispatch_mode === Pedido::DISPATCH_MARKETPLACE)) {
            return false;
        }
        if ($pedido->destino_lat === null || $pedido->destino_lng === null) {
            return $sameOwner && $pedido->entrega()->withoutGlobalScope(OwnerScope::class)->where('owner_id', $driver->owner_id)->exists();
        }
        $distance = $driver->distanciaA((float) $pedido->destino_lat, (float) $pedido->destino_lng);

        return $distance !== null && $distance <= (float) $driver->radio_km;
    }

    public function send(PushDevice $device, Pedido $pedido, string $type, string $key): bool
    {
        try {
            $response = Http::asJson()->withQueryParameters(['api_key' => $this->settings()['secret_api_key']])
                ->connectTimeout(3)->timeout(10)->post('https://api.pushy.me/push', [
                    'to' => $device->token,
                    'time_to_live' => $type === 'pool' ? 60 : 300,
                    'collapse_key' => substr(hash('sha256', $key), 0, 32),
                    'data' => ['user_id' => $device->user_id, 'order_id' => $pedido->id, 'type' => $type, 'alert_key' => $key],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con el proveedor push.');
        }
        if ($response->status() === 429 || $response->serverError()) {
            throw new RuntimeException('El proveedor push no está disponible temporalmente.');
        }
        if ($response->json('code') === 'NO_RECIPIENTS' || in_array($device->token, $response->json('info.failed', []), true)) {
            $device->delete();

            return false;
        }
        if (! $response->successful() || $response->json('success') !== true) {
            Log::warning('El proveedor push rechazó el envío.', ['device_id' => $device->id, 'status' => $response->status()]);

            return false;
        }

        return true;
    }
}

<?php

namespace App\Jobs;

use App\Models\Pedido;
use App\Models\PushDevice;
use App\Models\Repartidor;
use App\Scopes\OwnerScope;
use App\Services\ApiTokenService;
use App\Services\DeliveryPushService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDeliveryPush implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $timeout = 20;

    public int $uniqueFor = 300;

    public function __construct(public int $deviceId, public int $userId, public int $pedidoId, public string $type, public string $alertKey)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return hash('sha256', $this->deviceId.':'.$this->alertKey);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    /**
     * Execute the job.
     */
    public function handle(DeliveryPushService $service): void
    {
        if (! $service->isEnabled()) {
            return;
        }
        $device = PushDevice::with('accessToken')->find($this->deviceId);
        if (! $device || (int) $device->user_id !== $this->userId || ! $device->accessToken
            || app(ApiTokenService::class)->isExpired($device->accessToken)) {
            return;
        }
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->find($this->pedidoId);
        $driver = Repartidor::withoutGlobalScope(OwnerScope::class)->with('user')->where('user_id', $this->userId)->first();
        if (! $pedido || ! $driver || ! $service->canReceive($pedido, $driver, $this->type)) {
            return;
        }
        $key = $this->type === 'assigned' ? $pedido->assignmentAlertKey() : $pedido->poolAlertKey();
        if ($key !== $this->alertKey) {
            return;
        }
        $sentKey = 'delivery-push-sent:'.$this->uniqueId();
        $lock = Cache::lock('delivery-push-lock:'.$this->uniqueId(), 25);
        if (! $lock->get()) {
            $this->release(5);

            return;
        }
        try {
            if (! Cache::has($sentKey) && $service->send($device, $pedido, $this->type, $key)) {
                Cache::put($sentKey, true, now()->addDays(30));
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('No se pudo entregar la alerta push tras los reintentos.', ['device_id' => $this->deviceId, 'pedido_id' => $this->pedidoId]);
    }
}

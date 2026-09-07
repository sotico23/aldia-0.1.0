<?php

namespace App\Jobs;

use App\Models\Pedido;
use App\Scopes\OwnerScope;
use App\Services\ZoneAssignment;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AsignacionAutomaticaJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 120;

    /** @var int[] Seconds to wait between retry attempts */
    public array $backoff = [10, 60];

    public function __construct(public int $pedidoId) {}

    public function uniqueId(): string
    {
        return 'asignacion:'.$this->pedidoId;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AsignacionAutomaticaJob exhausted all retries', [
            'pedido_id' => $this->pedidoId,
            'job_uuid' => $this->job?->uuid(),
            'error' => $e->getMessage(),
        ]);
    }

    public function handle(ZoneAssignment $assignment): void
    {
        $pedido = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->find($this->pedidoId);

        if (! $pedido) {
            return;
        }

        $assignment->asignarSiPosible($pedido);
    }
}

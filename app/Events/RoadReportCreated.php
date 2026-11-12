<?php

namespace App\Events;

use App\Models\RoadReport;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoadReportCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public RoadReport $report;

    public function __construct(RoadReport $report)
    {
        $this->report = $report;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('delivery.map.reports'),
            new PrivateChannel('delivery.reports'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'road.report.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->report->id,
            'user_id' => $this->report->user_id,
            'user_name' => $this->report->user?->name ?? 'Repartidor',
            'type' => $this->report->type,
            'latitude' => (float) $this->report->latitude,
            'longitude' => (float) $this->report->longitude,
            'description' => $this->report->description,
            'created_at' => $this->report->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}

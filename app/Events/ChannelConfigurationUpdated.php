<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChannelConfigurationUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $ownerId,
        public int $userId,
        public string $botType // 'global' or 'custom'
    ) {}
}

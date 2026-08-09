<?php

namespace App\Support;

/**
 * Immutable context resolved by the Bot API middleware for the current request.
 */
final readonly class BotContext
{
    public function __construct(
        public int $ownerId,
        public int $actingUserId,
    ) {}
}

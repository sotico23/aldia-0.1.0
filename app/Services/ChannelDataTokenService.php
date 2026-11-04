<?php

namespace App\Services;

use App\Models\ChannelDataToken;
use Illuminate\Support\Str;

class ChannelDataTokenService
{
    public function tokenForOwner(int $ownerId): string
    {
        $token = Str::random(64);

        $record = ChannelDataToken::firstOrCreate(
            ['owner_id' => $ownerId],
            [
                'token' => $token,
                'token_hash' => hash('sha256', $token),
            ]
        );

        return $record->token;
    }
}

<?php

namespace App\Http\Controllers\Api\Bot;

use App\Support\OpenApi\BotOpenApi;
use Illuminate\Http\JsonResponse;

class OpenApiController
{
    public function index(): JsonResponse
    {
        return response()->json(BotOpenApi::schema());
    }
}

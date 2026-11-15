<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\StorePushDeviceRequest;
use App\Models\PushDevice;
use App\Services\DeliveryPushService;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class PushDeviceController extends Controller
{
    public function configuration(DeliveryPushService $push): JsonResponse
    {
        return response()->json($push->publicConfiguration());
    }

    public function store(StorePushDeviceRequest $request): JsonResponse
    {
        $accessToken = $request->attributes->get('api_token');
        abort_unless($accessToken instanceof PersonalAccessToken, 401);
        $token = $request->validated('token');
        PushDevice::updateOrCreate(['token_hash' => hash('sha256', $token)], [
            'user_id' => $request->user()->id,
            'personal_access_token_id' => $accessToken->id,
            'token' => $token,
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(StorePushDeviceRequest $request): JsonResponse
    {
        PushDevice::where('user_id', $request->user()->id)
            ->where('token_hash', hash('sha256', $request->validated('token')))->delete();

        return response()->json(['success' => true]);
    }
}

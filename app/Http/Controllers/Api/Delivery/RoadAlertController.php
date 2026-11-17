<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\RoadAlertRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RoadAlertController extends Controller
{
    private const CACHE_KEY = 'delivery:road-alerts:v1';

    public function index(RoadAlertRequest $request): JsonResponse
    {
        $lat = (float) $request->validated('lat');
        $lng = (float) $request->validated('lng');
        $alerts = collect(Cache::get(self::CACHE_KEY, []))
            ->filter(function (array $alert) use ($lat, $lng): bool {
                $distance = sin(deg2rad($alert['lat'] - $lat) / 2) ** 2
                    + cos(deg2rad($lat)) * cos(deg2rad($alert['lat']))
                    * sin(deg2rad($alert['lng'] - $lng) / 2) ** 2;

                return $alert['expires_at'] > now()->toISOString()
                    && 6371 * 2 * asin(sqrt(min(1, $distance))) <= 20;
            })->values()->all();

        return response()->json(['alerts' => $alerts]);
    }

    public function store(RoadAlertRequest $request): JsonResponse
    {
        $alert = [
            'id' => (string) Str::uuid(),
            'type' => $request->validated('type'),
            'lat' => (float) $request->validated('lat'),
            'lng' => (float) $request->validated('lng'),
            'expires_at' => now()->addHour()->toISOString(),
        ];

        Cache::lock(self::CACHE_KEY.':lock', 10)->block(3, function () use ($alert): void {
            $alerts = collect(Cache::get(self::CACHE_KEY, []))
                ->filter(fn (array $item): bool => $item['expires_at'] > now()->toISOString())
                ->take(-1999)->push($alert)->values()->all();
            Cache::put(self::CACHE_KEY, $alerts, now()->addHour());
        });

        return response()->json(['alert' => $alert], 201);
    }
}

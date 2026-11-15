<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PublicProfile;
use App\Models\Repartidor;
use App\Scopes\OwnerScope;

class DeliveryStoreService
{
    /** @return array<int, array{id: int, title: string, address: ?string, lat: ?float, lng: ?float}> */
    public function stores(Repartidor $driver): array
    {
        if (! $driver->isApproved()) {
            return [];
        }

        return PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true)->whereNull('banned_at'))
            ->whereNotNull('pickup_lat')->whereNotNull('pickup_lng')
            ->when(! $driver->isFreelancer(), fn ($query) => $query->where('owner_id', $driver->owner_id))
            ->when($driver->lat !== null && $driver->lng !== null, function ($query) use ($driver): void {
                $latitudeRange = (float) $driver->radio_km / 111.0;
                $longitudeRange = min(180, $latitudeRange / max(0.01, cos(deg2rad((float) $driver->lat))));
                $query->whereBetween('pickup_lat', [$driver->lat - $latitudeRange, $driver->lat + $latitudeRange])
                    ->whereBetween('pickup_lng', [$driver->lng - $longitudeRange, $driver->lng + $longitudeRange]);
            })
            ->orderBy('id')->limit(250)
            ->get(['id', 'title', 'pickup_address', 'pickup_lat', 'pickup_lng'])
            ->filter(fn (PublicProfile $store) => $driver->lat === null || $driver->lng === null
                || $driver->distanciaA($store->pickup_lat, $store->pickup_lng) <= (float) $driver->radio_km)
            ->map(fn (PublicProfile $store) => $this->resource($store))->values()->all();
    }

    /** @return array{id: int, title: string, address: ?string, lat: ?float, lng: ?float}|null */
    public function pickup(Pedido $order): ?array
    {
        $store = $order->public_profile_id ? $order->pickupStore : $order->ownerStore;

        if (! $store || ((int) $store->owner_id !== (int) $order->owner_id && (int) $store->user_id !== (int) $order->owner_id)) {
            return null;
        }

        return $this->resource($store);
    }

    /** @return array{id: int, title: string, address: ?string, lat: ?float, lng: ?float} */
    private function resource(PublicProfile $store): array
    {
        return ['id' => $store->id, 'title' => $store->title, 'address' => $store->pickup_address,
            'lat' => $store->pickup_lat, 'lng' => $store->pickup_lng];
    }
}

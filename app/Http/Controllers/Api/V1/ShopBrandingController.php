<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShopBrandingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ShopBrandingController extends Controller
{
    private const KEY = 'shop.branding';

    private const DEFAULTS = [
        'app_name' => 'RedClienteShop',
        'description' => 'Pedidos a domicilio desde los vendedores del marketplace de RedCliente.',
        'login_description' => 'Ingresa con tu cuenta de RedCliente para guardar tus direcciones y pedidos.',
        'logo_url' => null,
    ];

    public function show(): JsonResponse
    {
        $stored = json_decode(Setting::where('key', self::KEY)->value('value') ?? '{}', true);

        return response()->json(array_replace(
            self::DEFAULTS,
            array_intersect_key(is_array($stored) ? $stored : [], self::DEFAULTS),
        ));
    }

    public function update(ShopBrandingRequest $request): JsonResponse
    {
        $branding = array_replace(self::DEFAULTS, $request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding/shop', 'public');
            abort_unless($path, 500, 'No se pudo guardar el logo.');
            $branding['logo_url'] = url(Storage::disk('public')->url($path));
        }

        Setting::updateOrCreate(['key' => self::KEY], [
            'value' => json_encode($branding, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'group' => 'shop',
            'is_encrypted' => false,
        ]);

        return response()->json($branding);
    }
}

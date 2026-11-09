<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backend\LookupProductByBarcodeRequest;
use App\Models\Producto;
use App\Services\OpenFoodFactsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductoBarcodeController extends Controller
{
    public function __invoke(
        LookupProductByBarcodeRequest $request,
        OpenFoodFactsService $openFoodFacts,
    ): JsonResponse {
        $codigoEAN = $request->validated('ean');
        $producto = Producto::query()
            ->with('categoria')
            ->where('codigo', $codigoEAN)
            ->first();

        if ($producto) {
            return response()->json([
                'encontrado' => true,
                'origen' => 'base_datos',
                'datos' => $this->localProductData($producto),
            ]);
        }

        $result = $openFoodFacts->buscarProductoPorEAN($codigoEAN);

        if ($result['error']) {
            return response()->json([
                'encontrado' => false,
                'origen' => 'api_externa',
                'datos' => null,
                'mensaje' => 'No fue posible consultar Open Food Facts. Inténtalo nuevamente.',
            ], 502);
        }

        return response()->json([
            'encontrado' => $result['encontrado'],
            'origen' => 'api_externa',
            'datos' => $result['datos'],
        ], $result['encontrado'] ? 200 : 404);
    }

    /**
     * @return array{nombre: string, marca: null, categoria: ?string, imagen: ?string, formato: ?string}
     */
    private function localProductData(Producto $producto): array
    {
        $image = $producto->imagen;
        $format = $this->localProductFormat($producto);

        return [
            'nombre' => $producto->nombre,
            'marca' => null,
            'categoria' => $producto->categoria?->nombre,
            'imagen' => $image
                ? (str_starts_with($image, 'https://') ? $image : Storage::disk('public')->url($image))
                : null,
            'formato' => $format,
        ];
    }

    private function localProductFormat(Producto $producto): ?string
    {
        $quantity = (float) ($producto->contenido_por_unidad ?: $producto->peso_por_unidad ?: 0);

        if ($quantity <= 0) {
            return null;
        }

        $quantityLabel = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
        $unit = match ($producto->unidad_medida) {
            'kg' => 'kg',
            'lt' => 'l',
            default => 'unidades',
        };

        return "{$quantityLabel} {$unit}";
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backend\LookupSkuRequest;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Busca un producto por su SKU / código de barras y devuelve todos los datos
 * necesarios para hidratar (autorrellenar) el formulario de creación/edición
 * de productos desde el backend del tenant actual.
 */
class ProductoSkuLookupController extends Controller
{
    public function __invoke(LookupSkuRequest $request): JsonResponse
    {
        $codigo = $request->validated('codigo');

        $producto = Producto::query()
            ->where('codigo', $codigo)
            ->with(['categoria', 'skus.valores.varianteValor', 'inventarios.almacen'])
            ->first();

        if ($producto) {
            return response()->json([
                'encontrado' => true,
                'origen' => 'base_datos',
                'datos' => $this->hydrationData($producto),
                'mensaje' => 'Producto existente encontrado. Se han precargado sus datos.',
            ]);
        }

        return response()->json([
            'encontrado' => false,
            'origen' => 'base_datos',
            'datos' => null,
            'mensaje' => 'SKU no registrado. Puede completar el producto manualmente.',
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     codigo: string|null,
     *     nombre: string,
     *     descripcion: string|null,
     *     categoria_id: int|null,
     *     categoria: string|null,
     *     precio_compra: float,
     *     precio_venta: float,
     *     stock_minimo: int,
     *     stock_total: float,
     *     unidad_medida: string,
     *     medida_pesable: bool,
     *     tiene_variantes: bool,
     *     imagen: string|null,
     *     imagen2: string|null,
     *     imagen3: string|null,
     *     imagen4: string|null,
     *     imagen5: string|null,
     *     video: string|null,
     *     skus: array,
     *     inventarios: array,
     * }
     */
    private function hydrationData(Producto $producto): array
    {
        $imageUrl = static fn (?string $path): ?string =>
            $path ? Storage::disk('public')->url($path) : null;

        return [
            'id' => $producto->id,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'categoria_id' => $producto->categoria_id,
            'categoria' => $producto->categoria?->nombre,
            'precio_compra' => (float) $producto->precio_compra,
            'precio_venta' => (float) $producto->precio_venta,
            'stock_minimo' => (int) $producto->stock_minimo,
            'stock_total' => (float) $producto->stock_total,
            'unidad_medida' => $producto->unidad_medida,
            'medida_pesable' => (bool) $producto->medida_pesable,
            'tiene_variantes' => (bool) $producto->tiene_variantes,
            'imagen' => $imageUrl($producto->imagen),
            'imagen2' => $imageUrl($producto->imagen2),
            'imagen3' => $imageUrl($producto->imagen3),
            'imagen4' => $imageUrl($producto->imagen4),
            'imagen5' => $imageUrl($producto->imagen5),
            'video' => $imageUrl($producto->video),
            'skus' => $producto->skus->map(fn ($sku) => [
                'id' => $sku->id,
                'sku' => $sku->sku,
                'imagen' => $imageUrl($sku->imagen),
                'precio_venta' => (float) ($sku->precio_venta ?? $producto->precio_venta),
                'precio_compra' => (float) ($sku->precio_compra ?? $producto->precio_compra),
                'stock' => (float) $sku->stock,
                'stock_minimo' => (float) $sku->stock_minimo,
                'variante_valores' => $sku->valores->pluck('variante_valor_id')->all(),
                'labels' => $sku->valores->map(fn ($v) => $v->varianteValor?->valor)
                    ->filter()
                    ->all(),
            ])->all(),
            'inventarios' => $producto->inventarios->map(fn ($inv) => [
                'almacen_id' => $inv->almacen_id,
                'almacen' => $inv->almacen?->nombre,
                'cantidad' => (float) $inv->cantidad,
                'cantidad_minima' => (float) $inv->cantidad_minima,
            ])->all(),
        ];
    }
}

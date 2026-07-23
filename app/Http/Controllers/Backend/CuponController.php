<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CuponStoreRequest;
use App\Http\Requests\CuponUpdateRequest;
use App\Models\Cupon;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CuponController extends Controller
{
    public static function middleware(): array
    {
        return [
            'auth',
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = auth()->user()->getOwnerId();

        $cupones = Cupon::query()
            ->with('usuario', 'productos')
            ->where('owner_id', $ownerId)
            ->when($request->search, fn ($q, $v) => $q->where('codigo', 'like', '%'.SearchHelper::escapeLike($v).'%'))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $productos = Producto::where('owner_id', $ownerId)
            ->where('activo', true)
            ->get(['id', 'nombre', 'codigo', 'precio_venta']);

        return Inertia::render('Backend/Cupones/Index', [
            'cupones' => $cupones,
            'productos' => $productos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(CuponStoreRequest $request): RedirectResponse
    {
        $cupon = Cupon::create([
            'codigo' => $request->codigo,
            'tipo' => $request->tipo,
            'valor' => $request->valor ?? 0,
            'descripcion' => $request->descripcion,
            'plantilla_html' => $request->plantilla_html,
            'variables_ejemplo' => $request->variables_ejemplo,
            'max_usos' => $request->max_usos ?? 0,
            'usos_actuales' => 0,
            'usos_por_cliente' => $request->usos_por_cliente ?? 1,
            'compra_minima' => $request->compra_minima,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'activa' => $request->boolean('activa', true),
            'owner_id' => $request->user()->getOwnerId(),
            'user_id' => $request->user()->id,
        ]);

        if ($request->has('productos')) {
            $syncData = [];
            foreach ($request->productos as $producto) {
                $syncData[$producto['id']] = [
                    'descuento_tipo' => $producto['descuento_tipo'] ?? 'porcentaje',
                    'descuento_valor' => $producto['descuento_valor'] ?? 0,
                ];
            }
            $cupon->productos()->sync($syncData);
        }

        return to_route('ventas.cupones.index')
            ->with('success', "Cupón {$cupon->codigo} creado correctamente.");
    }

    public function update(CuponUpdateRequest $request, Cupon $cupon): RedirectResponse
    {
        $cupon->update(array_filter($request->validated(), fn ($v) => $v !== null));

        if ($request->has('productos')) {
            $syncData = [];
            foreach ($request->productos as $producto) {
                $syncData[$producto['id']] = [
                    'descuento_tipo' => $producto['descuento_tipo'] ?? 'porcentaje',
                    'descuento_valor' => $producto['descuento_valor'] ?? 0,
                ];
            }
            $cupon->productos()->sync($syncData);
        }

        return to_route('ventas.cupones.index')
            ->with('success', "Cupón {$cupon->codigo} actualizado correctamente.");
    }

    public function destroy(Cupon $cupon): RedirectResponse
    {
        $ownerId = auth()->user()->getOwnerId();
        if ($cupon->owner_id !== $ownerId) {
            abort(403);
        }

        $codigo = $cupon->codigo;
        $cupon->delete();

        return to_route('ventas.cupones.index')
            ->with('success', "Cupón {$codigo} eliminado correctamente.");
    }

    public function toggle(Cupon $cupon): RedirectResponse
    {
        $ownerId = auth()->user()->getOwnerId();
        if ($cupon->owner_id !== $ownerId) {
            abort(403);
        }

        $cupon->update(['activa' => ! $cupon->activa]);

        $estado = $cupon->activa ? 'activado' : 'desactivado';

        return to_route('ventas.cupones.index')
            ->with('success', "Cupón {$cupon->codigo} {$estado} correctamente.");
    }

    public function preview(Cupon $cupon): JsonResponse
    {
        return response()->json([
            'html' => $cupon->renderizarPreview(),
        ]);
    }

    public function validar(Request $request): JsonResponse
    {
        $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'monto' => ['required', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
        ]);

        $cupon = Cupon::query()
            ->where('codigo', $request->codigo)
            ->active()
            ->with('productos:id,nombre,precio_venta')
            ->first();

        if (! $cupon) {
            return response()->json([
                'valido' => false,
                'mensaje' => 'Cupón no encontrado.',
            ]);
        }

        if (! $cupon->validar((float) $request->monto)) {
            if ($cupon->fecha_fin && $cupon->fecha_fin->isPast()) {
                $mensaje = 'El cupón ha expirado.';
            } elseif ($cupon->max_usos > 0 && $cupon->usos_actuales >= $cupon->max_usos) {
                $mensaje = 'El cupón ha alcanzado su límite de usos.';
            } elseif ($cupon->compra_minima !== null && (float) $request->monto < (float) $cupon->compra_minima) {
                $mensaje = 'El monto mínimo de compra es $'.number_format((float) $cupon->compra_minima, 0, ',', '.');
            } else {
                $mensaje = 'El cupón no es válido.';
            }

            return response()->json([
                'valido' => false,
                'mensaje' => $mensaje,
            ]);
        }

        $items = $request->items ?? [];

        // Transformar items del frontend (id/precio) a formato backend (producto_id/precio_unitario)
        $itemsTransformados = collect($items)->map(fn ($item) => [
            'producto_id' => $item['id'] ?? $item['producto_id'],
            'cantidad' => $item['cantidad'],
            'precio_unitario' => $item['precio'] ?? $item['precio_unitario'],
        ])->toArray();

        $descuento = $cupon->esValeProducto() && ! empty($items)
            ? $cupon->calcularDescuentoProductos($itemsTransformados)
            : $cupon->calcularDescuento((float) $request->monto);

        $productosIds = $cupon->esValeProducto()
            ? $cupon->productos->pluck('id')->toArray()
            : [];

        $productosNombres = $cupon->esValeProducto()
            ? $cupon->productos->pluck('nombre')->toArray()
            : [];

        return response()->json([
            'valido' => true,
            'cupon_id' => $cupon->id,
            'codigo' => $cupon->codigo,
            'tipo' => $cupon->tipo,
            'descuento' => $descuento,
            'productos_ids' => $productosIds,
            'productos_nombres' => $productosNombres,
            'mensaje' => 'Cupón aplicado correctamente. Descuento: $'.number_format($descuento, 0, ',', '.'),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\SkuVariante;
use App\Models\Variante;
use App\Models\VarianteValor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class VarianteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ventas.variantes.create', only: ['create', 'store']),
            new Middleware('permission:ventas.variantes.edit', only: ['edit', 'update']),
            new Middleware('permission:ventas.variantes.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $ownerId = Auth::user()->getOwnerId();
        $variantes = Variante::with('valores')->where('owner_id', $ownerId)->get();

        return Inertia::render('Backend/Pos/Variantes', [
            'variantes' => $variantes,
        ]);
    }

    public function store(Request $request)
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|max:255',
            'valores' => 'required|array|min:1',
            'valores.*' => 'required|string|max:255',
        ]);

        $variante = Variante::create([
            'owner_id' => $ownerId,
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
        ]);

        foreach ($validated['valores'] as $valor) {
            VarianteValor::create([
                'variante_id' => $variante->id,
                'valor' => $valor,
            ]);
        }

        return redirect()->back()->with('success', 'Variante creada correctamente.');
    }

    public function update(Request $request, Variante $variante)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|max:255',
            'valores' => 'required|array|min:1',
            'valores.*' => 'required|string|max:255',
        ]);

        $variante->update([
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
        ]);

        $variante->valores()->delete();
        foreach ($validated['valores'] as $valor) {
            VarianteValor::create([
                'variante_id' => $variante->id,
                'valor' => $valor,
            ]);
        }

        return redirect()->back()->with('success', 'Variante actualizada correctamente.');
    }

    public function destroy(Variante $variante)
    {
        $variante->valores()->delete();
        $variante->delete();

        return redirect()->back()->with('success', 'Variante eliminada correctamente.');
    }

    public function skuIndex()
    {
        $ownerId = Auth::user()->getOwnerId();

        $skus = SkuVariante::with(['producto', 'valores.varianteValor.variante'])
            ->whereHas('producto', fn ($q) => $q->where('owner_id', $ownerId))
            ->get();

        $productos = Producto::where('owner_id', $ownerId)
            ->where('tiene_variantes', true)
            ->where('activo', true)
            ->get(['id', 'nombre', 'codigo']);

        $variantes = Variante::with('valores')
            ->where('owner_id', $ownerId)
            ->get(['id', 'nombre', 'tipo']);

        return Inertia::render('Backend/Pos/SkuManager', [
            'skus' => $skus,
            'productos' => $productos,
            'variantes' => $variantes,
        ]);
    }

    public function skuStore(Request $request)
    {
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'sku' => 'required|string|unique:sku_variantes,sku',
            'precio_venta' => 'nullable|numeric|min:0',
            'precio_compra' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'stock_minimo' => 'nullable|numeric|min:0',
            'variantes' => 'required|array',
            'variantes.*' => 'required|exists:variante_valores,id',
        ]);

        $sku = SkuVariante::create([
            'producto_id' => $validated['producto_id'],
            'sku' => $validated['sku'],
            'precio_venta' => $validated['precio_venta'] ?? null,
            'precio_compra' => $validated['precio_compra'] ?? null,
            'stock' => $validated['stock'] ?? 0,
            'stock_minimo' => $validated['stock_minimo'] ?? 0,
        ]);

        foreach ($validated['variantes'] as $varianteValorId) {
            $sku->valores()->create([
                'variante_valor_id' => $varianteValorId,
            ]);
        }

        return redirect()->back()->with('success', 'SKU creado correctamente.');
    }

    public function skuDestroy(SkuVariante $sku)
    {
        $sku->valores()->delete();
        $sku->delete();

        return redirect()->back()->with('success', 'SKU eliminado correctamente.');
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Exports\ProductosExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\ProductosImport;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\PublicProfile;
use App\Models\SkuVariante;
use App\Models\SkuVarianteValor;
use App\Models\Variante;
use App\Models\VarianteValor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductoController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:comercial.productos.create', only: ['create', 'store']),
            new Middleware('permission:comercial.productos.edit', only: ['edit', 'update']),
            new Middleware('permission:comercial.productos.delete', only: ['destroy']),
            new Middleware('permission:comercial.productos.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:comercial.productos.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function getExportClass(array $filters): object
    {
        return new ProductosExport($filters);
    }

    public function getImportClass(): object
    {
        return new ProductosImport;
    }

    public function show(Producto $producto): Response
    {
        $producto->load(['categoria', 'inventario.almacen', 'variantes.inventarios.almacen']);

        return Inertia::render('Backend/Productos/Show', [
            'producto' => $producto,
        ]);
    }

    public function ver(Producto $producto): Response
    {
        $producto->load(['categoria', 'inventario.almacen', 'variantes.inventarios.almacen']);

        return Inertia::render('Backend/Productos/Show', [
            'producto' => $producto,
        ]);
    }

    public function index(Request $request): Response
    {
        $userId = Auth::user()->getOwnerId();

        $query = Producto::with(['categoria', 'inventario.almacen', 'inventarios.almacen', 'envaseProducto', 'skus.valores.varianteValor'])
            ->where('owner_id', $userId)
            ->whereNull('parent_id');

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->input('categoria_id'));
        }

        if ($request->boolean('stock_bajo')) {
            $query->whereHas('inventario', function ($q) {
                $q->whereColumn('cantidad', '<=', 'cantidad_minima');
            });
        }

        if ($request->filled('almacen_id')) {
            $query->whereHas('inventarios', function ($q) use ($request) {
                $q->where('almacen_id', $request->input('almacen_id'));
            });
        }

        $productos = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $categorias = Categoria::where('tipo', 'producto')
            ->where('activo', true)
            ->where('owner_id', $userId)
            ->get();

        $almacenes = Almacen::where('activo', true)
            ->where('owner_id', $userId)
            ->get();

        $productosEnvase = Producto::where('owner_id', $userId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        $variantesDisponibles = Variante::with('valores')
            ->where('owner_id', $userId)
            ->where('activo', true)
            ->get();

        return Inertia::render('Backend/Productos/Index', [
            'productos' => $productos,
            'categorias' => $categorias,
            'almacenes' => $almacenes,
            'productosEnvase' => $productosEnvase,
            'variantesDisponibles' => $variantesDisponibles,
            'filters' => $request->only(['search', 'categoria_id', 'stock_bajo', 'almacen_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:50|unique:productos,codigo',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'nullable|exists:categorias,id',
            'precio_compra' => 'required|integer|min:0',
            'precio_venta' => 'required|integer|min:0',
            'stock_minimo' => 'required|integer|min:0',
            'fecha_vencimiento' => 'nullable|date',
            'stock' => 'required|integer|min:0',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'exists:almacenes,id',
            'unidad_medida' => 'nullable|in:unidad,kg,lt',
            'envase_retornable' => 'boolean',
            'envase_producto_id' => 'nullable|integer|exists:productos,id',
            'tipo_envase' => 'nullable|string',
            'activo' => 'boolean',
            'medida_pesable' => 'boolean',
            'tipo_medida' => 'nullable|in:unidad,kilo,litro',
            'cantidad_medida' => 'nullable|numeric|min:0',
            'contenido_por_unidad' => 'nullable|numeric|min:0',
            'peso_base' => 'nullable|numeric|min:0',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'imagen2' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'imagen3' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'imagen4' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'imagen5' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'video' => 'nullable|mimes:mp4,webm,ogv|max:10240',
            'mostrar_en_perfil' => 'boolean',
            'tiene_variantes' => 'boolean',
            // New variant system (System B)
            'variante_ids' => 'nullable|array',
            'variante_ids.*' => 'exists:variantes,id',
            'variantes' => 'nullable|array',
            'variantes.*.nombre' => 'required|string|max:255',
            'variantes.*.tipo' => 'required|in:texto,color,numero',
            'variantes.*.valores' => 'required|array|min:1',
            'variantes.*.valores.*' => 'required|string|max:255',
            'skus' => 'nullable|array',
            'skus.*.sku' => 'required|string|unique:sku_variantes,sku',
            'skus.*.precio_venta' => 'nullable|numeric|min:0',
            'skus.*.precio_compra' => 'nullable|numeric|min:0',
            'skus.*.stock' => 'nullable|numeric|min:0',
            'skus.*.stock_minimo' => 'nullable|numeric|min:0',
            'skus.*.almacen_id' => 'nullable|exists:almacenes,id',
            'skus.*.variante_valores' => 'required|array',
            'skus.*.variante_valores.*' => 'required|exists:variante_valores,id',
            // Legacy System A (deprecated - kept for existing products)
            'variantes_legacy' => 'nullable|array',
            'variantes_legacy.*.talla' => 'required|string',
            'variantes_legacy.*.codigo' => 'nullable|string',
            'variantes_legacy.*.stock' => 'nullable|numeric|min:0',
            'variantes_legacy.*.almacen_id' => 'nullable|exists:almacenes,id',
        ]);

        // Validación para productos con envase retornable (cilindros de gas)
        // Nota: Se permite que sean medibles (medida_pesable = true) si el usuario lo configura así
        // para poder registrar peso (ej. 45kg para recargas de gas).
        if ($validated['envase_retornable'] ?? false) {
            if (empty($validated['envase_producto_id'])) {
                return back()->withErrors(['envase_producto_id' => 'Debe seleccionar un envase/retornable asociado para productos retornables.'])->withInput();
            }

            // Si NO es medible, forzar valores por defecto para recargas de gas estándar
            if (! ($validated['medida_pesable'] ?? false)) {
                $validated['unidad_medida'] = 'unidad';
                $validated['tipo_medida'] = 'unidad';
            }
        }

        if (empty($request->envase_producto_id) || $request->envase_producto_id === 'none') {
            $request->merge(['envase_producto_id' => null]);
        }

        $data = array_merge($validated, [
            'owner_id' => Auth::user()->getOwnerId(),
            'user_id' => Auth::id(),
        ]);

        $publicProfile = PublicProfile::where('user_id', Auth::user()->getOwnerId())->first();
        if ($publicProfile) {
            $data['public_profile_id'] = $publicProfile->id;
        }

        if (! isset($data['unidad_medida']) || empty($data['unidad_medida'])) {
            $data['unidad_medida'] = 'unidad';
        }

        if (! isset($data['medida_pesable'])) {
            $data['medida_pesable'] = false;
        }

        if (! isset($data['tipo_medida']) || empty($data['tipo_medida'])) {
            $data['tipo_medida'] = 'unidad';
        }

        if (! isset($data['cantidad_medida'])) {
            $data['cantidad_medida'] = 0;
        }

        $stock = $validated['stock'];
        $warehouseIds = $validated['warehouse_ids'] ?? [];

        unset($data['stock'], $data['warehouse_ids'], $data['variante_ids'], $data['variantes'], $data['skus'], $data['variantes_legacy']);

        $imagenes = [];
        for ($i = 1; $i <= 5; $i++) {
            $key = 'imagen'.($i === 1 ? '' : $i);
            if ($request->hasFile($key)) {
                $imagenes[$key] = $request->file($key)->store('productos', 'public');
            }
        }

        if ($request->hasFile('video')) {
            $imagenes['video'] = $request->file('video')->store('productos/videos', 'public');
        }

        $producto = Producto::create(array_merge($data, $imagenes));

        // New variant system (System B)
        if ($request->boolean('tiene_variantes') && ($request->filled('skus') || $request->filled('variante_ids') || $request->filled('variantes'))) {
            $ownerId = Auth::user()->getOwnerId();
            $varianteIds = $request->variante_ids ?? [];

            // Create inline variant types
            foreach ($request->variantes ?? [] as $vData) {
                $variante = Variante::create([
                    'owner_id' => $ownerId,
                    'nombre' => $vData['nombre'],
                    'tipo' => $vData['tipo'],
                ]);
                foreach ($vData['valores'] as $valor) {
                    VarianteValor::create([
                        'variante_id' => $variante->id,
                        'valor' => $valor,
                    ]);
                }
                $varianteIds[] = $variante->id;
            }

            // Link variant types to product
            foreach ($varianteIds as $vid) {
                ProductoVariante::create([
                    'owner_id' => $ownerId,
                    'producto_id' => $producto->id,
                    'variante_id' => $vid,
                ]);
            }

            // Create SKUs
            foreach ($request->skus ?? [] as $skuData) {
                $sku = SkuVariante::create([
                    'producto_id' => $producto->id,
                    'sku' => $skuData['sku'],
                    'precio_venta' => $skuData['precio_venta'] ?? null,
                    'precio_compra' => $skuData['precio_compra'] ?? null,
                    'stock' => $skuData['stock'] ?? 0,
                    'stock_minimo' => $skuData['stock_minimo'] ?? 0,
                ]);

                $almacenSkuId = $skuData['almacen_id'] ?? ($warehouseIds[0] ?? null);
                if ($almacenSkuId) {
                    $producto->inventarios()->create([
                        'cantidad' => $skuData['stock'] ?? 0,
                        'cantidad_minima' => $skuData['stock_minimo'] ?? $validated['stock_minimo'],
                        'almacen_id' => $almacenSkuId,
                    ]);
                }

                foreach ($skuData['variante_valores'] as $vvId) {
                    SkuVarianteValor::create([
                        'sku_variante_id' => $sku->id,
                        'variante_valor_id' => $vvId,
                    ]);
                }
            }
        } elseif ($request->boolean('tiene_variantes') && $request->filled('variantes_legacy')) {
            // Legacy System A (deprecated)
            foreach ($request->variantes_legacy as $varianteData) {
                $child = Producto::create(array_merge($data, [
                    'codigo' => $varianteData['codigo'] ?? $producto->codigo.'-'.strtoupper($varianteData['talla']),
                    'nombre' => $producto->nombre.' - '.strtoupper($varianteData['talla']),
                    'talla' => $varianteData['talla'],
                    'tiene_variantes' => false,
                    'parent_id' => $producto->id,
                ]));

                $child->inventarios()->create([
                    'cantidad' => $varianteData['stock'] ?? 0,
                    'cantidad_minima' => $validated['stock_minimo'],
                    'almacen_id' => $varianteData['almacen_id'] ?? ($warehouseIds[0] ?? null),
                ]);
            }
        } else {
            DB::transaction(function () use ($producto, $warehouseIds, $stock, $validated) {
                $ownerId = Auth::user()->getOwnerId();
                $syncData = [];
                foreach ($warehouseIds as $wId) {
                    $syncData[$wId] = [
                        'cantidad' => $stock,
                        'cantidad_minima' => $validated['stock_minimo'],
                        'owner_id' => $ownerId,
                    ];
                }
                $producto->warehouses()->sync($syncData);
            });
        }

        if ($request->boolean('envase_retornable') && is_null($request->envase_producto_id)) {
            $producto->update(['envase_producto_id' => $producto->id]);
        }

        return redirect()->route('productos.index');
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => 'nullable|string|max:50|unique:productos,codigo,'.$producto->id,
            'nombre' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|present',
            'categoria_id' => 'nullable|exists:categorias,id',
            'precio_compra' => 'nullable|numeric|min:0',
            'precio_venta' => 'nullable|numeric|min:0',
            'stock_minimo' => 'nullable|numeric|min:0',
            'fecha_vencimiento' => 'nullable|date',
            'stock' => 'nullable|numeric|min:0',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'exists:almacenes,id',
            'unidad_medida' => 'nullable|in:unidad,kg,lt',
            'envase_retornable' => 'nullable|boolean',
            'envase_producto_id' => 'nullable|integer|exists:productos,id',
            'tipo_envase' => 'nullable|string',
            'activo' => 'nullable|boolean',
            'medida_pesable' => 'nullable|boolean',
            'tipo_medida' => 'nullable|in:unidad,kilo,litro',
            'cantidad_medida' => 'nullable|numeric|min:0',
            'contenido_por_unidad' => 'nullable|numeric|min:0',
            'peso_base' => 'nullable|numeric|min:0',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'imagen2' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'imagen3' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'imagen4' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'imagen5' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'video' => 'nullable|mimes:mp4,webm,ogv|max:10240',
            'mostrar_en_perfil' => 'nullable|boolean',
            'tiene_variantes' => 'nullable|boolean',
            // New variant system (System B)
            'variante_ids' => 'nullable|array',
            'variante_ids.*' => 'exists:variantes,id',
            'variantes' => 'nullable|array',
            'variantes.*.nombre' => 'required|string|max:255',
            'variantes.*.tipo' => 'required|in:texto,color,numero',
            'variantes.*.valores' => 'required|array|min:1',
            'variantes.*.valores.*' => 'required|string|max:255',
            'skus' => 'nullable|array',
            'skus.*.sku' => 'required|string|unique:sku_variantes,sku,'.$producto->id.',producto_id',
            'skus.*.precio_venta' => 'nullable|numeric|min:0',
            'skus.*.precio_compra' => 'nullable|numeric|min:0',
            'skus.*.stock' => 'nullable|numeric|min:0',
            'skus.*.stock_minimo' => 'nullable|numeric|min:0',
            'skus.*.almacen_id' => 'nullable|exists:almacenes,id',
            'skus.*.variante_valores' => 'required|array',
            'skus.*.variante_valores.*' => 'required|exists:variante_valores,id',
            // Legacy System A (deprecated - kept for existing products)
            'variantes_legacy' => 'nullable|array',
            'variantes_legacy.*.talla' => 'required|string',
            'variantes_legacy.*.codigo' => 'nullable|string',
            'variantes_legacy.*.stock' => 'nullable|numeric|min:0',
            'variantes_legacy.*.almacen_id' => 'nullable|exists:almacenes,id',
        ]);

        if (empty($request->envase_producto_id) || $request->envase_producto_id === 'none') {
            $request->merge(['envase_producto_id' => null]);
        }

        // Validar configuración de recargas de gas (cilindros retornables)
        // Se permite que sean medibles (medida_pesable = true) si el usuario lo configura así
        // para poder registrar peso (ej. 45kg para recargas de gas).
        if (($validated['envase_retornable'] ?? false) && ($validated['envase_producto_id'] ?? null)) {
            // Si NO es medible, forzar valores por defecto para recargas de gas estándar
            if (! ($validated['medida_pesable'] ?? false)) {
                $validated['unidad_medida'] = 'unidad';
                $validated['tipo_medida'] = 'unidad';
                $validated['medida_pesable'] = false;
            }
        }

        $publicProfile = PublicProfile::where('user_id', Auth::user()->getOwnerId())->first();

        $updateData = array_intersect_key($validated, array_flip([
            'codigo', 'nombre', 'descripcion', 'categoria_id', 'precio_compra',
            'precio_venta', 'stock_minimo', 'fecha_vencimiento', 'activo', 'unidad_medida',
            'envase_retornable', 'envase_producto_id', 'tipo_envase', 'medida_pesable',
            'tipo_medida', 'cantidad_medida', 'mostrar_en_perfil',
            'contenido_por_unidad', 'peso_base',
        ]));

        $updateData['public_profile_id'] = $publicProfile?->id;

        for ($i = 1; $i <= 5; $i++) {
            $key = 'imagen'.($i === 1 ? '' : $i);
            if ($request->hasFile($key)) {
                if ($producto->{$key}) {
                    Storage::disk('public')->delete($producto->{$key});
                }
                $updateData[$key] = $request->file($key)->store('productos', 'public');
            }
        }

        if ($request->hasFile('video')) {
            if ($producto->video) {
                Storage::disk('public')->delete($producto->video);
            }
            $updateData['video'] = $request->file('video')->store('productos/videos', 'public');
        }

        $updateDataFiltered = array_filter($updateData, fn ($key) => ! in_array($key, ['_stock_update', '_stock_minimo_update', '_almacen_id_update']), ARRAY_FILTER_USE_KEY);

        if (! empty($updateDataFiltered)) {
            $producto->update($updateDataFiltered);
        }

        // Handle variant changes
        if ($request->boolean('tiene_variantes') && ($request->filled('variante_ids') || $request->filled('variantes') || $request->filled('skus'))) {
            $ownerId = Auth::user()->getOwnerId();

            // Clear existing SKUs and variant links
            $producto->skus()->each(function ($sku) {
                $sku->valores()->delete();
                $sku->delete();
            });
            $producto->productoVariantes()->delete();

            // Create inline variant types
            $varianteIds = $request->variante_ids ?? [];
            foreach ($request->variantes ?? [] as $vData) {
                $variante = Variante::create([
                    'owner_id' => $ownerId,
                    'nombre' => $vData['nombre'],
                    'tipo' => $vData['tipo'],
                ]);
                foreach ($vData['valores'] as $valor) {
                    VarianteValor::create([
                        'variante_id' => $variante->id,
                        'valor' => $valor,
                    ]);
                }
                $varianteIds[] = $variante->id;
            }

            // Link variant types to product
            foreach ($varianteIds as $vid) {
                ProductoVariante::create([
                    'owner_id' => $ownerId,
                    'producto_id' => $producto->id,
                    'variante_id' => $vid,
                ]);
            }

            // Create SKUs
            foreach ($request->skus ?? [] as $skuData) {
                $sku = SkuVariante::create([
                    'producto_id' => $producto->id,
                    'sku' => $skuData['sku'],
                    'precio_venta' => $skuData['precio_venta'] ?? null,
                    'precio_compra' => $skuData['precio_compra'] ?? null,
                    'stock' => $skuData['stock'] ?? 0,
                    'stock_minimo' => $skuData['stock_minimo'] ?? 0,
                ]);

                foreach ($skuData['variante_valores'] as $vvId) {
                    SkuVarianteValor::create([
                        'sku_variante_id' => $sku->id,
                        'variante_valor_id' => $vvId,
                    ]);
                }
            }
        } elseif ($request->boolean('tiene_variantes') && $request->filled('variantes_legacy')) {
            // Legacy System A (deprecated)
            $producto->update(['tiene_variantes' => true]);

            $existingVariantes = $producto->variantes()->get()->keyBy('talla');
            $newTallas = collect($request->variantes_legacy)->pluck('talla')->toArray();

            foreach ($existingVariantes as $talla => $variante) {
                if (! in_array($talla, $newTallas)) {
                    $variante->delete();
                }
            }

            foreach ($request->variantes_legacy as $varianteData) {
                $childData = array_merge($updateDataFiltered, [
                    'codigo' => $varianteData['codigo'] ?: $producto->codigo.'-'.strtoupper($varianteData['talla']),
                    'nombre' => $producto->nombre.' - '.strtoupper($varianteData['talla']),
                    'talla' => $varianteData['talla'],
                    'tiene_variantes' => false,
                    'parent_id' => $producto->id,
                ]);

                if ($existingVariantes->has($varianteData['talla'])) {
                    $existingVariantes->get($varianteData['talla'])->update($childData);
                } else {
                    Producto::create(array_merge($childData, [
                        'owner_id' => Auth::user()->getOwnerId(),
                        'user_id' => Auth::id(),
                    ]));
                }
            }
        } else {
            // No variants — clean up if was previously with variants
            if ($producto->tiene_variantes) {
                $producto->skus()->each(function ($sku) {
                    $sku->valores()->delete();
                    $sku->delete();
                });
                $producto->productoVariantes()->delete();
                $producto->variantes()->delete();
            }
            $producto->update(['tiene_variantes' => false]);

            if (array_key_exists('warehouse_ids', $validated)) {
                $warehouseIds = $validated['warehouse_ids'] ?? [];
                DB::transaction(function () use ($producto, $warehouseIds, $validated) {
                    $currentIds = $producto->warehouses()->pluck('almacen_id')->toArray();
                    $ownerId = Auth::user()->getOwnerId();
                    $syncData = [];

                    foreach ($warehouseIds as $id) {
                        if (in_array($id, $currentIds)) {
                            $syncData[$id] = [];
                        } else {
                            $syncData[$id] = [
                                'cantidad' => 0,
                                'cantidad_minima' => $validated['stock_minimo'] ?? $producto->stock_minimo,
                                'owner_id' => $ownerId,
                            ];
                        }
                    }

                    $producto->warehouses()->sync($syncData);
                });
            }
        }

        if ($request->boolean('envase_retornable') && is_null($request->envase_producto_id)) {
            $producto->update(['envase_producto_id' => $producto->id]);
        }

        return redirect()->route('productos.index');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        if ($producto->owner_id !== Auth::user()->getOwnerId()) {
            abort(403, 'No tienes permiso para eliminar este producto.');
        }

        for ($i = 1; $i <= 5; $i++) {
            $key = 'imagen'.($i === 1 ? '' : $i);
            if ($producto->{ $key}) {
                Storage::disk('public')->delete($producto->{ $key});
            }
        }
        if ($producto->video) {
            Storage::disk('public')->delete($producto->video);
        }

        $producto->delete();

        return redirect()->route('productos.index');
    }
}

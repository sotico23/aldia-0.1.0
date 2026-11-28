<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Scopes\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * API pública del marketplace de vendedores para la APK Al Día Eats.
 *
 * Expone exactamente el conjunto de tiendas, categorías y productos que el
 * comprador ve en /tienda/{slug}. Todas las consultas omiten OwnerScope: un
 * comprador autenticado tiene un owner_id distinto al del vendedor y, sin
 * esta omisión, no vería ningún producto.
 */
class MarketplaceApiController extends Controller
{
    /** GET /api/v1/marketplace/stores */
    public function stores(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $featured = $search === '' && ! $request->filled('category_id')
            ? $this->featuredStoreIds()
            : collect();
        $stores = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('is_active', true)
            ->with('user')
            ->withReactionStats()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), function (Builder $query) use ($request): void {
                $query->whereHas('categorias', function (Builder $query) use ($request): void {
                    $query->withoutGlobalScope(OwnerScope::class)
                        ->where('categorias.id', (int) $request->integer('category_id'))
                        ->where('categorias.activo', true);
                });
            })
            ->whereNotIn('public_profiles.id', $featured)
            ->latest()
            ->limit(100 - $featured->count())
            ->get()
            ->map(fn (PublicProfile $store): array => $this->storeResource($store));

        return response()->json([
            'success' => true,
            'stores' => $this->featuredStores($featured)->concat($stores),
        ]);
    }

    public function masterOverview(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Master'), 403, 'Se requiere el rol Master.');

        $completed = $this->completedOrders();
        $totals = (clone $completed)->selectRaw('public_profile_id, COUNT(*) AS delivered_orders')
            ->groupBy('public_profile_id');
        $profiles = PublicProfile::withoutGlobalScope(OwnerScope::class);
        $stores = (clone $profiles)->leftJoinSub($totals, 'sales', 'sales.public_profile_id', '=', 'public_profiles.id')
            ->orderByDesc(DB::raw('COALESCE(sales.delivered_orders, 0)'))
            ->orderBy('public_profiles.id')
            ->paginate(25, [
                'public_profiles.id', 'public_profiles.title', 'public_profiles.slug',
                'public_profiles.is_active', 'public_profiles.email', 'public_profiles.phone',
                'public_profiles.created_at', DB::raw('COALESCE(sales.delivered_orders, 0) AS delivered_orders'),
            ]);

        $products = DB::table('pedido_items')
            ->joinSub($completed->select(['id', 'public_profile_id']), 'sale', 'sale.id', '=', 'pedido_items.pedido_id')
            ->join('public_profiles', 'public_profiles.id', '=', 'sale.public_profile_id')
            ->selectRaw('public_profiles.id AS store_id, public_profiles.title AS store, pedido_items.producto_id, CASE WHEN pedido_items.producto_id IS NULL THEN pedido_items.nombre_producto ELSE NULL END AS historical_name, MAX(pedido_items.nombre_producto) AS name, SUM(pedido_items.cantidad) AS units')
            ->groupBy('public_profiles.id', 'public_profiles.title', 'pedido_items.producto_id', 'historical_name')
            ->orderByDesc('units')
            ->orderBy('store_id')
            ->orderBy('pedido_items.producto_id')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json([
            'registered_stores' => $stores->total(),
            'active_stores' => (clone $profiles)->where('is_active', true)->count(),
            'delivered_orders' => $this->completedOrders()->count(),
            'featured_stores' => $this->featuredStores($this->featuredStoreIds()),
            'products' => $products,
            'stores' => $stores->items(),
            'page' => $stores->currentPage(),
            'last_page' => $stores->lastPage(),
        ]);
    }

    private function completedOrders(): Builder
    {
        return Pedido::withoutGlobalScope(OwnerScope::class)
            ->where('estado', 'entregado')
            ->whereNotNull('public_profile_id');
    }

    private function featuredStoreIds(): Collection
    {
        return $this->completedOrders()
            ->join('public_profiles', 'public_profiles.id', '=', 'pedidos.public_profile_id')
            ->where('public_profiles.is_active', true)
            ->selectRaw('pedidos.public_profile_id, COUNT(*) AS delivered_orders')
            ->groupBy('pedidos.public_profile_id')
            ->orderByDesc('delivered_orders')
            ->orderBy('pedidos.public_profile_id')
            ->limit(3)
            ->get()
            ->pluck('public_profile_id');
    }

    private function featuredStores(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $profiles = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('is_active', true)
            ->whereKey($ids->all())
            ->with('user')
            ->withReactionStats()
            ->get()
            ->keyBy('id');

        return $ids->map(fn ($id) => $profiles->get($id))
            ->filter()
            ->values()
            ->map(fn (PublicProfile $store, int $index): array => [
                ...$this->storeResource($store),
                'featured_rank' => $index + 1,
            ]);
    }

    /** GET /api/v1/marketplace/stores/{slug} */
    public function show(string $slug): JsonResponse
    {
        $store = $this->activeStore($slug);

        return response()->json([
            'success' => true,
            'store' => $this->storeResource($store, true),
            'categorias' => $this->categoriesFor($store),
            'almacenes' => $this->warehouses($store),
        ]);
    }

    /** GET /api/v1/marketplace/stores/{slug}/categories */
    public function categories(string $slug): JsonResponse
    {
        return response()->json([
            'success' => true,
            'categorias' => $this->categoriesFor($this->activeStore($slug)),
        ]);
    }

    /** GET /api/v1/marketplace/stores/{slug}/products */
    public function products(Request $request, string $slug): JsonResponse
    {
        $store = $this->activeStore($slug);
        $perPage = min(max($request->integer('per_page', 24), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $paginator = $this->productsQuery($store)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nombre', 'like', "%{$search}%")
                        ->orWhere('descripcion', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), function (Builder $query) use ($request): void {
                $query->where('categoria_id', (int) $request->integer('category_id'));
            })
            ->when($request->filled('almacen_id'), function (Builder $query) use ($request): void {
                $query->whereHas('inventarios', function (Builder $query) use ($request): void {
                    $query->withoutGlobalScope(OwnerScope::class)
                        ->where('almacen_id', (int) $request->integer('almacen_id'));
                });
            })
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'productos' => $paginator->getCollection()
                ->map(fn (Producto $product): array => $this->productResource($product))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** GET /api/v1/marketplace/orders — pedidos del cliente autenticado */
    public function orders(Request $request): JsonResponse
    {
        $orders = $this->ordersQuery($request)
            ->where('cliente_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Pedido $order): array => $this->orderResource($order));

        return response()->json(['success' => true, 'orders' => $orders]);
    }

    /** POST /api/v1/marketplace/orders */
    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_profile_id' => ['required', 'integer', 'exists:public_profiles,id'],
            'nombre_cliente' => ['required', 'string', 'max:255'],
            'telefono_cliente' => ['nullable', 'string', 'max:50'],
            'direccion_entrega' => ['nullable', 'string', 'max:1000'],
            'comuna' => ['nullable', 'string', 'max:100'],
            'metodo_pago' => ['nullable', 'string', 'max:100'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'numeric', 'min:1'],
        ]);

        $store = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->whereKey($validated['public_profile_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $order = DB::transaction(function () use ($validated, $store, $request): Pedido {
            $lines = [];
            $subtotal = 0.0;
            $costoEnvio = 0;

            foreach ($validated['items'] as $item) {
                $product = Producto::withoutGlobalScope(OwnerScope::class)
                    ->whereKey($item['producto_id'])
                    ->where('owner_id', $store->owner_id)
                    ->where(function (Builder $query) use ($store): void {
                        $query->where('public_profile_id', $store->id)
                            ->orWhereNull('public_profile_id');
                    })
                    ->firstOrFail();

                $quantity = (float) $item['cantidad'];
                $lineSubtotal = (float) $product->precio_venta * $quantity;
                $subtotal += $lineSubtotal;
                $costoEnvio = max($costoEnvio, $product->costoEnvioPara($validated['comuna'] ?? null));
                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $order = Pedido::create([
                'user_id' => $store->user_id,
                'owner_id' => $store->owner_id,
                'public_profile_id' => $store->id,
                'cliente_id' => $request->user()->id,
                'numero_pedido' => Pedido::generarNumeroPedido(),
                'estado' => 'pendiente',
                'dispatch_mode' => Pedido::DISPATCH_INTERNAL,
                'nombre_cliente' => $validated['nombre_cliente'],
                'telefono_cliente' => $validated['telefono_cliente'] ?? null,
                'direccion_cliente' => implode(', ', array_filter([$validated['direccion_entrega'] ?? null, $validated['comuna'] ?? null])) ?: null,
                'metodo_pago' => $validated['metodo_pago'] ?? null,
                'notas' => $validated['notas'] ?? null,
                'subtotal' => $subtotal,
                'impuesto' => 0,
                'costo_envio' => $costoEnvio,
                'total' => $subtotal + $costoEnvio,
            ]);

            foreach ($lines as $line) {
                PedidoItem::create([
                    'pedido_id' => $order->id,
                    'producto_id' => $line['product']->id,
                    'nombre_producto' => $line['product']->nombre,
                    'precio_unitario' => $line['product']->precio_venta,
                    'cantidad' => $line['quantity'],
                    'subtotal' => $line['subtotal'],
                    'owner_id' => $store->owner_id,
                ]);
            }

            return $order;
        });

        $order->load([
            'items' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class),
            'publicProfile' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class),
        ]);

        return response()->json([
            'success' => true,
            'order' => $this->orderResource($order),
        ], 201);
    }

    /** GET /api/v1/marketplace/orders/{order} */
    public function order(Request $request, int $order): JsonResponse
    {
        $pedido = $this->ordersQuery($request)
            ->whereKey($order)
            ->where('cliente_id', $request->user()->id)
            ->firstOrFail();

        return response()->json(['success' => true, 'order' => $this->orderResource($pedido)]);
    }

    /** GET /api/v1/marketplace/orders/{order}/status */
    public function orderStatus(Request $request, int $order): JsonResponse
    {
        return $this->order($request, $order);
    }

    /** POST /api/v1/marketplace/orders/{order}/cancel */
    public function cancelOrder(Request $request, int $order): JsonResponse
    {
        $pedido = $this->ordersQuery($request)
            ->whereKey($order)
            ->where('cliente_id', $request->user()->id)
            ->firstOrFail();

        if (! in_array($pedido->estado, ['pendiente', 'preparando'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido ya no se puede cancelar.',
            ], 409);
        }

        $pedido->update(['estado' => 'cancelado']);

        return response()->json([
            'success' => true,
            'order' => $this->orderResource($pedido),
        ]);
    }

    private function activeStore(string $slug): PublicProfile
    {
        return PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('user')
            ->firstOrFail();
    }

    /** Misma restricción que el storefront Livewire: owner_id + perfil o legacy. */
    private function productsQuery(PublicProfile $store): Builder
    {
        return Producto::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $store->owner_id)
            ->where(function (Builder $query) use ($store): void {
                $query->where('public_profile_id', $store->id)
                    ->orWhereNull('public_profile_id');
            })
            ->with([
                'categoria' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class),
                'inventarios' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class),
            ])
            ->orderBy('nombre');
    }

    private function categoriesFor(PublicProfile $store): array
    {
        return Categoria::withoutGlobalScope(OwnerScope::class)
            ->where('public_profile_id', $store->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn (Categoria $category): array => [
                'id' => $category->id,
                'nombre' => $category->nombre,
                'descripcion' => $category->descripcion ?? '',
                'imagen' => $category->imagen,
                'activo' => (bool) $category->activo,
                'mostrar_en_perfil' => (bool) $category->mostrar_en_perfil,
            ])
            ->values()
            ->all();
    }

    private function warehouses(PublicProfile $store): array
    {
        return Almacen::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $store->owner_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'direccion'])
            ->values()
            ->all();
    }

    private function ordersQuery(Request $request): Builder
    {
        return Pedido::withoutGlobalScope(OwnerScope::class)
            ->with([
                'items' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class),
                'publicProfile' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class),
            ]);
    }

    private function storeResource(PublicProfile $store, bool $withCategories = false): array
    {
        $user = $store->getRelation('user');
        $ratingCount = (int) ($store->rating_count ?? 0);
        $rating = $ratingCount > 0
            ? round((float) ($store->rating_total ?? 0) / $ratingCount, 1)
            : 0.0;

        $resource = [
            'id' => $store->id,
            'slug' => $store->slug,
            'title' => $store->title,
            'description' => $store->description ?? '',
            'phone' => $store->phone ?? '',
            'email' => $store->email ?? '',
            'user_id' => $store->user_id,
            'owner_id' => $store->owner_id,
            'is_active' => (bool) $store->is_active,
            'is_verified' => (bool) $store->is_verified,
            'rating' => $rating,
            'rating_count' => $ratingCount,
            'likes_count' => (int) ($store->likes_count ?? 0),
            'logo' => $user?->profile_photo_path,
            'cover' => $user?->cover_photo_path,
            'pickup_address' => $store->pickup_address ?? '',
            'pickup_lat' => $store->pickup_lat,
            'pickup_lng' => $store->pickup_lng,
            // La app muestra envío gratis mientras no exista tarifa por tienda.
            'delivery_fee' => 0,
            'minimum_order' => 0,
            'eta_minutes' => 30,
        ];

        if ($withCategories) {
            $resource['categorias'] = $this->categoriesFor($store);
        }

        return $resource;
    }

    private function productResource(Producto $product): array
    {
        $category = $product->getRelation('categoria');

        return [
            'id' => $product->id,
            'nombre' => $product->nombre,
            'descripcion' => $product->descripcion ?? '',
            'precio_venta' => (float) $product->precio_venta,
            'envio_gratis' => (bool) $product->envio_gratis,
            'envio_gratis_comuna' => $product->envio_gratis_comuna,
            'precio_envio' => (int) $product->precio_envio,
            'imagen' => $product->imagen,
            'categoria_id' => $product->categoria_id,
            'categoria_nombre' => $category?->nombre ?? 'General',
            'stock' => (float) $product->inventarios->sum('cantidad'),
            'unidad_medida' => $product->unidad_medida ?? 'unidad',
            'activo' => (bool) $product->activo,
            'mostrar_en_perfil' => (bool) $product->mostrar_en_perfil,
            'is_service' => (bool) $product->is_service,
            'duracion' => (int) ($product->duracion ?? 0),
        ];
    }

    private function orderResource(Pedido $order): array
    {
        $store = $order->getRelation('publicProfile');
        $items = $order->getRelation('items') ?? collect();

        return [
            'id' => $order->id,
            'numero_pedido' => $order->numero_pedido,
            'estado' => $order->estado,
            'store_slug' => $store?->slug ?? '',
            'store_name' => $store?->title ?? 'Tienda Al Día',
            'store_emoji' => '🏪',
            'items' => $items->map(fn (PedidoItem $item): array => [
                'producto_id' => $item->producto_id,
                'nombre_producto' => $item->nombre_producto,
                'cantidad' => (int) $item->cantidad,
                'precio_unitario' => (float) $item->precio_unitario,
                'subtotal' => (float) $item->subtotal,
                'emoji' => '🍽️',
            ])->values()->all(),
            'subtotal' => (float) $order->subtotal,
            'costo_envio' => (float) $order->costo_envio,
            'total' => (float) $order->total,
            'metodo_pago' => $order->metodo_pago ?? 'Efectivo',
            'nombre_cliente' => $order->nombre_cliente ?? '',
            'telefono_cliente' => $order->telefono_cliente ?? '',
            'direccion_cliente' => $order->direccion_cliente ?? '',
            'notas' => $order->notas ?? '',
            'eta_minutes' => 30,
            'created_at' => $order->created_at?->toISOString(),
        ];
    }
}

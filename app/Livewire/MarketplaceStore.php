<?php

namespace App\Livewire;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\PaymentConfig;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\StoreReaction;
use App\Models\StoreReview;
use App\Scopes\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MarketplaceStore extends Component
{
    public string $slug;

    public string $tabActivo = 'productos';

    public ?int $filtroAlmacen = null;

    public ?int $filtroCategoria = null;

    public int $perPage = 24;

    /** @var array<int, float> */
    public array $carrito = [];

    public string $nuevoComentario = '';

    protected $queryString = [
        'tabActivo' => ['except' => 'productos'],
        'filtroAlmacen' => ['except' => null],
        'filtroCategoria' => ['except' => null],
    ];

    private ?PublicProfile $storeCache = null;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->carrito = session()->get($this->carritoKey(), []);
    }

    public function setTab(string $tab): void
    {
        $this->tabActivo = $tab;
    }

    public function setFiltroAlmacen(?int $almacenId): void
    {
        $this->filtroAlmacen = $almacenId;
        $this->perPage = 24;
    }

    public function setFiltroCategoria(?int $categoriaId): void
    {
        $this->filtroCategoria = $categoriaId;
        $this->perPage = 24;
    }

    public function cargarMas(): void
    {
        if ($this->perPage >= $this->productosQuery()->count()) {
            return;
        }

        $this->perPage += 24;
    }

    public function agregar(int $productoId): void
    {
        $producto = $this->productoDeLaTienda($productoId);

        if (! $producto) {
            return;
        }

        $actual = (float) ($this->carrito[$productoId] ?? 0);
        $stock = $this->stock($producto);

        if (! $producto->is_service && $actual + 1 > $stock) {
            return;
        }

        $this->carrito[$productoId] = $actual + 1;
        $this->persistirCarrito();
    }

    public function incrementar(int $productoId): void
    {
        $this->agregar($productoId);
    }

    public function decrementar(int $productoId): void
    {
        $actual = (float) ($this->carrito[$productoId] ?? 0);

        if ($actual <= 1) {
            $this->quitar($productoId);

            return;
        }

        $this->carrito[$productoId] = $actual - 1;
        $this->persistirCarrito();
    }

    public function quitar(int $productoId): void
    {
        unset($this->carrito[$productoId]);

        $this->persistirCarrito();
    }

    public function vaciar(): void
    {
        $this->carrito = [];

        $this->persistirCarrito();
    }

    public function darMeGusta(): void
    {
        $store = $this->store();

        if (! $store || ! Auth::check()) {
            return;
        }

        $reaccion = StoreReaction::firstOrNew([
            'user_id' => Auth::id(),
            'public_profile_id' => $store->id,
        ]);

        $reaccion->like = $reaccion->like ? 0 : 1;
        $reaccion->save();

        $store->likes_count = StoreReaction::where('public_profile_id', $store->id)
            ->where('like', 1)
            ->count();
        $store->save();

        $this->storeCache = $store;
    }

    public function guardarOpinion(): void
    {
        $store = $this->store();

        if (! $store || ! Auth::check() || ! $this->haComprado($store)) {
            return;
        }

        $this->validate([
            'nuevoComentario' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        StoreReview::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'public_profile_id' => $store->id,
            ],
            ['comentario' => $this->nuevoComentario],
        );

        $this->nuevoComentario = '';
        $this->tabActivo = 'opiniones';
        $this->dispatch('opinion-guardada');
    }

    public function store(): ?PublicProfile
    {
        if ($this->storeCache instanceof PublicProfile) {
            return $this->storeCache;
        }

        return $this->storeCache = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->with(['user', 'categorias' => function ($query) {
                $query->withoutGlobalScope(OwnerScope::class)
                    ->where('activo', true)
                    ->orderBy('nombre');
            }])
            ->first();
    }

    /**
     * @return Collection<int, Almacen>
     */
    public function almacenes(): Collection
    {
        $store = $this->store();

        if (! $store) {
            return collect();
        }

        return Almacen::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $store->owner_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return Collection<int, Categoria>
     */
    public function categorias(): Collection
    {
        $store = $this->store();

        if (! $store) {
            return collect();
        }

        return Categoria::withoutGlobalScope(OwnerScope::class)
            ->where('public_profile_id', $store->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    public function productos(): Collection
    {
        return $this->productosQuery()
            ->limit($this->perPage)
            ->get();
    }

    public function totalProductos(): int
    {
        return $this->productosQuery()->count();
    }

    public function stock(Producto $producto): float
    {
        $inventarios = $producto->inventarios;

        if ($this->filtroAlmacen) {
            $inventarios = $inventarios->where('almacen_id', $this->filtroAlmacen);
        }

        return (float) $inventarios->sum('cantidad');
    }

    public function imagenProducto(Producto $producto): ?string
    {
        if (! $producto->imagen) {
            return null;
        }

        if (str_starts_with($producto->imagen, 'http')) {
            return $producto->imagen;
        }

        return asset('storage/'.ltrim($producto->imagen, '/'));
    }

    /**
     * @return array<int, array{producto: Producto, cantidad: float, subtotal: float}>
     */
    public function itemsCarrito(): array
    {
        if ($this->carrito === []) {
            return [];
        }

        $productos = $this->productosQuery()
            ->whereIn('id', array_keys($this->carrito))
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($this->carrito as $productoId => $cantidad) {
            $producto = $productos->get((int) $productoId);

            if (! $producto || $cantidad <= 0) {
                continue;
            }

            $items[] = [
                'producto' => $producto,
                'cantidad' => (float) $cantidad,
                'subtotal' => (float) $producto->precio_venta * (float) $cantidad,
            ];
        }

        return $items;
    }

    public function totalCarrito(): float
    {
        return (float) collect($this->itemsCarrito())->sum('subtotal');
    }

    public function unidadesCarrito(): float
    {
        return (float) array_sum($this->carrito);
    }

    public function promedioValoracion(): string
    {
        $store = $this->store();

        if (! $store || $store->rating_count <= 0) {
            return '5.0';
        }

        return number_format($store->rating_total / $store->rating_count, 1);
    }

    public function haComprado(PublicProfile $store): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return \App\Models\Pedido::where('user_id', Auth::id())
            ->where('public_profile_id', $store->id)
            ->exists();
    }

    public function metodosPago(): array
    {
        $store = $this->store();

        if (! $store) {
            return [];
        }

        $config = PaymentConfig::resolveForOwner($store->user_id);
        $metodos = ['efectivo', 'transferencia'];

        if ($config?->is_active) {
            $metodos[] = 'webpay';
        }

        if ($config?->paypal_active) {
            $metodos[] = 'paypal';
        }

        if ($config?->mercadopago_active) {
            $metodos[] = 'mercadopago';
        }

        return $metodos;
    }

    public function render(): View
    {
        $store = $this->store();

        if (! $store) {
            abort(404);
        }

        $reviews = StoreReview::where('public_profile_id', $store->id)
            ->with('user')
            ->latest()
            ->get();

        return view('livewire.marketplace-store', [
            'store' => $store,
            'almacenes' => $this->almacenes(),
            'categorias' => $this->categorias(),
            'productos' => $this->productos(),
            'totalProductos' => $this->totalProductos(),
            'reviews' => $reviews,
            'itemsCarrito' => $this->itemsCarrito(),
            'totalCarrito' => $this->totalCarrito(),
            'unidadesCarrito' => $this->unidadesCarrito(),
            'promedio' => $this->promedioValoracion(),
            'haComprado' => $this->haComprado($store),
            'yaOpino' => Auth::check() && $reviews->contains('user_id', Auth::id()),
            'metodosPago' => $this->metodosPago(),
            'usuarioAutenticado' => Auth::check(),
        ]);
    }

    protected function productosQuery(): Builder
    {
        $store = $this->store();

        if (! $store) {
            return Producto::withoutGlobalScope(OwnerScope::class)->whereRaw('1 = 0');
        }

        return Producto::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $store->owner_id)
            ->where(function ($query) use ($store) {
                $query->where('public_profile_id', $store->id)
                    ->orWhereNull('public_profile_id');
            })
            ->when($this->filtroAlmacen, function ($query) {
                $query->whereHas('inventarios', function ($inventarioQuery) {
                    $inventarioQuery->withoutGlobalScope(OwnerScope::class)
                        ->where('almacen_id', $this->filtroAlmacen);
                });
            })
            ->when($this->filtroCategoria, function ($query) {
                $query->where('categoria_id', $this->filtroCategoria);
            })
            ->with([
                'inventarios' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class),
            ])
            ->orderBy('nombre');
    }

    protected function productoDeLaTienda(int $productoId): ?Producto
    {
        return $this->productosQuery()->whereKey($productoId)->first();
    }

    protected function persistirCarrito(): void
    {
        session()->put($this->carritoKey(), $this->carrito);
    }

    protected function carritoKey(): string
    {
        $store = $this->store();

        return 'carrito_tienda_'.($store?->id ?? $this->slug);
    }
}

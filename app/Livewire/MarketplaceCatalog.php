<?php

namespace App\Livewire;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Scopes\OwnerScope;
use Livewire\Component;
use Livewire\WithPagination;

class MarketplaceCatalog extends Component
{
    use WithPagination;

    public string $slug;
    public string $tabActivo = 'productos';
    public ?int $filtroAlmacen = null;
    public ?int $filtroCategoria = null;
    public int $perPage = 24;
    public string $orderBy = 'nombre';
    public string $orderDir = 'asc';

    protected $queryString = [
        'slug' => ['except' => ''],
        'tabActivo' => ['except' => 'productos'],
        'filtroAlmacen' => ['except' => null],
        'filtroCategoria' => ['except' => null],
        'perPage' => ['except' => 24],
    ];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function updatedFiltroAlmacen(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroCategoria(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function cargarMas(): void
    {
        $this->perPage += 24;
    }

    public function setTab(string $tab): void
    {
        $this->tabActivo = $tab;
    }

    public function setFiltroAlmacen(?int $almacenId): void
    {
        $this->filtroAlmacen = $almacenId;
        $this->resetPage();
    }

    public function setFiltroCategoria(?int $categoriaId): void
    {
        $this->filtroCategoria = $categoriaId;
        $this->resetPage();
    }

    public function store(): ?PublicProfile
    {
        return PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->first();
    }

    public function almacenes()
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

    public function categorias()
    {
        $store = $this->store();

        if (! $store) {
            return collect();
        }

        return \App\Models\Categoria::withoutGlobalScope(OwnerScope::class)
            ->where('public_profile_id', $store->id)
            ->where('activo', true)
            ->where('mostrar_en_perfil', true)
            ->orderBy('nombre')
            ->get();
    }

    public function productos()
    {
        $store = $this->store();

        if (! $store) {
            return collect();
        }

        return Producto::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $store->owner_id)
            ->where(function ($query) use ($store) {
                $query->where('public_profile_id', $store->id)
                    ->orWhereNull('public_profile_id');
            })
            ->when($this->filtroAlmacen, function ($query) {
                $query->whereHas('inventarios', function ($q) {
                    $q->where('almacen_id', $this->filtroAlmacen);
                });
            })
            ->when($this->filtroCategoria, function ($query) {
                $query->where('categoria_id', $this->filtroCategoria);
            })
            ->with([
                'inventarios' => fn ($q) => $q->withoutGlobalScope(OwnerScope::class),
                'skus' => fn ($q) => $q->withoutGlobalScope(OwnerScope::class),
                'skus.valores' => fn ($q) => $q->withoutGlobalScope(OwnerScope::class),
                'skus.valores.varianteValor' => fn ($q) => $q->withoutGlobalScope(OwnerScope::class),
            ])
            ->orderBy($this->orderBy, $this->orderDir)
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.marketplace-catalog', [
            'productos' => $this->productos(),
            'almacenes' => $this->almacenes(),
            'categorias' => $this->categorias(),
            'store' => $this->store(),
        ]);
    }
}

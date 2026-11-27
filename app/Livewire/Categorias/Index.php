<?php

namespace App\Livewire\Categorias;

use App\Helpers\SearchHelper;
use App\Http\Controllers\Backend\CategoriaController;
use App\Models\Categoria;
use App\Models\PublicProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Categorías')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public string $busqueda = '';

    public string $tipo = '';

    public string $marketplace = '';

    public string $orden = 'nombre';

    public string $direccion = 'asc';

    public bool $formOpen = false;

    public string $mensaje = '';

    #[Locked]
    public ?int $editando = null;

    public array $form = [];

    public $imagen = null;

    public function boot(): void
    {
        Gate::authorize('comercial.categorias.viewAny');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['busqueda', 'tipo', 'marketplace'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'tipo', 'marketplace');
        $this->resetPage();
    }

    public function ordenar(string $campo): void
    {
        if (! in_array($campo, ['nombre', 'tipo'], true)) {
            return;
        }

        $this->direccion = $this->orden === $campo && $this->direccion === 'asc' ? 'desc' : 'asc';
        $this->orden = $campo;
        $this->resetPage();
    }

    public function nueva(): void
    {
        Gate::authorize('comercial.categorias.create');
        $this->abrirFormulario();
    }

    public function editar(int $id): void
    {
        Gate::authorize('comercial.categorias.edit');
        $this->abrirFormulario($this->categorias()->findOrFail($id));
    }

    public function guardar(CategoriaController $controller): void
    {
        $categoria = $this->editando === null
            ? null
            : $this->categorias()->findOrFail($this->editando);
        Gate::authorize($categoria === null
            ? 'comercial.categorias.create'
            : 'comercial.categorias.edit');

        $this->validate([
            'form.nombre' => 'required|string|max:255',
            'form.tipo' => 'required|in:'.implode(',', array_keys(Categoria::TIPOS)),
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $datos = $this->form;
        $datos['activo'] = ! empty($datos['activo']);
        $datos['mostrar_en_perfil'] = ! empty($datos['mostrar_en_perfil']);
        $archivos = $this->imagen === null ? [] : ['imagen' => $this->imagen];

        $request = Request::create('/categorias', 'POST', $datos, [], $archivos);
        if ($categoria === null) {
            $controller->crearCategoria($request);
        } else {
            $controller->actualizarCategoria($request, $categoria);
        }

        $this->cerrarFormulario();
        $this->mensaje = $categoria === null
            ? 'Categoría creada.'
            : 'Categoría actualizada.';
        $this->resetPage();
    }

    public function eliminar(int $id, CategoriaController $controller): void
    {
        Gate::authorize('comercial.categorias.delete');
        $controller->eliminarCategoria($this->categorias()->findOrFail($id));
        $this->mensaje = 'Categoría eliminada.';
        $this->resetPage();
    }

    public function render(): View
    {
        $ownerId = auth()->user()->getOwnerId();
        $publicProfile = PublicProfile::where('user_id', $ownerId)->first();

        if ($publicProfile) {
            Categoria::where('owner_id', $ownerId)
                ->whereNull('public_profile_id')
                ->update(['public_profile_id' => $publicProfile->id]);
        }

        $termino = mb_substr(trim($this->busqueda), 0, 120);
        $like = '%'.SearchHelper::escapeLike($termino).'%';
        $base = $this->categorias();
        $filtradas = (clone $base)
            ->when($termino !== '', function (Builder $query) use ($like): void {
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('nombre', 'like', $like)
                        ->orWhere('descripcion', 'like', $like)
                        ->orWhere('id', 'like', $like);
                });
            })
            ->when($this->tipo !== '', fn (Builder $query): Builder => $query->where('tipo', $this->tipo))
            ->when($this->marketplace === '1', fn (Builder $query): Builder => $query
                ->where('mostrar_en_perfil', true)
                ->whereNotNull('public_profile_id'))
            ->when($this->marketplace === '0', fn (Builder $query): Builder => $query
                ->where(function (Builder $query): void {
                    $query->where('mostrar_en_perfil', false)
                        ->orWhereNull('public_profile_id');
                }));

        $categorias = $filtradas
            ->orderBy($this->orden, $this->direccion)
            ->orderByDesc('id')
            ->paginate(12);

        $todas = (clone $base)->orderBy('nombre')->get();
        $ordenTipos = array_flip(array_keys(Categoria::TIPOS));
        $porTipo = $todas->groupBy('tipo')
            ->sortBy(fn ($items, $tipo): array => [$ordenTipos[$tipo] ?? 999, $tipo])
            ->map(fn ($items, $tipo): array => [
                'tipo' => $tipo,
                'label' => Str::headline($tipo),
                'total' => $items->count(),
                'activas' => $items->where('activo', true)->count(),
            ])
            ->values();

        $tiposDisponibles = collect(array_keys(Categoria::TIPOS))
            ->merge($todas->pluck('tipo'))
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $tipo): array => [
                'value' => $tipo,
                'label' => Str::headline($tipo),
            ]);

        return view('livewire.categorias.index', [
            'categorias' => $categorias,
            'porTipo' => $porTipo,
            'tiposDisponibles' => $tiposDisponibles,
            'resumen' => [
                'total' => $todas->count(),
                'activas' => $todas->where('activo', true)->count(),
                'marketplace' => $todas->filter(fn (Categoria $categoria): bool => (bool) $categoria->mostrar_en_perfil && $categoria->public_profile_id !== null)->count(),
                'tipos' => $porTipo->count(),
            ],
            'tiendaSlug' => $publicProfile?->slug,
        ])->layout('components.app-layout', [
            'breadcrumbs' => [['title' => 'Categorías', 'href' => route('categorias.index')]],
        ]);
    }

    private function categorias(): Builder
    {
        return Categoria::where('owner_id', auth()->user()->getOwnerId());
    }

    private function abrirFormulario(?Categoria $categoria = null): void
    {
        $this->resetValidation();
        $this->imagen = null;
        $this->editando = $categoria?->id;
        $this->form = [
            'nombre' => $categoria?->nombre ?? '',
            'descripcion' => $categoria?->descripcion ?? '',
            'tipo' => $categoria?->tipo ?? 'producto',
            'activo' => $categoria?->activo ?? true,
            'mostrar_en_perfil' => $categoria?->mostrar_en_perfil ?? false,
        ];
        $this->formOpen = true;
    }

    private function cerrarFormulario(): void
    {
        $this->reset('formOpen', 'form', 'editando', 'imagen');
        $this->resetValidation();
    }
}

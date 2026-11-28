<?php

namespace App\Livewire\Productos;

use App\Helpers\SearchHelper;
use App\Http\Controllers\Backend\ProductoController;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Variante;
use App\Services\OpenFoodFactsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Productos')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    private const IMAGE_KEYS = ['imagen', 'imagen2', 'imagen3', 'imagen4', 'imagen5'];

    public string $busqueda = '';

    public string $categoriaId = '';

    public string $almacenId = '';

    public bool $stockBajo = false;

    public string $orden = 'created_at';

    public string $direccion = 'desc';

    public string $vista = 'tabla';

    public bool $formOpen = false;

    public string $mensaje = '';

    #[Locked]
    public ?int $editando = null;

    #[Locked]
    public ?int $detalle = null;

    public array $form = [];

    public array $imagenes = [];

    public array $imagenesExistentes = [];

    public $video = null;

    public $archivo = null;

    public function boot(): void
    {
        Gate::authorize('comercial.productos.viewAny');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['busqueda', 'categoriaId', 'almacenId', 'stockBajo'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'categoriaId', 'almacenId', 'stockBajo');
        $this->resetPage();
    }

    public function ordenar(string $campo): void
    {
        $permitidos = ['nombre', 'categoria', 'descripcion', 'precio_venta', 'stock', 'estado'];
        if (! in_array($campo, $permitidos, true)) {
            return;
        }

        $this->direccion = $this->orden === $campo && $this->direccion === 'asc' ? 'desc' : 'asc';
        $this->orden = $campo;
        $this->resetPage();
    }

    public function nueva(): void
    {
        Gate::authorize('comercial.productos.create');
        $this->cerrar();
        $this->form = $this->formPorDefecto();
        $this->formOpen = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('comercial.productos.edit');
        $producto = $this->productos()->findOrFail($id);
        $this->cerrar();
        $this->editando = $producto->id;
        $this->form = $this->formDesdeProducto($producto);
        $this->imagenesExistentes = collect(self::IMAGE_KEYS)
            ->filter(fn (string $key): bool => ! empty($producto->{$key}))
            ->mapWithKeys(fn (string $key): array => [$key => $producto->{$key}])
            ->all();
        $this->formOpen = true;
    }

    public function ver(int $id): void
    {
        $this->detalle = $this->productos()->findOrFail($id)->id;
    }

    public function cerrarDetalle(): void
    {
        $this->reset('detalle');
    }

    public function cerrar(): void
    {
        $this->reset('formOpen', 'form', 'imagenes', 'imagenesExistentes', 'video', 'editando', 'detalle');
        $this->resetValidation();
    }

    public function agregarVariante(): void
    {
        $this->form['variantes'][] = ['nombre' => '', 'tipo' => 'texto', 'valores' => ['']];
    }

    public function quitarVariante(int $index): void
    {
        unset($this->form['variantes'][$index]);
        $this->form['variantes'] = array_values($this->form['variantes']);
    }

    public function agregarValor(int $index): void
    {
        $this->form['variantes'][$index]['valores'][] = '';
    }

    public function quitarValor(int $index, int $valorIndex): void
    {
        unset($this->form['variantes'][$index]['valores'][$valorIndex]);
        $this->form['variantes'][$index]['valores'] = array_values($this->form['variantes'][$index]['valores']);
    }

    /** Genera las combinaciones de SKU a partir de las variantes elegidas. */
    public function regenerarSkus(): void
    {
        $combinaciones = [[]];
        $etiquetas = [[]];
        $varianteIds = array_values(array_filter($this->form['variante_ids'] ?? []));

        foreach ($varianteIds as $varianteId) {
            $variante = Variante::where('owner_id', auth()->user()->getOwnerId())
                ->with('valores')
                ->find($varianteId);
            if ($variante === null || $variante->valores->isEmpty()) {
                continue;
            }
            $nuevas = [];
            $nuevasEtiquetas = [];
            foreach ($combinaciones as $indice => $combinacion) {
                foreach ($variante->valores as $valor) {
                    $nuevas[] = [...$combinacion, (string) $valor->id];
                    $nuevasEtiquetas[] = [...$etiquetas[$indice], $valor->valor];
                }
            }
            $combinaciones = $nuevas;
            $etiquetas = $nuevasEtiquetas;
        }

        foreach ($this->form['variantes'] ?? [] as $i => $variante) {
            $valores = array_values(array_filter($variante['valores'] ?? []));
            if ($valores === []) {
                continue;
            }
            $nuevas = [];
            $nuevasEtiquetas = [];
            foreach ($combinaciones as $indice => $combinacion) {
                foreach ($valores as $j => $valor) {
                    $nuevas[] = [...$combinacion, "inline:{$i}:{$j}"];
                    $nuevasEtiquetas[] = [...$etiquetas[$indice], $valor];
                }
            }
            $combinaciones = $nuevas;
            $etiquetas = $nuevasEtiquetas;
        }

        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) ($this->form['nombre'] ?? 'producto')) ?: 'producto');
        $this->form['skus'] = [];
        foreach ($combinaciones as $indice => $combinacion) {
            $this->form['skus'][] = [
                'sku' => $indice === 0 ? $base : $base.'-'.($indice + 1),
                'precio_venta' => $this->form['precio_venta'] ?? '',
                'precio_compra' => $this->form['precio_compra'] ?? '',
                'stock' => 0,
                'stock_minimo' => $this->form['stock_minimo'] ?? 0,
                'almacen_id' => '',
                'variante_valores' => $combinacion,
                'etiqueta' => implode(' / ', $etiquetas[$indice]),
            ];
        }
    }

    /** Autocompleta el formulario desde el SKU local o desde Open Food Facts. */
    public function buscarCodigo(): void
    {
        $codigo = trim((string) ($this->form['codigo'] ?? ''));
        if ($codigo === '') {
            return;
        }

        $producto = Producto::where('codigo', $codigo)
            ->with(['categoria', 'inventarios', 'skus.valores.varianteValor'])
            ->first();
        if ($producto !== null) {
            foreach (['nombre', 'descripcion', 'categoria_id', 'precio_compra', 'precio_venta', 'stock_minimo', 'unidad_medida'] as $campo) {
                $this->form[$campo] = $producto->{$campo};
            }
            $this->form['warehouse_ids'] = $producto->inventarios->pluck('almacen_id')->filter()->values()->all();
            $this->form['tiene_variantes'] = (bool) $producto->tiene_variantes;
            $this->mensaje = 'Datos del SKU '.$codigo.' cargados.';
            $this->resetValidation();

            return;
        }

        if (preg_match('/^(?:\d{8}|\d{12,14})$/', $codigo) === 1) {
            $resultado = app(OpenFoodFactsService::class)->buscarProductoPorEAN($codigo);
            if (! $resultado['error'] && $resultado['encontrado']) {
                $this->form['nombre'] = $resultado['datos']['nombre'] ?? '';
                $this->form['descripcion'] = $resultado['datos']['descripcion'] ?? '';
                $this->mensaje = 'Producto encontrado en Open Food Facts. Revisa los datos.';

                return;
            }
        }

        $this->addError('form.codigo', 'SKU no registrado. Puedes completar el producto manualmente.');
    }

    public function guardar(ProductoController $controller): void
    {
        Gate::authorize($this->editando === null
            ? 'comercial.productos.create'
            : 'comercial.productos.edit');

        $datos = $this->form;
        foreach (['activo', 'envase_retornable', 'medida_pesable', 'mostrar_en_perfil', 'tiene_variantes'] as $booleano) {
            $datos[$booleano] = ! empty($datos[$booleano]) ? '1' : '0';
        }

        $envase = (string) ($datos['envase_producto_id'] ?? '');
        // "self" se resuelve en el controlador asignando el propio producto.
        if ($envase === 'self') {
            $datos['envase_producto_id'] = '';
        }

        // La petición sintética no pasa por ConvertEmptyStringsToNull.
        foreach (['codigo', 'categoria_id', 'fecha_vencimiento', 'tipo_envase', 'envase_producto_id'] as $nullable) {
            if (($datos[$nullable] ?? '') === '') {
                $datos[$nullable] = null;
            }
        }

        $archivos = [];
        foreach (array_values($this->imagenes) as $indice => $archivo) {
            if ($archivo !== null && $indice < count(self::IMAGE_KEYS)) {
                $archivos[self::IMAGE_KEYS[$indice]] = $archivo;
            }
        }
        if ($this->video !== null) {
            $archivos['video'] = $this->video;
        }
        if ($this->editando !== null) {
            $datos['manage_images'] = '1';
            $datos['imagenes_existentes'] = $this->imagenesExistentes;
        }

        $request = Request::create('/productos', 'POST', $datos, [], $archivos);
        try {
            if ($this->editando === null) {
                $controller->crearProducto($request);
                $this->mensaje = 'Producto creado correctamente.';
            } else {
                $controller->actualizarProducto($request, $this->productos()->findOrFail($this->editando));
                $this->mensaje = 'Producto actualizado correctamente.';
            }
        } catch (ValidationException $error) {
            foreach ($error->errors() as $campo => $mensajes) {
                $this->addError('form.'.$campo, $mensajes);
            }

            return;
        }

        $this->cerrar();
        $this->resetPage();
    }

    public function eliminar(int $id, ProductoController $controller): void
    {
        Gate::authorize('comercial.productos.delete');
        $controller->eliminarProducto($this->productos()->findOrFail($id));
        $this->mensaje = 'Producto eliminado correctamente.';
        $this->resetPage();
    }

    public function importar(): void
    {
        Gate::authorize('comercial.productos.import');
        $this->validate(['archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:12288']);

        $request = Request::create('/productos/import', 'POST', [], [], ['archivo' => $this->archivo]);
        app(ProductoController::class)->importCsv($request);
        $this->reset('archivo');
        $this->resetPage();
        $this->mensaje = 'Importación completada.';
    }

    public function render(): View
    {
        $termino = mb_substr(trim($this->busqueda), 0, 120);
        $like = '%'.SearchHelper::escapeLike($termino).'%';
        $base = $this->productos();
        $filtrados = (clone $base)
            ->when($termino !== '', function (Builder $query) use ($like): void {
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('nombre', 'like', $like)
                        ->orWhere('codigo', 'like', $like)
                        ->orWhere('descripcion', 'like', $like);
                });
            })
            ->when($this->categoriaId !== '', fn (Builder $query): Builder => $query->where('categoria_id', (int) $this->categoriaId))
            ->when($this->almacenId !== '', fn (Builder $query): Builder => $query->whereHas('inventarios', fn (Builder $query): Builder => $query->where('almacen_id', (int) $this->almacenId)))
            ->when($this->stockBajo, fn (Builder $query): Builder => $query->whereHas('inventario', fn (Builder $query): Builder => $query->whereColumn('cantidad', '<=', 'cantidad_minima')));

        $orden = in_array($this->orden, ['nombre', 'categoria', 'descripcion', 'precio_venta', 'stock', 'estado'], true)
            ? $this->orden
            : 'created_at';
        $direccion = $this->direccion === 'desc' ? 'desc' : 'asc';

        if ($orden === 'categoria') {
            $filtrados->orderBy(
                Categoria::query()->select('nombre')->whereColumn('categorias.id', 'productos.categoria_id'),
                $direccion,
            );
        } elseif ($orden === 'stock') {
            $filtrados->orderBy(
                Inventario::query()->selectRaw('COALESCE(SUM(cantidad), 0)')->whereColumn('inventarios.producto_id', 'productos.id'),
                $direccion,
            );
        } elseif ($orden !== 'created_at') {
            $columna = $orden === 'estado' ? 'productos.activo' : 'productos.'.$orden;
            $filtrados->orderBy($columna, $direccion);
        } else {
            $filtrados->orderBy('productos.created_at', 'desc');
        }
        $filtrados->orderBy('productos.id');

        $ownerId = auth()->user()->getOwnerId();

        return view('livewire.productos.index', [
            'productos' => $filtrados->paginate(15),
            'categorias' => Categoria::where('tipo', 'producto')->where('activo', true)->where('owner_id', $ownerId)->orderBy('nombre')->get(),
            'almacenes' => Almacen::where('activo', true)->where('owner_id', $ownerId)->orderBy('nombre')->get(),
            'productosEnvase' => Producto::where('owner_id', $ownerId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'variantesDisponibles' => Variante::with('valores')->where('owner_id', $ownerId)->where('activo', true)->get(),
            'productoDetalle' => $this->detalle === null ? null : $this->productos()
                ->with(['categoria', 'inventarios.almacen', 'variantes.inventarios.almacen', 'envaseProducto', 'skus.valores.varianteValor'])
                ->find($this->detalle),
        ])->layout('components.app-layout', [
            'breadcrumbs' => [['title' => 'Productos', 'href' => route('productos.index')]],
        ]);
    }

    private function productos(): Builder
    {
        return Producto::with(['categoria', 'inventario.almacen', 'inventarios.almacen', 'envaseProducto', 'skus.valores.varianteValor'])
            ->where('owner_id', auth()->user()->getOwnerId())
            ->whereNull('parent_id');
    }

    /** @return array<string, mixed> */
    private function formPorDefecto(): array
    {
        return [
            'codigo' => '', 'nombre' => '', 'descripcion' => '', 'categoria_id' => '',
            'precio_compra' => 0, 'precio_venta' => 0, 'comision_cajero_por_unidad' => 0,
            'stock_minimo' => 0, 'fecha_vencimiento' => '', 'stock' => 0,
            'warehouse_ids' => [], 'unidad_medida' => 'unidad', 'activo' => true,
            'envase_retornable' => false, 'envase_producto_id' => '', 'medida_pesable' => false,
            'tipo_medida' => 'unidad', 'cantidad_medida' => 0, 'tipo_envase' => '',
            'mostrar_en_perfil' => false, 'contenido_por_unidad' => 0, 'peso_base' => 0,
            'tiene_variantes' => false, 'variante_ids' => [], 'variantes' => [], 'skus' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function formDesdeProducto(Producto $producto): array
    {
        $form = $this->formPorDefecto();
        foreach (array_keys($form) as $campo) {
            if (in_array($campo, ['warehouse_ids', 'variante_ids', 'variantes', 'skus'], true)) {
                continue;
            }
            $form[$campo] = $producto->{$campo};
        }
        $form['warehouse_ids'] = $producto->inventarios->pluck('almacen_id')->filter()->values()->all();
        $form['variante_ids'] = $producto->productoVariantes->pluck('variante_id')->filter()->values()->all();
        $form['activo'] = (bool) $producto->activo;
        $form['mostrar_en_perfil'] = (bool) $producto->mostrar_en_perfil;
        $form['tiene_variantes'] = (bool) $producto->tiene_variantes;
        $form['envase_retornable'] = (bool) $producto->envase_retornable;
        $form['medida_pesable'] = (bool) $producto->medida_pesable;

        return $form;
    }
}

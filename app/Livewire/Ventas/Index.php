<?php

namespace App\Livewire\Ventas;

use App\Enums\Currency;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Backend\EntregaController;
use App\Http\Controllers\Backend\VentaController;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Ventas')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public string $busqueda = '';

    public string $estado = '';

    public string $cliente = '';

    public string $desde = '';

    public string $hasta = '';

    public string $vista = 'tabla';

    public string $periodo = 'mes';

    public string $orden = 'fecha';

    public string $direccion = 'desc';

    public bool $formOpen = false;

    #[Locked]
    public ?int $editando = null;

    #[Locked]
    public ?int $detalle = null;

    #[Locked]
    public ?int $ventaEntrega = null;

    public array $form = [];

    public array $envio = [];

    public $archivo = null;

    public string $mensaje = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', Venta::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['busqueda', 'estado', 'cliente', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }

        if (preg_match('/^form\.productos\.(\d+)\.producto_id$/', $property, $matches)) {
            $index = (int) $matches[1];
            $producto = Producto::where('owner_id', auth()->user()->getOwnerId())
                ->find($this->form['productos'][$index]['producto_id'] ?? null);
            $this->form['productos'][$index]['precio_unitario'] = $producto?->precio_venta ?? 0;
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'estado', 'cliente', 'desde', 'hasta');
        $this->resetPage();
    }

    public function ordenar(string $campo): void
    {
        if (! in_array($campo, ['numero', 'fecha', 'estado', 'total'], true)) {
            return;
        }

        $this->direccion = $this->orden === $campo && $this->direccion === 'asc' ? 'desc' : 'asc';
        $this->orden = $campo;
        $this->resetPage();
    }

    public function ver(int $id): void
    {
        $this->detalle = $this->ventas()->findOrFail($id)->id;
    }

    public function cerrarDetalle(): void
    {
        $this->reset('detalle');
    }

    public function prepararEntrega(int $id): void
    {
        $this->autorizarEntrega();
        $venta = $this->ventasParaEntrega()->with('cliente')->findOrFail($id);
        $this->resetValidation();
        $this->ventaEntrega = $venta->id;
        $this->envio = [
            'cliente' => $venta->delivery_recipient_name ?: $venta->cliente?->nombre,
            'direccion' => $venta->delivery_address ?: collect([
                $venta->cliente?->direccion,
                $venta->cliente?->comuna,
                $venta->cliente?->ciudad,
                $venta->cliente?->region,
            ])->filter()->join(', '),
            'fecha_entrega' => now()->toDateString(),
            'estado_asignacion' => 'asignado',
            'repartidor_id' => '',
            'notas' => '',
        ];
    }

    public function cerrarEntrega(): void
    {
        $this->reset('ventaEntrega', 'envio');
        $this->resetValidation();
    }

    public function enviarEntrega(EntregaController $controller): void
    {
        $this->autorizarEntrega();
        $venta = $this->ventasParaEntrega()->findOrFail($this->ventaEntrega);
        $datos = $this->validate([
            'envio.cliente' => 'nullable|string|max:255',
            'envio.direccion' => 'required|string|max:1000',
            'envio.fecha_entrega' => 'required|date',
            'envio.estado_asignacion' => 'required|in:asignado,libre',
            'envio.repartidor_id' => 'required_if:envio.estado_asignacion,asignado|nullable|integer',
            'envio.notas' => 'nullable|string|max:1000',
        ])['envio'];

        if ($datos['estado_asignacion'] === 'asignado' && ! collect($controller->repartidoresDisponibles($venta->owner_id))->contains('id', (int) $datos['repartidor_id'])) {
            $this->addError('envio.repartidor_id', 'El repartidor seleccionado ya no está disponible.');

            return;
        }

        $controller->crearEntrega(Request::create('/entregas', 'POST', [
            ...$datos,
            'repartidor_id' => $datos['estado_asignacion'] === 'asignado' ? $datos['repartidor_id'] : null,
            'venta_id' => $venta->id,
            'estado' => 'pendiente',
        ]));
        $this->cerrarEntrega();
        $this->mensaje = 'Venta enviada a reparto.';
    }

    public function nueva(): void
    {
        Gate::authorize('create', Venta::class);
        $this->abrirFormulario();
        $this->agregarProducto();
    }

    public function editar(int $id): void
    {
        $venta = $this->ventas()->with(['detalleVentas', 'almacenes'])->findOrFail($id);
        Gate::authorize('update', $venta);
        $this->abrirFormulario($venta);
    }

    public function cerrarFormulario(): void
    {
        $this->reset('formOpen', 'form', 'editando');
        $this->resetValidation();
    }

    public function agregarProducto(): void
    {
        $this->form['productos'][] = [
            'producto_id' => '',
            'cantidad' => 1,
            'precio_unitario' => 0,
            'cantidad_retornada' => null,
        ];
    }

    public function quitarProducto(int $index): void
    {
        unset($this->form['productos'][$index]);
    }

    public function guardar(VentaController $controller): void
    {
        $venta = $this->editando === null ? null : $this->ventas()->findOrFail($this->editando);
        $venta ? Gate::authorize('update', $venta) : Gate::authorize('create', Venta::class);

        $datos = $this->form;
        if ($venta && ($datos['estado'] ?? null) === $venta->estado) {
            unset($datos['estado']);
        }
        $datos['cliente_id'] = ($datos['cliente_tipo'] ?? null) === 'generico'
            ? null
            : (($datos['cliente_id'] ?? null) ?: null);
        $datos['productos'] = array_map(fn (array $item): array => [
            ...$item,
            'cantidad_retornada' => ($item['cantidad_retornada'] ?? '') === '' ? null : $item['cantidad_retornada'],
        ], array_values($datos['productos'] ?? []));
        $request = Request::create('/ventas', 'POST', $datos);

        if ($venta) {
            $controller->actualizarVenta($request, $venta);
        } else {
            $controller->crearVenta($request);
        }

        $this->cerrarFormulario();
        $this->mensaje = $venta ? 'Venta actualizada.' : 'Venta creada.';
        $this->resetPage();
    }

    public function cambiarEstado(int $id, string $estado, VentaController $controller): void
    {
        $venta = $this->ventas()->findOrFail($id);
        $controller->cambiarEstado(Request::create('/ventas', 'PATCH', ['estado' => $estado]), $venta);
        $this->mensaje = 'Estado actualizado.';
        $this->resetPage();
    }

    public function eliminar(int $id, VentaController $controller): void
    {
        $controller->eliminarVenta($this->ventas()->findOrFail($id));
        $this->reset('detalle');
        $this->mensaje = 'Venta eliminada.';
        $this->resetPage();
    }

    public function importar(VentaController $controller): void
    {
        Gate::authorize('ventas.ventas.import');
        $this->validate(['archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:12288']);
        $this->mensaje = $controller->importarVentas(Request::create('/ventas/import', 'POST', [], [], ['archivo' => $this->archivo]));
        $this->reset('archivo');
        $this->resetPage();
    }

    public function render(): View
    {
        $base = $this->ventas();
        $filtros = (clone $base)
            ->when($this->estado !== '', fn (Builder $query) => $query->where('estado', $this->estado))
            ->when(ctype_digit($this->cliente), fn (Builder $query) => $query->where('cliente_id', $this->cliente))
            ->when($this->fechaValida($this->desde), fn (Builder $query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->fechaValida($this->hasta), fn (Builder $query) => $query->whereDate('fecha', '<=', $this->hasta));
        $termino = trim($this->busqueda);
        if ($termino !== '') {
            $like = '%'.SearchHelper::escapeLike(mb_substr($termino, 0, 120)).'%';
            $filtros->where(fn (Builder $query) => $query->where('numero', 'like', $like)
                ->orWhere('notas', 'like', $like)
                ->orWhereHas('cliente', fn (Builder $cliente) => $cliente->where('nombre', 'like', $like)->orWhere('rut', 'like', $like)));
        }

        $orden = in_array($this->orden, ['numero', 'fecha', 'estado', 'total'], true) ? $this->orden : 'fecha';
        $owner = auth()->user()->getOwnerId();
        $clientes = Cliente::where('owner_id', $owner);
        if (auth()->user()->isCliente()) {
            $clientes->whereKey(auth()->user()->getClienteActual()?->id ?? 0);
        }

        $ventas = $filtros->with(['cliente:id,nombre,telefono', 'almacen:id,nombre'])
            ->withExists('entrega')
            ->orderBy($orden, $this->direccion === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate(15);
        $grupos = [
            'pagadas' => $ventas->getCollection()
                ->filter(fn (Venta $venta): bool => $venta->estado === 'pagada')
                ->values(),
            'no-pagadas' => $ventas->getCollection()
                ->filter(fn (Venta $venta): bool => $venta->estado !== 'pagada')
                ->values(),
        ];

        return view('livewire.ventas.index', [
            'ventas' => $ventas,
            'grupos' => $grupos,
            'resumen' => (clone $base)->selectRaw("COUNT(*) AS total, COUNT(CASE WHEN estado = 'pagada' THEN 1 END) AS pagadas, COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) AS pendientes, COALESCE(SUM(CASE WHEN estado = 'pagada' THEN total ELSE 0 END), 0) AS ingresos")->first(),
            'serie' => $this->serie($base),
            'clientes' => $clientes->where(fn (Builder $query) => $query->where('activo', true)->orWhere('id', (int) ($this->form['cliente_id'] ?? 0)))->orderBy('nombre')->get(['id', 'nombre']),
            'almacenes' => $this->formOpen ? Almacen::where('owner_id', $owner)
                ->where(fn (Builder $query) => $query->where('activo', true)->orWhereIn('id', $this->form['almacen_ids'] ?? []))->orderBy('nombre')->get(['id', 'nombre']) : collect(),
            'productos' => $this->formOpen ? Producto::where('owner_id', $owner)
                ->where(fn (Builder $query) => $query->where('activo', true)->orWhereIn('id', array_column($this->form['productos'] ?? [], 'producto_id')))->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'envase_retornable']) : collect(),
            'ventaDetalle' => $this->detalle === null ? null : $base->with(['cliente', 'almacen', 'detalleVentas.producto', 'almacenes'])->find($this->detalle),
            'repartidores' => $this->ventaEntrega === null ? [] : app(EntregaController::class)->repartidoresDisponibles($owner),
            'moneda' => Currency::fromCountry(auth()->user()->country ?? 'CL')->value,
        ])->layout('components.app-layout', [
            'breadcrumbs' => [['title' => 'Ventas', 'href' => route('ventas.index')]],
        ]);
    }

    private function abrirFormulario(?Venta $venta = null): void
    {
        $this->resetValidation();
        $this->editando = $venta?->id;
        $this->form = [
            'numero_factura' => $venta?->numero ?? ('V-'.str()->ulid()),
            'cliente_id' => $venta?->cliente_id ?? '',
            'cliente_tipo' => 'existente',
            'cliente_nombre' => '',
            'cliente_rut' => '',
            'cliente_telefono' => '',
            'cliente_direccion' => '',
            'fecha' => $venta?->fecha?->toDateString() ?? now()->toDateString(),
            'estado' => $venta?->estado ?? 'pendiente',
            'tipo_documento' => $venta?->tipo_documento ?? 'boleta',
            'incluye_iva' => $venta ? (bool) $venta->incluye_iva : true,
            'tipo_descuento' => $venta?->tipo_descuento ?? 'monto',
            'valor_descuento' => $venta?->valor_descuento ?? 0,
            'cupon_codigo' => '',
            'notas' => $venta?->notas ?? '',
            'currency' => $venta?->currency ?? Currency::fromCountry(auth()->user()->country ?? 'CL')->value,
            'almacen_ids' => $venta
                ? $venta->almacenes->pluck('id')->whenEmpty(fn (Collection $ids) => $ids->merge(array_filter([$venta->almacen_id])))->map(fn ($id) => (string) $id)->all()
                : [],
            'productos' => $venta?->detalleVentas->map(fn ($item) => [
                'producto_id' => $item->producto_id,
                'cantidad' => $item->cantidad,
                'precio_unitario' => $item->precio_unitario,
                'cantidad_retornada' => $item->cantidad_retornada,
            ])->all() ?? [],
        ];
        $this->formOpen = true;
    }

    private function ventas(): Builder
    {
        $user = auth()->user();

        return Venta::where('owner_id', $user->getOwnerId())
            ->when($user->isCliente(), fn (Builder $query) => $query->where('cliente_id', $user->getClienteActual()?->id ?? 0));
    }

    private function ventasParaEntrega(): Builder
    {
        return $this->ventas()->whereIn('estado', Venta::ESTADOS_PARA_ENTREGA)->whereDoesntHave('entrega');
    }

    private function autorizarEntrega(): void
    {
        Gate::authorize('flota.entregas.viewAny');
        Gate::authorize('flota.entregas.create');
    }

    private function fechaValida(string $fecha): bool
    {
        $partes = explode('-', $fecha);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha) && checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0]);
    }

    private function serie(Builder $ventas): Collection
    {
        $hoy = CarbonImmutable::today();
        $rangos = collect(range(5, 0))->map(fn (int $paso) => match ($this->periodo) {
            'dia' => [$hoy->subDays($paso), $hoy->subDays($paso)->endOfDay(), $hoy->subDays($paso)->format('d/m')],
            'semana' => [$hoy->subWeeks($paso)->startOfWeek(), $hoy->subWeeks($paso)->endOfWeek(), $hoy->subWeeks($paso)->startOfWeek()->format('d/m')],
            default => [$hoy->startOfMonth()->subMonths($paso), $hoy->startOfMonth()->subMonths($paso)->endOfMonth(), $hoy->startOfMonth()->subMonths($paso)->format('m/Y')],
        });
        $montos = (clone $ventas)->whereBetween('fecha', [$rangos->first()[0], $rangos->last()[1]])
            ->selectRaw('DATE(fecha) AS dia, SUM(total) AS monto')->groupBy('dia')->pluck('monto', 'dia');

        return $rangos->map(fn (array $rango) => [
            'label' => $rango[2],
            'monto' => $montos->filter(fn ($monto, $dia) => $dia >= $rango[0]->toDateString() && $dia <= $rango[1]->toDateString())->sum(),
        ]);
    }
}

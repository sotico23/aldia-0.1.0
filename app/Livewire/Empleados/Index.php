<?php

namespace App\Livewire\Empleados;

use App\Actions\Delivery\CreateCompanyDriver;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Backend\EmpleadoController;
use App\Http\Requests\Backend\StoreEmpleadoRequest;
use App\Imports\EmpleadosImport;
use App\Models\Almacen;
use App\Models\Empleado;
use App\Models\Repartidor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

#[Title('Empleados')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    private const CAMPOS = [
        'nombre', 'apellido', 'rut', 'email', 'telefono', 'fecha_nacimiento',
        'nacionalidad', 'estado_civil', 'direccion', 'comuna', 'cargo', 'departamento',
        'almacen_id', 'fecha_contratacion', 'tipo_contrato', 'salario', 'sueldo_liquido_pactado',
        'afp', 'sistema_salud', 'isapre_nombre', 'banco_nombre', 'banco_tipo_cuenta',
        'banco_numero_cuenta', 'hora_entrada', 'hora_salida', 'estado', 'notas',
    ];

    public string $busqueda = '';
    public string $estado = '';
    public string $almacen = '';
    public bool $formulario = false;
    public array $form = [];
    public $foto = null;
    public $archivo = null;
    public string $mensaje = '';

    #[Locked]
    public ?int $empleadoId = null;

    public function boot(): void
    {
        Gate::authorize('rrhh.empleados.viewAny');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['busqueda', 'estado', 'almacen'], true)) {
            $this->resetPage();
        }

        if ($property === 'form.es_repartidor' && $this->form['es_repartidor']) {
            $this->form['crear_usuario'] = true;
            $this->form['cargo'] = 'repartidor';
        }
    }

    public function nuevo(): void
    {
        Gate::authorize('rrhh.empleados.create');
        $this->cerrar();
        $this->form = array_replace(array_fill_keys(self::CAMPOS, ''), [
            'estado' => 'activo', 'nacionalidad' => 'Chilena', 'estado_civil' => 'Soltero(a)',
            'tipo_contrato' => 'Indefinido', 'sistema_salud' => 'Fonasa',
            'crear_usuario' => false, 'es_repartidor' => false, 'rol_id' => '', 'password' => '',
        ]);
        $this->formulario = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('rrhh.empleados.edit');
        $empleado = $this->empleados()->with('user.roles')->findOrFail($id);
        $this->cerrar();
        $this->empleadoId = $empleado->id;
        foreach (self::CAMPOS as $campo) {
            $this->form[$campo] = $empleado->{$campo} ?? '';
        }
        foreach (['fecha_nacimiento', 'fecha_contratacion', 'hora_entrada', 'hora_salida'] as $campo) {
            $this->form[$campo] = $empleado->{$campo}?->format(str_starts_with($campo, 'hora_') ? 'H:i' : 'Y-m-d') ?? '';
        }
        $this->form['crear_usuario'] = $empleado->user !== null;
        $this->form['es_repartidor'] = $empleado->user?->hasRole(Repartidor::defaultRole()) ?? false;
        $this->form['rol_id'] = $empleado->user?->roles->first()?->id ?? '';
        $this->form['password'] = '';
        $this->formulario = true;
    }

    public function cerrar(): void
    {
        $this->reset('formulario', 'form', 'foto', 'empleadoId');
        $this->resetValidation();
    }

    public function guardar(EmpleadoController $controller, CreateCompanyDriver $createDriver): void
    {
        Gate::authorize($this->empleadoId === null ? 'rrhh.empleados.create' : 'rrhh.empleados.edit');
        $datos = array_map(fn ($valor) => $valor === '' ? null : $valor, $this->form);
        $request = Request::create('/empleados', 'POST', $datos, [], $this->foto ? ['foto' => $this->foto] : []);
        $request->setUserResolver(fn () => auth()->user());

        if ($this->empleadoId === null) {
            $reglas = StoreEmpleadoRequest::createFrom($request);
            $validated = Validator::make($request->all(), $reglas->rules(), $reglas->messages())->validate();
            $this->mensaje = $controller->crearEmpleado($validated, auth()->user(), $createDriver);
            $this->resetPage();
        } else {
            $empleado = $this->empleados()->findOrFail($this->empleadoId);
            if ($request->input('cargo') === $empleado->cargo && ! in_array($empleado->cargo, ['cajero', 'repartidor'], true)) {
                $request->request->remove('cargo');
            }
            $controller->actualizarEmpleado($request, $empleado, $createDriver);
            $this->mensaje = 'Empleado actualizado correctamente.';
        }

        $this->cerrar();
    }

    public function eliminar(int $id, EmpleadoController $controller): void
    {
        Gate::authorize('rrhh.empleados.delete');
        $controller->eliminarEmpleado($this->empleados()->findOrFail($id));
        $this->mensaje = 'Empleado eliminado.';
        $this->resetPage();
    }

    public function importar(): void
    {
        Gate::authorize('rrhh.empleados.import');
        $this->validate(['archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:12288']);

        try {
            Excel::import(new EmpleadosImport, $this->archivo);
        } catch (\Exception $error) {
            report($error);
            $this->addError('archivo', 'No se pudo importar el archivo. Revisa el formato y vuelve a intentarlo.');

            return;
        }

        $this->reset('archivo');
        $this->resetPage();
        $this->mensaje = 'Importación completada.';
    }

    public function render(): View
    {
        $busqueda = '%'.SearchHelper::escapeLike(mb_substr(trim($this->busqueda), 0, 120)).'%';
        $ownerId = auth()->user()->getOwnerId();

        return view('livewire.empleados.index', [
            'empleados' => $this->empleados()->with('almacen:id,nombre')
                ->when(trim($this->busqueda) !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('nombre', 'like', $busqueda)->orWhere('apellido', 'like', $busqueda)
                    ->orWhere('email', 'like', $busqueda)->orWhere('rut', 'like', $busqueda)))
                ->when($this->estado !== '', fn (Builder $query) => $query->where('estado', $this->estado))
                ->when(ctype_digit($this->almacen), fn (Builder $query) => $query->where('almacen_id', $this->almacen))
                ->orderBy('nombre')->orderBy('id')->paginate(15),
            'almacenes' => Almacen::where('owner_id', $ownerId)
                ->where(fn (Builder $query) => $query->where('activo', true)->orWhere('id', (int) ($this->form['almacen_id'] ?? 0)))
                ->orderBy('nombre')->get(['id', 'nombre']),
            'roles' => $this->formulario && ($this->form['crear_usuario'] ?? false)
                ? Role::where('owner_id', $ownerId)->orderBy('name')->get(['id', 'name']) : collect(),
        ])->layout('components.app-layout', [
            'breadcrumbs' => [['title' => 'Empleados', 'href' => route('empleados.index')]],
        ]);
    }

    private function empleados(): Builder
    {
        return Empleado::where('owner_id', auth()->user()->getOwnerId());
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Actions\Delivery\CreateCompanyDriver;
use App\Exports\EmpleadosExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Http\Requests\Backend\StoreEmpleadoRequest;
use App\Imports\EmpleadosImport;
use App\Models\Almacen;
use App\Models\Empleado;
use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class EmpleadoController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:rrhh.empleados.create', only: ['create', 'store']),
            new Middleware('permission:rrhh.empleados.edit', only: ['edit', 'update']),
            new Middleware('permission:rrhh.empleados.delete', only: ['destroy']),
            new Middleware('permission:rrhh.empleados.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:rrhh.empleados.import', only: ['importCsv', 'importExcel']),
        ];
    }

    protected function getExportClass(array $filters): object
    {
        return new EmpleadosExport($filters);
    }

    protected function getImportClass(): object
    {
        return new EmpleadosImport;
    }

    public function index(): Response
    {
        $userId = Auth::user()->getOwnerId();
        $empleados = Empleado::with(['almacen', 'user.roles'])
            ->where('owner_id', $userId)
            ->orderBy('nombre')
            ->paginate(15);

        $empleados->getCollection()->transform(function ($empleado) {
            $empleado->user_rol_id = $empleado->user?->roles?->first()?->id;
            $empleado->user_rol_name = $empleado->user?->roles?->first()?->name;
            $empleado->es_repartidor = $empleado->user?->hasRole(Repartidor::defaultRole()) ?? false;

            return $empleado;
        });

        $almacenes = Almacen::where('owner_id', $userId)->where('activo', true)->orderBy('nombre')->get();

        $roles = Role::where('owner_id', $userId)->orderBy('name')->get();

        return Inertia::render('Backend/Empleados/Index', [
            'empleados' => $empleados,
            'almacenes' => $almacenes,
            'roles' => $roles,
        ]);
    }

    public function store(StoreEmpleadoRequest $request, CreateCompanyDriver $createDriver): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['almacen_id'])) {
            $validated['almacen_id'] = null;
        }

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('empleados', 'public');
        }

        $esRepartidor = (bool) ($validated['es_repartidor'] ?? false);
        $crearUsuario = (bool) ($validated['crear_usuario'] ?? false) || $esRepartidor;
        $password = $validated['password'] ?? null;
        $rolId = $validated['rol_id'] ?? null;

        unset(
            $validated['crear_usuario'],
            $validated['password'],
            $validated['rol_id'],
            $validated['es_repartidor'],
        );

        $actor = $request->user();

        DB::transaction(function () use ($validated, $crearUsuario, $esRepartidor, $password, $rolId, $createDriver, $actor) {
            $validated['user_id'] = null;
            $validated['creator_id'] = $actor->id;

            if ($crearUsuario) {
                if ($esRepartidor) {
                    $result = $createDriver->handle([
                        'name' => trim($validated['nombre'].' '.$validated['apellido']),
                        'email' => $validated['email'],
                        'password' => $password,
                        'telefono' => $validated['telefono'] ?? null,
                        'rut' => $validated['rut'] ?? null,
                        'estado' => 'offline',
                        'radio_km' => 10,
                        'capacidad_max' => 1,
                    ], $actor);

                    $validated['user_id'] = $result['user']->id;
                } else {
                    $passwordHash = $password
                        ? Hash::make($password)
                        : Hash::make(Str::random(16));

                    $user = (new User)->forceFill([
                        'creator_id' => $actor->id,
                        'name' => $validated['nombre'].' '.$validated['apellido'],
                        'email' => $validated['email'],
                        'password' => $passwordHash,
                        'job' => $validated['cargo'] ?? null,
                        'telefono' => $validated['telefono'] ?? null,
                        'direccion' => $validated['direccion'] ?? null,
                    ]);
                    $user->has_explicit_role = true;
                    $user->save();

                    $role = Role::findById($rolId);
                    $user->syncRoles([$role->name]);

                    $validated['user_id'] = $user->id;
                }
            }

            Empleado::create($validated);
        });

        $mensaje = $crearUsuario
            ? 'Empleado y acceso a plataforma creado correctamente.'
            : 'Empleado creado correctamente.';

        return redirect()->route('empleados.index')->with('success', $mensaje);
    }

    public function update(Request $request, Empleado $empleado, CreateCompanyDriver $createDriver): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'nombre' => 'nullable|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:2048',
            'rut' => 'nullable|string|max:20|unique:empleados,rut,'.$empleado->id,
            'email' => [
                'nullable',
                'email',
                'unique:empleados,email,'.$empleado->id,
                'unique:users,email,'.($empleado->user_id ?? '_'),
            ],
            'telefono' => 'nullable|string|max:50',
            'fecha_nacimiento' => 'nullable|date',
            'nacionalidad' => 'nullable|string|max:100',
            'estado_civil' => 'nullable|string|max:50',
            'direccion' => 'nullable|string',
            'comuna' => 'nullable|string|max:100',
            'cargo' => 'nullable|string|max:100|in:repartidor,cajero',
            'departamento' => 'nullable|string|max:100',
            'almacen_id' => [
                'nullable',
                'exists:almacenes,id',
                Rule::in(Almacen::where('owner_id', $ownerId)->pluck('id')),
            ],
            'fecha_contratacion' => 'nullable|date',
            'tipo_contrato' => 'nullable|string|max:50',
            'salario' => 'nullable|numeric|min:0',
            'sueldo_liquido_pactado' => 'nullable|numeric|min:0',
            'afp' => 'nullable|string|max:100',
            'sistema_salud' => 'nullable|string|max:100',
            'isapre_nombre' => 'nullable|string|max:100',
            'banco_nombre' => 'nullable|string|max:100',
            'banco_tipo_cuenta' => 'nullable|string|max:100',
            'banco_numero_cuenta' => 'nullable|string|max:100',
            'hora_entrada' => 'nullable|date_format:H:i',
            'hora_salida' => 'nullable|date_format:H:i',
            'estado' => 'nullable|string|max:50',
            'notas' => 'nullable|string',
            'crear_usuario' => 'nullable|boolean',
            'es_repartidor' => 'nullable|boolean',
            'password' => 'nullable|string|min:6',
            'rol_id' => [
                Rule::excludeIf(fn () => $request->boolean('es_repartidor')),
                Rule::requiredIf(fn () => $request->boolean('crear_usuario') && ! $request->boolean('es_repartidor')),
                'nullable',
                'integer',
                'exists:roles,id',
                Rule::in(Role::where('owner_id', $ownerId)->pluck('id')),
            ],
        ]);

        if ($request->hasFile('foto')) {
            if ($empleado->foto) {
                Storage::disk('public')->delete($empleado->foto);
            }
            $validated['foto'] = $request->file('foto')->store('empleados', 'public');
        }

        $esRepartidor = (bool) ($validated['es_repartidor'] ?? false);
        $crearUsuario = $request->filled('crear_usuario') ? ($validated['crear_usuario'] ?? false) : false;
        $crearUsuario = $crearUsuario || $esRepartidor;
        $actualizarUsuario = $request->filled('crear_usuario') || $esRepartidor;

        $rolId = $validated['rol_id'] ?? null;

        unset($validated['crear_usuario'], $validated['rol_id'], $validated['es_repartidor']);

        $updateData = [];
        foreach ($validated as $key => $value) {
            if ($value !== null && ($request->filled($key) || $request->hasFile($key))) {
                $updateData[$key] = $value;
            }
        }

        if (empty($updateData['almacen_id']) && $request->filled('almacen_id')) {
            $updateData['almacen_id'] = null;
        }

        $usuarioExistente = $empleado->user;

        DB::transaction(function () use ($updateData, $empleado, $crearUsuario, $actualizarUsuario, $usuarioExistente, $request, $rolId, $esRepartidor, $createDriver, $ownerId) {
            if ($actualizarUsuario) {
                if ($crearUsuario && ! $usuarioExistente && $request->filled('email') && $request->filled('nombre') && $request->filled('apellido')) {
                    if ($esRepartidor) {
                        $result = $createDriver->handle([
                            'name' => trim(($updateData['nombre'] ?? $empleado->nombre).' '.($updateData['apellido'] ?? $empleado->apellido)),
                            'email' => $updateData['email'] ?? $empleado->email,
                            'password' => $request->input('password'),
                            'telefono' => $updateData['telefono'] ?? $empleado->telefono,
                            'rut' => $updateData['rut'] ?? $empleado->rut,
                            'estado' => 'offline',
                            'radio_km' => 10,
                            'capacidad_max' => 1,
                        ], $request->user());

                        $updateData['user_id'] = $result['user']->id;
                    } else {
                        $passwordInput = $request->input('password');
                        $passwordHash = $passwordInput
                            ? Hash::make($passwordInput)
                            : Hash::make(Str::random(16));

                        $user = (new User)->forceFill([
                            'creator_id' => Auth::id(),
                            'name' => ($updateData['nombre'] ?? $empleado->nombre).' '.($updateData['apellido'] ?? $empleado->apellido),
                            'email' => $updateData['email'] ?? $empleado->email,
                            'password' => $passwordHash,
                            'job' => $updateData['cargo'] ?? $empleado->cargo,
                            'telefono' => $updateData['telefono'] ?? $empleado->telefono,
                            'direccion' => $updateData['direccion'] ?? $empleado->direccion,
                        ]);
                        $user->has_explicit_role = true;
                        $user->save();

                        $role = Role::findById($rolId);
                        $user->syncRoles([$role->name]);

                        $updateData['user_id'] = $user->id;
                    }
                } elseif ($crearUsuario && $usuarioExistente) {
                    if ($request->filled('password')) {
                        $usuarioExistente->update([
                            'password' => Hash::make($request->input('password')),
                        ]);
                    }
                    if ($esRepartidor) {
                        $usuarioExistente->has_explicit_role = true;
                        $usuarioExistente->save();

                        // Asegurar que el rol Spatie de repartidor exista con guard 'web'
                        // antes de asignarlo. Evita RoleDoesNotExist -> HTTP 500.
                        $rolRepartidor = Role::firstOrCreate(
                            ['name' => Repartidor::defaultRole(), 'guard_name' => 'web'],
                            ['owner_id' => null]
                        );
                        $usuarioExistente->syncRoles([$rolRepartidor->name]);

                        $this->ensureDriverProfile($usuarioExistente, (int) $ownerId);
                    } elseif ($rolId) {
                        $role = Role::findById($rolId);
                        $usuarioExistente->syncRoles([$role->name]);
                    }
                } elseif (! $crearUsuario && $usuarioExistente) {
                    $usuarioExistente->delete();
                    $updateData['user_id'] = null;
                }
            }

            if (! empty($updateData)) {
                $empleado->update($updateData);
            }
        });

        return redirect()->route('empleados.index')->with('success', 'Empleado actualizado correctamente.');
    }

    private function ensureDriverProfile(User $user, int $ownerId): void
    {
        Repartidor::firstOrCreate(
            ['user_id' => $user->id],
            [
                'owner_id' => $ownerId,
                'driver_type' => Repartidor::TYPE_EMPLOYEE,
                'approval_status' => Repartidor::APPROVAL_APPROVED,
                'is_online' => false,
                'estado' => 'offline',
                'radio_km' => 10,
                'capacidad_max' => 1,
            ],
        );
    }

    public function destroy(Empleado $empleado): RedirectResponse
    {
        if ($empleado->user) {
            $empleado->user->delete();
        }

        $empleado->delete();

        return redirect()->route('empleados.index');
    }
}

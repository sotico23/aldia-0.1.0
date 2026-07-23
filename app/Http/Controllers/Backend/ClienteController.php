<?php

namespace App\Http\Controllers\Backend;

use App\Exports\ClientesExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Http\Requests\Backend\StoreClienteRequest;
use App\Http\Requests\Backend\UpdateClienteRequest;
use App\Imports\ClientesImport;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class ClienteController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:comercial.clientes.create', only: ['create', 'store']),
            new Middleware('permission:comercial.clientes.edit', only: ['edit', 'update']),
            new Middleware('permission:comercial.clientes.delete', only: ['destroy']),
            new Middleware('permission:comercial.clientes.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:comercial.clientes.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function getExportClass(array $filters): object
    {
        return new ClientesExport($filters);
    }

    public function getImportClass(): object
    {
        return new ClientesImport;
    }

    public function index(Request $request): Response
    {
        $userId = Auth::user()->getOwnerId();

        $query = Cliente::with('categoria')
            ->where('owner_id', '=', $userId);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%")
                    ->orWhere('ciudad', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->input('categoria_id'));
        }

        $clientes = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($cliente) {
                return [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'nit' => $cliente->nit,
                    'rut' => $cliente->rut,
                    'telefono' => $cliente->telefono,
                    'email' => $cliente->email,
                    'direccion' => $cliente->direccion,
                    'ciudad' => $cliente->ciudad,
                    'region' => $cliente->region,
                    'comuna' => $cliente->comuna,
                    'giro' => $cliente->giro,
                    'contacto' => $cliente->contacto,
                    'telefono_contacto' => $cliente->telefono_contacto,
                    'categoria_id' => $cliente->categoria_id,
                    'activo' => $cliente->activo,
                    'notas' => $cliente->notas,
                    'imagen' => $cliente->imagen,
                    'categoria' => $cliente->categoria,
                    'created_at' => $cliente->created_at,
                    'user_id' => $cliente->user_id,
                    'tiene_acceso' => $cliente->user_id !== null,
                ];
            });

        $categorias = Categoria::where('tipo', 'cliente')
            ->where('activo', true)
            ->where('owner_id', $userId)
            ->get();

        return Inertia::render('Backend/Clientes/Index', [
            'clientes' => $clientes,
            'categorias' => $categorias,
            'filters' => $request->only(['search', 'categoria_id']),
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $crearUsuario = $request->boolean('crear_usuario');

        if ($crearUsuario) {
            $request->validate([
                'email' => 'unique:users,email',
                'password' => ['required', 'string', 'min:8'],
            ]);
        }

        DB::transaction(function () use (&$validated, $crearUsuario, $request) {
            $validated['owner_id'] = Auth::user()->getOwnerId();
            $validated['user_id'] = null;

            if ($crearUsuario) {
                $user = User::create([
                    'creator_id' => Auth::id(),
                    'name' => $validated['nombre'],
                    'email' => $validated['email'],
                    'password' => Hash::make($request->input('password')),
                    'rut' => $validated['rut'] ?? null,
                    'telefono' => $validated['telefono'] ?? null,
                    'direccion' => $validated['direccion'] ?? null,
                    'ciudad' => $validated['ciudad'] ?? null,
                    'region' => $validated['region'] ?? null,
                    'comuna' => $validated['comuna'] ?? null,
                ]);

                $role = Role::firstOrCreate(['name' => 'Cliente']);
                $user->syncRoles([$role->name]);

                $validated['user_id'] = $user->id;
            }

            // Manejar imagen
            if ($request->hasFile('imagen')) {
                $imagenPath = $request->file('imagen')->store('clientes', 'public');
                $validated['imagen'] = Storage::url($imagenPath);
            }

            Cliente::create($validated);
        });

        $mensaje = $crearUsuario
            ? 'Cliente y acceso a plataforma creado correctamente.'
            : 'Cliente creado correctamente.';

        return redirect()->route('clientes.index')->with('success', $mensaje);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $validated = $request->validated();
        $crearUsuario = $request->boolean('crear_usuario');
        unset($validated['crear_usuario']);

        $usuarioExistente = $cliente->user;

        if ($crearUsuario && ! $usuarioExistente) {
            $request->validate([
                'email' => 'unique:users,email',
                'password' => ['required', 'string', 'min:8'],
            ]);

            $user = User::create([
                'creator_id' => Auth::id(),
                'name' => $validated['nombre'],
                'email' => $validated['email'],
                'password' => Hash::make($request->input('password')),
                'rut' => $validated['rut'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'ciudad' => $validated['ciudad'] ?? null,
                'region' => $validated['region'] ?? null,
                'comuna' => $validated['comuna'] ?? null,
            ]);

            $role = Role::firstOrCreate(['name' => 'Cliente']);
            $user->syncRoles([$role->name]);

            $validated['user_id'] = $user->id;
        } elseif ($crearUsuario && $usuarioExistente) {
            if (! $usuarioExistente->creator_id) {
                $usuarioExistente->update([
                    'creator_id' => Auth::id(),
                ]);
            }

            $usuarioExistente->syncRoles(['Cliente']);

            if ($request->filled('password')) {
                $usuarioExistente->update([
                    'password' => Hash::make($request->input('password')),
                ]);
            }
        } elseif (! $crearUsuario && $usuarioExistente) {
            $usuarioExistente->delete();
            $validated['user_id'] = null;
        }

        // Manejar imagen
        if ($request->hasFile('imagen')) {
            if ($cliente->imagen) {
                $path = str_replace('/storage/', '', $cliente->imagen);
                Storage::delete($path);
            }
            $imagenPath = $request->file('imagen')->store('clientes', 'public');
            $validated['imagen'] = Storage::url($imagenPath);
        }

        $cliente->update($validated);

        return redirect()->route('clientes.index');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $cliente->delete();

        return redirect()->route('clientes.index');
    }

    public function show(Cliente $cliente): Response
    {
        $cliente->load('categoria');

        return Inertia::render('Backend/Clientes/Show', [
            'cliente' => $cliente,
        ]);
    }
}

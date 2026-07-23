<?php

namespace App\Http\Controllers\Backend;

use App\Exports\ProveedorsExport;
use App\Helpers\NotificationHelper;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backend\StoreProveedorRequest;
use App\Http\Requests\Backend\UpdateProveedorRequest;
use App\Imports\ProveedorsImport;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\User;
use App\Notifications\WelcomeProveedorNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProveedorController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventario.proveedores.create', only: ['create', 'store']),
            new Middleware('permission:inventario.proveedores.edit', only: ['edit', 'update']),
            new Middleware('permission:inventario.proveedores.delete', only: ['destroy']),
            new Middleware('permission:inventario.proveedores.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:inventario.proveedores.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $userId = Auth::user()->getOwnerId();
        $query = Proveedor::with('categoria')
            ->where('owner_id', $userId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('nit', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->input('categoria_id'));
        }

        $proveedors = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($p) {
                $p->tiene_acceso = $p->user_id !== null;

                return $p;
            });
        $categorias = Categoria::where('tipo', 'proveedor')->where('activo', true)->where('owner_id', $userId)->get();

        return Inertia::render('Backend/Proveedores/Index', [
            'proveedors' => $proveedors,
            'categorias' => $categorias,
            'filters' => $request->only(['search', 'categoria_id']),
        ]);
    }

    public function show(Proveedor $proveedor): Response
    {
        $proveedor->load('categoria');
        $proveedor->tiene_acceso = $proveedor->user_id !== null;

        return Inertia::render('Backend/Proveedores/Show', [
            'proveedor' => $proveedor,
        ]);
    }

    private function ensureProveedorRole(User $user): void
    {
        $role = Role::firstOrCreate(['name' => 'Proveedor']);

        $portalPermissions = ['compras.viewOwn', 'perfil.edit', 'documentos.upload'];
        $role->syncPermissions($portalPermissions);

        $user->syncRoles([$role->name]);
    }

    public function store(StoreProveedorRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $crearUsuario = $request->boolean('crear_usuario');

        if ($crearUsuario) {
            $request->validate(['email' => 'unique:users,email']);
        }

        $plainPassword = $request->input('password') ?: Str::random(16);
        $userToNotify = null;

        DB::transaction(function () use ($validated, $crearUsuario, $plainPassword, &$userToNotify) {
            $validated['owner_id'] = Auth::user()->getOwnerId();
            $validated['user_id'] = null;

            if ($crearUsuario) {
                $user = User::create([
                    'creator_id' => Auth::id(),
                    'name' => $validated['nombre'],
                    'email' => $validated['email'],
                    'password' => Hash::make($plainPassword),
                    'telefono' => $validated['telefono'] ?? null,
                    'direccion' => $validated['direccion'] ?? null,
                ]);

                $this->ensureProveedorRole($user);
                $validated['user_id'] = $user->id;

                $userToNotify = $user;
            }

            Proveedor::create($validated);
        });

        if ($userToNotify) {
            NotificationHelper::send($userToNotify, new WelcomeProveedorNotification($userToNotify->email));
        }

        return redirect()->route('proveedors.index');
    }

    public function update(UpdateProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $validated = $request->validated();
        $crearUsuario = $request->boolean('crear_usuario');
        unset($validated['crear_usuario']);

        $usuarioExistente = $proveedor->user;
        $plainPassword = $request->input('password') ?: Str::random(16);
        $userToNotify = null;

        DB::transaction(function () use ($validated, $proveedor, $crearUsuario, $usuarioExistente, $plainPassword, &$userToNotify) {
            if ($crearUsuario && ! $usuarioExistente) {
                request()->validate(['email' => 'unique:users,email']);

                $user = User::create([
                    'creator_id' => Auth::id(),
                    'name' => $validated['nombre'],
                    'email' => $validated['email'],
                    'password' => Hash::make($plainPassword),
                    'telefono' => $validated['telefono'] ?? null,
                    'direccion' => $validated['direccion'] ?? null,
                ]);

                $this->ensureProveedorRole($user);
                $validated['user_id'] = $user->id;

                $userToNotify = $user;
            } elseif ($crearUsuario && $usuarioExistente) {
                if (! empty($validated['password'])) {
                    $usuarioExistente->update([
                        'password' => Hash::make($validated['password']),
                    ]);
                }

                if (! $usuarioExistente->is_active) {
                    $usuarioExistente->update(['is_active' => true]);
                }
            } elseif (! $crearUsuario && $usuarioExistente) {
                $usuarioExistente->update([
                    'is_active' => false,
                    'password' => Hash::make(Str::random(32)),
                ]);
                $usuarioExistente->removeRole('Proveedor');
                $validated['user_id'] = null;
            }

            $proveedor->update($validated);
        });

        if (isset($userToNotify)) {
            NotificationHelper::send($userToNotify, new WelcomeProveedorNotification($userToNotify->email));
        }

        return redirect()->route('proveedors.index');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        if ($proveedor->user) {
            $proveedor->user->update([
                'is_active' => false,
                'password' => Hash::make(Str::random(32)),
            ]);
            $proveedor->user->removeRole('Proveedor');
        }

        $proveedor->delete();

        return redirect()->route('proveedors.index');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Proveedor::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('nit', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->input('categoria_id'));
        }

        $proveedors = $query->orderBy('nombre', 'asc')->get();
        $filename = 'proveedores_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($proveedors) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Nombre', 'NIT/RUT', 'Telefono', 'Email', 'Direccion', 'Empresa', 'Contacto', 'Activo', 'Notas'], ';');

            foreach ($proveedors as $p) {
                fputcsv($file, [
                    $p->nombre,
                    $p->nit,
                    $p->telefono,
                    $p->email,
                    $p->direccion,
                    $p->nombre_empresa,
                    $p->contacto_principal,
                    $p->activo ? 'Si' : 'No',
                    $p->notas,
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportExcel(Request $request)
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Proveedor::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('nit', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->input('categoria_id'));
        }

        return Excel::download(new ProveedorsExport($query->get()), 'proveedores_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt,xlsx,xls']);
        Excel::import(new ProveedorsImport, $request->file('archivo'));

        return redirect()->back()->with('success', 'Proveedores importados correctamente.');
    }

    public function importExcel(Request $request): RedirectResponse
    {
        return $this->importCsv($request);
    }
}

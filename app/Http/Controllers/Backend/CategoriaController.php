<?php

namespace App\Http\Controllers\Backend;

use App\Exports\CategoriasExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\CategoriasImport;
use App\Models\Categoria;
use App\Models\PublicProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:comercial.categorias.create', only: ['create', 'store']),
            new Middleware('permission:comercial.categorias.edit', only: ['edit', 'update']),
            new Middleware('permission:comercial.categorias.delete', only: ['destroy']),
            new Middleware('permission:comercial.categorias.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:comercial.categorias.import', only: ['importCsv', 'importExcel']),
        ];
    }

    protected function getExportClass(array $filters): object
    {
        return new CategoriasExport($filters);
    }

    protected function getImportClass(): object
    {
        return new CategoriasImport;
    }

    public function index(Request $request): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $publicProfile = PublicProfile::where('user_id', $ownerId)->first();

        // Auto-sync missing public_profile_id for current owner's categories
        if ($publicProfile) {
            Categoria::where('owner_id', $ownerId)
                ->whereNull('public_profile_id')
                ->update(['public_profile_id' => $publicProfile->id]);
        }

        $query = Categoria::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where('nombre', 'like', "%{$search}%")
                ->orWhere('id', 'like', "%{$search}%");
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        $categorias = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('Backend/Categorias/Index', [
            'categorias' => $categorias,
            'tiendaSlug' => $publicProfile?->slug,
            'filters' => $request->only(['search', 'tipo']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo' => 'required|in:producto,cliente,proveedor,servicio',
            'activo' => 'boolean',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'mostrar_en_perfil' => 'boolean',
        ]);

        if ($request->hasFile('imagen')) {
            $validated['imagen'] = $request->file('imagen')->store('categorias', 'public');
        }

        $data = array_merge($validated, [
            'owner_id' => auth()->user()->getOwnerId(),
            'user_id' => auth()->id(),
        ]);

        $publicProfile = PublicProfile::where('user_id', auth()->user()->getOwnerId())->first();
        if ($publicProfile) {
            $data['public_profile_id'] = $publicProfile->id;
        }

        Categoria::create($data);

        return redirect()->route('categorias.index');
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        if ($categoria->owner_id !== auth()->user()->getOwnerId()) {
            abort(403);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo' => 'required|in:producto,cliente,proveedor,servicio',
            'activo' => 'boolean',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'mostrar_en_perfil' => 'boolean',
        ]);

        if ($request->hasFile('imagen')) {
            if ($categoria->imagen) {
                Storage::disk('public')->delete($categoria->imagen);
            }
            $validated['imagen'] = $request->file('imagen')->store('categorias', 'public');
        } else {
            unset($validated['imagen']);
        }

        $publicProfile = PublicProfile::where('user_id', auth()->user()->getOwnerId())->first();
        if ($publicProfile) {
            $validated['public_profile_id'] = $publicProfile->id;
        }

        $categoria->update($validated);

        return redirect()->route('categorias.index');
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        if ($categoria->owner_id !== auth()->user()->getOwnerId()) {
            abort(403);
        }

        if ($categoria->imagen) {
            Storage::disk('public')->delete($categoria->imagen);
        }
        $categoria->delete();

        return redirect()->route('categorias.index');
    }
}

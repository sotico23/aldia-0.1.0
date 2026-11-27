<?php

namespace App\Http\Controllers\Backend;

use App\Exports\CategoriasExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\CategoriasImport;
use App\Livewire\Categorias\Index as CategoriasIndex;
use App\Models\Categoria;
use App\Models\PublicProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('categorias.index'));
        }

        config(['livewire.csp_safe' => true]);

        return app(CategoriasIndex::class)();
    }

    public function store(Request $request): RedirectResponse
    {
        $this->crearCategoria($request);

        return redirect()->route('categorias.index');
    }

    public function crearCategoria(Request $request): void
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo' => ['required', Rule::in(array_keys(Categoria::TIPOS))],
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
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $this->actualizarCategoria($request, $categoria);

        return redirect()->route('categorias.index');
    }

    public function actualizarCategoria(Request $request, Categoria $categoria): void
    {
        if ($categoria->owner_id !== auth()->user()->getOwnerId()) {
            abort(403);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo' => ['required', Rule::in(array_keys(Categoria::TIPOS))],
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
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        $this->eliminarCategoria($categoria);

        return redirect()->route('categorias.index');
    }

    public function eliminarCategoria(Categoria $categoria): void
    {
        if ($categoria->owner_id !== auth()->user()->getOwnerId()) {
            abort(403);
        }

        if ($categoria->imagen) {
            Storage::disk('public')->delete($categoria->imagen);
        }
        $categoria->delete();
    }
}

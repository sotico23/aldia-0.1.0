<?php

namespace App\Http\Controllers\Proveedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proveedor\UpdateProfileRequest;
use App\Models\ProveedorDocumento;
use App\Models\User;
use App\Models\WebSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $proveedor = $user->getProveedorActual();

        if (! $proveedor) {
            abort(403, 'No tienes un perfil de proveedor asociado.');
        }

        $ownerId = $proveedor->owner_id;
        $owner = User::findOrFail($ownerId);

        // Load compras with details for this provider
        $compras = $proveedor->compras()
            ->with(['detalleCompras.producto'])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Load documents for this provider
        $documentos = $proveedor->documentos()
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Proveedor', [
            'proveedor' => [
                'id' => $proveedor->id,
                'nombre' => $proveedor->nombre,
                'nit' => $proveedor->nit,
                'telefono' => $proveedor->telefono,
                'email' => $proveedor->email,
                'direccion' => $proveedor->direccion,
                'activo' => $proveedor->activo,
            ],
            'compras' => $compras,
            'documentos' => $documentos,
            'business' => [
                'name' => $owner->dashboard_name ?: $owner->name,
                'logo' => $owner->businessLogoUrl(),
                'primary_color' => $owner->primary_color ?: '#4f46e5',
                'secondary_color' => $owner->secondary_color ?: '#06b6d4',
                'phone' => $owner->telefono,
                'email' => $owner->email,
            ],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $proveedor = $user->getProveedorActual();

        if (! $proveedor) {
            abort(403);
        }

        $proveedor->update($request->validated());

        return redirect()->route('proveedor.dashboard')->with('success', 'Perfil actualizado correctamente.');
    }

    public function downloadCompraPdf(int $compraId): \Illuminate\Http\Response
    {
        $user = Auth::user();
        $proveedor = $user->getProveedorActual();

        if (! $proveedor) {
            abort(403);
        }

        $compra = $proveedor->compras()
            ->with(['detalleCompras.producto', 'proveedor'])
            ->findOrFail($compraId);

        $path = $user->business_logo_path ? storage_path('app/public/'.$user->business_logo_path) : null;
        $logo = $path && file_exists($path)
            ? $path
            : (
                class_exists(WebSetting::class) && WebSetting::getSettings()->app_logo
                    ? public_path(WebSetting::getSettings()->app_logo)
                    : public_path('favicon.svg')
            );

        $pdf = Pdf::loadView('pdf.compra', compact('compra', 'logo'));

        return $pdf->download('orden_compra_'.$compra->numero.'.pdf');
    }

    public function uploadDocument(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $proveedor = $user->getProveedorActual();

        if (! $proveedor) {
            abort(403);
        }

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'descripcion' => 'nullable|string|max:1000',
        ]);

        $path = $request->file('archivo')->store('proveedor-docs/'.$proveedor->id, 'public');

        ProveedorDocumento::create([
            'proveedor_id' => $proveedor->id,
            'owner_id' => $proveedor->owner_id,
            'titulo' => $validated['titulo'],
            'archivo' => $path,
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        return redirect()->route('proveedor.dashboard')->with('success', 'Documento subido correctamente.');
    }

    public function deleteDocument(int $documentoId): RedirectResponse
    {
        $user = Auth::user();
        $proveedor = $user->getProveedorActual();

        if (! $proveedor) {
            abort(403);
        }

        $documento = $proveedor->documentos()->findOrFail($documentoId);
        Storage::disk('public')->delete($documento->archivo);
        $documento->delete();

        return redirect()->route('proveedor.dashboard')->with('success', 'Documento eliminado correctamente.');
    }
}

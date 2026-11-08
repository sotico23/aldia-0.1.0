<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Repartidor;
use App\Scopes\OwnerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Legajo digital del repartidor (freelancer).
 *
 * GET  /api/v1/driver/documents  → lista los documentos cargados.
 * POST /api/v1/driver/documents  → sube/reemplaza un documento (multipart).
 *
 * Se usa `auth:sanctum` + `active` (sin el middleware `delivery`) para que un
 * freelancer pendiente de aprobación pueda completar su legajo.
 */
class DriverDocumentsController extends Controller
{
    private const TIPOS = [
        'cedula_frontal',
        'cedula_reverso',
        'licencia',
        'permiso_circulacion',
        'foto_vehiculo',
        'otro',
    ];

    public function index(Request $request): JsonResponse
    {
        $repartidor = $this->repartidor($request);

        return response()->json([
            'success' => true,
            'documents' => $this->documentos($repartidor),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:'.implode(',', self::TIPOS)],
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ], [
            'tipo.in' => 'El tipo de documento no es válido.',
            'archivo.mimes' => 'El archivo debe ser JPG, PNG o PDF.',
            'archivo.max' => 'El archivo no puede superar los 8 MB.',
        ]);

        $repartidor = $this->repartidor($request);
        $tipo = $validated['tipo'];
        $documentos = $repartidor->documentos ?? [];

        // Eliminar el archivo anterior del mismo tipo para no acumular basura.
        $anterior = $documentos[$tipo]['path'] ?? null;

        $archivo = $request->file('archivo');
        $path = $archivo->store('driver-documents/'.$repartidor->user_id, 'public');

        if ($anterior && $anterior !== $path && Storage::disk('public')->exists($anterior)) {
            Storage::disk('public')->delete($anterior);
        }

        $documentos[$tipo] = [
            'tipo' => $tipo,
            'nombre' => $archivo->getClientOriginalName(),
            'path' => $path,
            'subido_at' => now()->toISOString(),
        ];

        $updates = ['documentos' => $documentos];

        // Si el legajo había sido rechazado, una nueva carga lo devuelve a
        // revisión para que el equipo valide los documentos corregidos.
        if ($repartidor->approval_status === Repartidor::APPROVAL_REJECTED) {
            $updates['approval_status'] = Repartidor::APPROVAL_PENDING;
            $updates['rejected_reason'] = null;
        }

        $repartidor->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Documento subido correctamente.',
            'data' => $this->documentoResource($documentos[$tipo]),
        ]);
    }

    private function repartidor(Request $request): Repartidor
    {
        return Repartidor::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentos(Repartidor $repartidor): array
    {
        return collect($repartidor->documentos ?? [])
            ->map(fn (array $documento) => $this->documentoResource($documento))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $documento
     * @return array<string, mixed>
     */
    private function documentoResource(array $documento): array
    {
        $path = $documento['path'] ?? '';

        return [
            'tipo' => $documento['tipo'] ?? 'otro',
            'nombre' => $documento['nombre'] ?? 'Documento',
            'path' => $path,
            'url' => $path !== '' ? url(Storage::disk('public')->url($path)) : '',
            'subido_at' => $documento['subido_at'] ?? null,
        ];
    }
}

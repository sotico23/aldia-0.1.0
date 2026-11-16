<?php

namespace App\Http\Controllers\Backend;

use App\Events\RepartidorEstadoActualizado;
use App\Http\Controllers\Controller;
use App\Models\Repartidor;
use App\Models\User;
use App\Scopes\OwnerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RepartidorSolicitudesController extends Controller implements HasMiddleware
{
    private const DOCUMENT_LABELS = [
        'cedula_frontal' => 'Cédula de identidad · frontal',
        'cedula_reverso' => 'Cédula de identidad · reverso',
        'licencia' => 'Licencia de conducir',
        'permiso_circulacion' => 'Permiso de circulación',
        'foto_vehiculo' => 'Foto del vehículo',
        'otro' => 'Otro documento',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.usuarios.viewAny', only: ['index']),
            new Middleware('permission:admin.usuarios.edit', only: ['aprobar', 'rechazar']),
        ];
    }

    /**
     * Lista las solicitudes de repartidores pendientes de aprobación.
     *
     * GET /api/v1/admin/repartidores/solicitudes
     */
    public function index(Request $request): JsonResponse
    {
        $solicitudes = Repartidor::where('approval_status', Repartidor::APPROVAL_PENDING)
            ->where('driver_type', Repartidor::TYPE_FREELANCER)
            ->with('user:id,name,email,rut,telefono,created_at')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Repartidor $r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'nombre' => $r->user?->name ?? 'Sin nombre',
                'rut' => $r->user?->rut ?? null,
                'email' => $r->user?->email ?? null,
                'telefono' => $r->user?->telefono ?? null,
                'fecha_registro' => $r->user?->created_at?->toISOString(),
                'documentos' => $r->documentos ?? [],
                'estado' => $r->estado,
                'approval_status' => $r->approval_status,
                'driver_type' => $r->driver_type,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $solicitudes,
        ]);
    }

    public function detalle(Request $request, int $id): JsonResponse
    {
        $this->requireMaster($request);

        $repartidor = Repartidor::withoutGlobalScope(OwnerScope::class)
            ->with('user:id,name,email,rut,telefono,created_at')
            ->findOrFail($id);
        $user = $repartidor->user;
        $empresa = (int) $repartidor->owner_id !== (int) $repartidor->user_id
            ? User::withoutGlobalScopes()->find($repartidor->owner_id)
            : null;
        $disk = Storage::disk('public');
        $documentos = collect($repartidor->documentos ?? [])->map(function (mixed $documento, string|int $key) use ($id, $disk): ?array {
            if (is_string($documento)) {
                $url = filter_var($documento, FILTER_VALIDATE_URL) && in_array(parse_url($documento, PHP_URL_SCHEME), ['http', 'https'], true)
                    ? $documento : null;
                if ($url === null) {
                    return null;
                }

                $tipo = (string) $key;

                return ['tipo' => $tipo, 'label' => self::DOCUMENT_LABELS[$tipo] ?? Str::headline($tipo),
                    'nombre' => basename((string) parse_url($url, PHP_URL_PATH)) ?: 'Documento', 'url' => $url,
                    'mime_type' => null, 'bytes' => null, 'subido_at' => null];
            }
            if (! is_array($documento)) {
                return null;
            }

            $tipo = (string) ($documento['tipo'] ?? $key);
            $path = (string) ($documento['path'] ?? '');
            $fileExists = $path !== '' && $disk->exists($path);
            $url = $fileExists ? route('admin.repartidores.documento', ['id' => $id, 'tipo' => $tipo]) : null;
            $legacyUrl = $documento['url'] ?? null;
            if ($url === null && is_string($legacyUrl) && filter_var($legacyUrl, FILTER_VALIDATE_URL)
                && in_array(parse_url($legacyUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                $url = $legacyUrl;
            }

            return ['tipo' => $tipo, 'label' => self::DOCUMENT_LABELS[$tipo] ?? Str::headline($tipo),
                'nombre' => (string) ($documento['nombre'] ?? 'Documento'), 'url' => $url,
                'mime_type' => $fileExists ? $disk->mimeType($path) : null,
                'bytes' => $fileExists ? $disk->size($path) : null,
                'subido_at' => $documento['subido_at'] ?? null];
        })->filter()->values()->all();

        return response()->json(['success' => true, 'data' => [
            'id' => $repartidor->id,
            'solicitud' => [
                'tipo' => $repartidor->driver_type,
                'estado' => $repartidor->approval_status,
                'fecha' => $user?->created_at?->toISOString(),
            ],
            'persona' => [
                'nombre' => $user?->name,
                'email' => $user?->email,
                'telefono' => $user?->telefono,
                'rut' => $user?->rut,
            ],
            'vehiculo' => [
                'tipo' => $repartidor->vehiculo_tipo,
                'radio_km' => $repartidor->radio_km,
            ],
            'empresa' => $empresa ? ['codigo' => $empresa->id, 'nombre' => $empresa->name] : null,
            'independiente' => $empresa === null,
            'antecedentes' => [
                'aceptados' => $repartidor->antecedentes_aceptados_at !== null,
                'fecha' => $repartidor->antecedentes_aceptados_at?->toISOString(),
            ],
            'documentos' => $documentos,
        ]]);
    }

    public function documento(Request $request, int $id, string $tipo): StreamedResponse
    {
        $this->requireMaster($request);

        $repartidor = Repartidor::withoutGlobalScope(OwnerScope::class)->findOrFail($id);
        $documento = $repartidor->documentos[$tipo] ?? null;
        abort_unless(is_array($documento) && filled($documento['path'] ?? null), 404);

        $disk = Storage::disk('public');
        $path = (string) $documento['path'];
        abort_unless($disk->exists($path), 404);
        $filename = Str::of((string) ($documento['nombre'] ?? 'documento'))
            ->replaceMatches('/[\r\n"\\\\]/', '')
            ->limit(180, '')
            ->toString();

        return $disk->response($path, $filename, ['Content-Disposition' => 'inline']);
    }

    /**
     * Aprueba un repartidor solicitante.
     *
     * POST /api/v1/admin/repartidores/{id}/aprobar
     */
    public function aprobar(Request $request, int $id): JsonResponse
    {
        $this->requireMaster($request);

        $repartidor = Repartidor::where('id', $id)
            ->where('approval_status', Repartidor::APPROVAL_PENDING)
            ->first();

        if (! $repartidor) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud de repartidor no encontrada o ya procesada.',
            ], 404);
        }

        $user = User::find($repartidor->user_id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario asociado no encontrado.',
            ], 404);
        }

        // Actualizar estado del repartidor
        $repartidor->update([
            'approval_status' => Repartidor::APPROVAL_APPROVED,
            'estado' => 'disponible',
            'rejected_reason' => null,
        ]);

        // Asignar rol Spatie de Repartidor al usuario
        $user->assignRole(Repartidor::defaultRole());

        // Emitir notificación en tiempo real al usuario
        Broadcast::channel('notifications.'.$user->id, function ($u) use ($user) {
            return (int) $u->id === (int) $user->id;
        });

        broadcast(new RepartidorEstadoActualizado($user, 'aprobado', 'Tu solicitud de repartidor ha sido aprobada. Ya puedes recibir entregas.'));

        return response()->json([
            'success' => true,
            'message' => 'Repartidor aprobado correctamente.',
            'data' => [
                'id' => $repartidor->id,
                'user_id' => $repartidor->user_id,
                'nombre' => $user->name,
                'email' => $user->email,
                'approval_status' => $repartidor->approval_status,
                'estado' => $repartidor->estado,
            ],
        ]);
    }

    /**
     * Rechaza un repartidor solicitante.
     *
     * POST /api/v1/admin/repartidores/{id}/rechazar
     */
    public function rechazar(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        $repartidor = Repartidor::where('id', $id)
            ->where('approval_status', Repartidor::APPROVAL_PENDING)
            ->first();

        if (! $repartidor) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud de repartidor no encontrada o ya procesada.',
            ], 404);
        }

        $user = User::find($repartidor->user_id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario asociado no encontrado.',
            ], 404);
        }

        // Actualizar estado del repartidor
        $repartidor->update([
            'approval_status' => Repartidor::APPROVAL_REJECTED,
            'estado' => 'offline',
            'rejected_reason' => $validated['motivo'] ?? null,
        ]);

        // Emitir notificación en tiempo real al usuario
        broadcast(new RepartidorEstadoActualizado($user, 'rechazado', 'Tu solicitud de repartidor ha sido rechazada.'.($validated['motivo'] ? ' Motivo: '.$validated['motivo'] : '')));

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de repartidor rechazada.',
            'data' => [
                'id' => $repartidor->id,
                'user_id' => $repartidor->user_id,
                'nombre' => $user->name,
                'email' => $user->email,
                'approval_status' => $repartidor->approval_status,
                'estado' => $repartidor->estado,
                'rejected_reason' => $repartidor->rejected_reason,
            ],
        ]);
    }

    private function requireMaster(Request $request): void
    {
        abort_unless($request->user()?->hasRole('Master'), 403, 'Solo el rol Master puede revisar o aprobar solicitudes de repartidores.');
    }
}

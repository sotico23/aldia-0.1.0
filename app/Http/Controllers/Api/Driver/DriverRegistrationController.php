<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\RegisterFreelancerRequest;
use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/**
 * Controlador para el registro y aprobación de repartidores Freelancer.
 *
 * Flujo:
 *   1. POST /api/v1/driver/register  → crea User + Repartidor con approval_status = pending_approval
 *   2. Admin aprueba/rechaza desde el panel web (futuro endpoint admin)
 *   3. GET  /api/v1/driver/status    → el freelancer consulta su estado de aprobación
 */
class DriverRegistrationController extends Controller
{
    /**
     * POST /api/v1/driver/register
     *
     * Registro de repartidor Freelancer desde la app móvil.
     * No requiere autenticación previa — crea la cuenta en un solo paso.
     */
    public function register(RegisterFreelancerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $companyId = isset($validated['owner_id']) ? (int) $validated['owner_id'] : null;

        try {
            DB::beginTransaction();

            // Crear el usuario
            $user = (new User)->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'telefono' => $validated['telefono'],
                'rut' => $validated['rut'] ?? null,
                'creator_id' => $companyId,
                'owner_id' => $companyId,
                'is_active' => true,
                'has_explicit_role' => true,
            ]);
            $user->save();

            if ($companyId === null) {
                $user->forceFill(['owner_id' => $user->id])->save();
            }
            $ownerId = $companyId ?? $user->id;

            // Asignar el rol por defecto de repartidor via Spatie.
            // Asegurar que el rol exista con guard 'web' para evitar
            // RoleDoesNotExist -> HTTP 500.
            $rolRepartidor = Role::firstOrCreate(
                ['name' => Repartidor::defaultRole(), 'guard_name' => 'web'],
                ['owner_id' => null]
            );
            $user->syncRoles([$rolRepartidor]);

            // Crear perfil de repartidor en estado pendiente
            $repartidor = Repartidor::create([
                'owner_id' => $ownerId,
                'user_id' => $user->id,
                'driver_type' => Repartidor::TYPE_FREELANCER,
                'approval_status' => Repartidor::APPROVAL_PENDING,
                'vehiculo_tipo' => $validated['vehiculo_tipo'],
                'antecedentes_aceptados_at' => ($validated['antecedentes_aceptados'] ?? false) ? now() : null,
                'is_online' => false,
                'estado' => 'offline',
                'capacidad_max' => 1,
                'radio_km' => $validated['radio_km'] ?? 10,
                'comision_pct' => 80.00, // 80% del costo de envío por defecto
            ]);

            // Guardar notas del vehículo en el perfil (sin tabla separada por ahora)
            Log::info('driver.registration', [
                'user_id' => $user->id,
                'repartidor_id' => $repartidor->id,
                'vehiculo_tipo' => $validated['vehiculo_tipo'],
                'owner_id' => $ownerId,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tu solicitud fue enviada. Recibirás una notificación cuando sea aprobada.',
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values(),
                    'owner_id' => $user->owner_id,
                    'repartidor_id' => $repartidor->id,
                    'approval_status' => $repartidor->approval_status,
                    'driver_type' => $repartidor->driver_type,
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('driver.registration.error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo completar el registro. Intenta de nuevo.',
            ], 500);
        }
    }

    /**
     * GET /api/v1/driver/status
     *
     * Consulta el estado de aprobación del perfil (para freelancers en espera).
     * Requiere autenticación (Sanctum token).
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $repartidor = Repartidor::where('user_id', $user->id)->first();

        if (! $repartidor) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un perfil de repartidor.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'approval_status' => $repartidor->approval_status,
                'driver_type' => $repartidor->driver_type,
                'is_online' => $repartidor->is_online,
                'rejected_reason' => $repartidor->rejected_reason,
                'mensaje' => match ($repartidor->approval_status) {
                    'approved' => 'Tu perfil está aprobado. ¡Puedes comenzar a repartir!',
                    'pending_approval' => 'Tu solicitud está en revisión. Te notificaremos pronto.',
                    'rejected' => 'Tu solicitud fue rechazada. '.($repartidor->rejected_reason ?? ''),
                    default => 'Estado desconocido.',
                },
            ],
        ]);
    }
}

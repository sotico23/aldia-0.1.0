<?php

namespace App\Actions\Delivery;

use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea (o vincula) un repartidor perteneciente a la empresa que lo registra.
 *
 * Garantiza tres cosas en una sola transacción:
 *  1. El usuario existe y queda ligado al tenant (`creator_id` + `owner_id`).
 *  2. Tiene el rol Spatie por defecto de repartidor (nunca el rol genérico).
 *  3. Existe su perfil en `repartidores` con el mismo `owner_id`.
 */
class CreateCompanyDriver
{
    /**
     * @param  array{user_id?: int|string|null, name?: string|null, email?: string|null, password?: string|null, telefono?: string|null, rut?: string|null, estado: string, radio_km: int|float|string, capacidad_max: int|string}  $data
     * @return array{user: User, repartidor: Repartidor}
     */
    public function handle(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $ownerId = (int) $actor->getOwnerId();

            $user = ! empty($data['user_id'])
                ? $this->linkExistingUser((int) $data['user_id'], $ownerId)
                : $this->createUser($data, $actor, $ownerId);

            $user->has_explicit_role = true;
            $user->save();
            $user->syncRoles([Repartidor::defaultRole()]);

            $repartidor = Repartidor::create([
                'owner_id' => $ownerId,
                'user_id' => $user->id,
                'driver_type' => Repartidor::TYPE_EMPLOYEE,
                'approval_status' => Repartidor::APPROVAL_APPROVED,
                'is_online' => false,
                'estado' => $data['estado'],
                'radio_km' => $data['radio_km'],
                'capacidad_max' => $data['capacidad_max'],
            ]);

            return [
                'user' => $user->fresh()->load('roles'),
                'repartidor' => $repartidor,
            ];
        });
    }

    /**
     * @param  array{name?: string|null, email?: string|null, password?: string|null, telefono?: string|null, rut?: string|null}  $data
     */
    private function createUser(array $data, User $actor, int $ownerId): User
    {
        $user = (new User)->forceFill([
            'creator_id' => $actor->id,
            'owner_id' => $ownerId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'] ?? Str::random(16)),
            'telefono' => $data['telefono'] ?? null,
            'rut' => $data['rut'] ?? null,
            'is_active' => true,
            'has_explicit_role' => true,
        ]);

        $user->save();

        return $user;
    }

    private function linkExistingUser(int $userId, int $ownerId): User
    {
        $user = User::findOrFail($userId);

        if ($user->getOwnerId() !== $ownerId && (int) $user->creator_id !== $ownerId) {
            abort(403, 'No puedes vincular un usuario de otra empresa.');
        }

        return $user;
    }
}

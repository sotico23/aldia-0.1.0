<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venta;

/**
 * Policy para Venta.
 *
 * Reglas de negocio (agnóstico de roles):
 *  - Las acciones de capacidad se resuelven con permisos Spatie (ventas.ventas.*).
 *  - "Editar/eliminar sus propias ventas" se resuelve por propiedad (venta.user_id).
 *  - Master / Super Admin se resuelven vía Gate::before en AppServiceProvider
 *    (no se pregunta por el rol aquí).
 */
class VentaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ventas.ventas.viewAny');
    }

    public function view(User $user, Venta $venta): bool
    {
        return $user->can('ventas.ventas.viewAny');
    }

    public function create(User $user): bool
    {
        return $user->can('ventas.ventas.create');
    }

    /**
     * Edición: permiso global de edición O ser el creador de la venta.
     */
    public function update(User $user, Venta $venta): bool
    {
        if ($user->can('ventas.ventas.edit')) {
            return true;
        }

        return $venta->user_id === $user->id;
    }

    /**
     * Eliminación: permiso global de eliminación O ser el creador de la venta.
     */
    public function delete(User $user, Venta $venta): bool
    {
        if ($user->can('ventas.ventas.delete')) {
            return true;
        }

        return $venta->user_id === $user->id;
    }
}

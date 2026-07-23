<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;

/**
 * Policy para Pedido (Pedidos Recibidos).
 *
 * Reglas de negocio (agnóstico de roles):
 *  - Las acciones de capacidad se resuelven con permisos Spatie (comercial.oportunidades.*).
 *  - "Actualizar estado / Generar venta" se resuelve por permiso global O ser el dueño (pedido.user_id).
 *  - Master / Super Admin se resuelven vía Gate::before en AppServiceProvider (no se pregunta por el rol aquí).
 */
class PedidoRecibidoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('comercial.oportunidades.viewAny');
    }

    public function view(User $user, Pedido $pedido): bool
    {
        if ($user->can('comercial.oportunidades.viewAny')) {
            return true;
        }

        return $pedido->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('comercial.oportunidades.create');
    }

    /**
     * Actualizar estado: permiso global de edición O ser el dueño del pedido.
     */
    public function update(User $user, Pedido $pedido): bool
    {
        if ($user->can('comercial.oportunidades.edit')) {
            return true;
        }

        return $pedido->user_id === $user->id;
    }

    /**
     * Eliminar: permiso global de eliminación O ser el dueño del pedido.
     */
    public function delete(User $user, Pedido $pedido): bool
    {
        if ($user->can('comercial.oportunidades.delete')) {
            return true;
        }

        return $pedido->user_id === $user->id;
    }

    /**
     * Generar venta: requiere permiso de crear ventas Y ser el dueño del pedido.
     */
    public function generarVenta(User $user, Pedido $pedido): bool
    {
        if (! $user->can('ventas.ventas.create')) {
            return false;
        }

        return $pedido->user_id === $user->id;
    }
}

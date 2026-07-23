<?php

namespace App\Traits\Scopes;

use Illuminate\Database\Eloquent\Builder;

trait WarehouseScope
{
    public function scopeAccesible(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        $level = $user->highestRoleLevel();

        return match ($level) {
            0, 1 => $query, // Master y Super Admin ven todo
            2 => $query,    // Administrador ve todo pero no puede eliminar movimientos
            3 => $query->whereHas('empleados', function ($q) use ($user) {
                $q->where('almacen_id', $user->empleado?->almacen_id);
            }),
            default => $query,
        };
    }

    public function scopeByUserWarehouse(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user || $user->highestRoleLevel() > 2) {
            return $query;
        }

        $almacenId = $user->empleado?->almacen_id;

        if (! $almacenId) {
            return $query;
        }

        return $query->where('almacen_id', $almacenId);
    }

    public static function puedeEliminarMovimiento(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $level = $user->highestRoleLevel();

        return $level <= 2;
    }

    public static function puedeCrearTraslado(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $level = $user->highestRoleLevel();

        return $level <= 2;
    }

    public static function puedeRegistrarMovimiento(string $type): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $level = $user->highestRoleLevel();

        return match ($level) {
            0, 1, 2 => true,  // Todos pueden registrar
            3 => in_array($type, ['INGRESO', 'EGRESO']), // Empleado solo INGRESO/EGRESO, no TRASLADO
            default => false,
        };
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conductor extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'conductores';

    protected $fillable = ['owner_id', 'empleado_id', 'nombre', 'rut', 'licencia', 'fecha_vencimiento_licencia', 'telefono', 'email', 'estado', 'notas', 'lat', 'lng', 'ultima_actualizacion'];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento_licencia' => 'datetime',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function cargasDiarias(): HasMany
    {
        return $this->hasMany(CargaDiaria::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }

    public function getEstadisticasVentasAttribute()
    {
        $hoy = now()->toDateString();
        $mesActual = now()->format('Y-m');

        $stats = [
            'diario' => ['productos' => 0, 'kilos' => 0, 'litros' => 0],
            'mensual' => ['productos' => 0, 'kilos' => 0, 'litros' => 0],
        ];

        // 1. Calcular de Entregas (Pedidos asignados que han sido pagados)
        $entregas = Entrega::where(function ($q) {
            $q->where('conductor_id', $this->id)
                ->orWhereHas('grupoTrabajo.conductores', fn ($q) => $q->where('conductor_id', $this->id));
        })
            ->whereHas('venta', fn ($q) => $q->where('estado', 'pagada'))
            ->where(function ($q) use ($hoy, $mesActual) {
                $q->whereDate('updated_at', $hoy)
                    ->orWhere('updated_at', 'like', $mesActual.'%');
            })
            ->with(['venta.detalleVentas.producto'])
            ->get();

        foreach ($entregas as $entrega) {
            $isHoy = substr($entrega->updated_at, 0, 10) === $hoy;
            $isMes = substr($entrega->updated_at, 0, 7) === $mesActual;

            if ($entrega->venta && $entrega->venta->detalleVentas) {
                foreach ($entrega->venta->detalleVentas as $detalle) {
                    $producto = $detalle->producto;
                    $cantidad = $detalle->cantidad;

                    if ($producto) {
                        $periodods = [];
                        if ($isHoy) {
                            $periodods[] = 'diario';
                        }
                        if ($isMes) {
                            $periodods[] = 'mensual';
                        }

                        foreach ($periodods as $p) {
                            $stats[$p]['productos'] += $cantidad;
                            if ($producto->tipo_medida === 'kilo' || strtolower($producto->tipo_medida) === 'kilos') {
                                $stats[$p]['kilos'] += $cantidad * ($producto->cantidad_medida ?? 1);
                            } elseif ($producto->tipo_medida === 'litro' || strtolower($producto->tipo_medida) === 'litros') {
                                $stats[$p]['litros'] += $cantidad * ($producto->cantidad_medida ?? 1);
                            }
                        }
                    }
                }
            }
        }

        // 2. Calcular de Cargas Diarias (Autoventas)
        $cargas = CargaDiaria::where('conductor_id', $this->id)
            ->where('estado', 'cerrado')
            ->where(function ($q) use ($hoy, $mesActual) {
                $q->whereDate('fecha', $hoy)
                    ->orWhere('fecha', 'like', $mesActual.'%');
            })
            ->with(['productos.producto'])
            ->get();

        foreach ($cargas as $carga) {
            $isHoy = substr($carga->fecha, 0, 10) === $hoy;
            $isMes = substr($carga->fecha, 0, 7) === $mesActual;

            foreach ($carga->productos as $cargaProd) {
                $producto = $cargaProd->producto;
                $cantidad = $cargaProd->cantidad_vendida; // Asumiendo que se llena al cerrar

                if ($producto && $cantidad > 0) {
                    $periodods = [];
                    if ($isHoy) {
                        $periodods[] = 'diario';
                    }
                    if ($isMes) {
                        $periodods[] = 'mensual';
                    }

                    foreach ($periodods as $p) {
                        $stats[$p]['productos'] += $cantidad;
                        if ($producto->tipo_medida === 'kilo' || strtolower($producto->tipo_medida) === 'kilos') {
                            $stats[$p]['kilos'] += $cantidad * ($producto->cantidad_medida ?? 1);
                        } elseif ($producto->tipo_medida === 'litro' || strtolower($producto->tipo_medida) === 'litros') {
                            $stats[$p]['litros'] += $cantidad * ($producto->cantidad_medida ?? 1);
                        }
                    }
                }
            }
        }

        return $stats;
    }
}

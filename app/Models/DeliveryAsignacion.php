<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Database\Factories\DeliveryAsignacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAsignacion extends Model
{
    /** @use HasFactory<DeliveryAsignacionFactory> */
    use BelongsToOwner, HasFactory;

    protected $table = 'delivery_asignaciones';

    protected $fillable = [
        'owner_id',
        'pedido_id',
        'repartidor_id',
        'zona_id',
        'asignador_id',
        'motivo',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function asignador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignador_id');
    }

    /**
     * Registra una asignacion (auto, manual o aceptada) en el historico.
     */
    public static function registrar(
        Pedido $pedido,
        int $repartidorUserId,
        ?Zone $zona = null,
        ?int $asignadorId = null,
        string $motivo = 'auto_asignado',
    ): self {
        return static::create([
            'owner_id' => $pedido->owner_id,
            'pedido_id' => $pedido->id,
            'repartidor_id' => $repartidorUserId,
            'zona_id' => $zona?->id,
            'asignador_id' => $asignadorId,
            'motivo' => $motivo,
        ]);
    }
}

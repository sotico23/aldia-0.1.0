<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversacion extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo (opcional si sigue la convención conversations/conversaciones)
     */
    protected $table = 'conversaciones';

    /**
     * Atributos asignables masivamente.
     *
     * Deben coincidir con las columnas reales de la tabla `conversaciones`
     * (ver database/migrations/2026_03_16_234952_create_conversaciones_table.php
     * y 2026_06_28_041156_add_owner_id_to_chat_tables.php).
     */
    protected $fillable = [
        'owner_id',
        'pedido_id',
        'public_profile_id',
        'comprador_id',
        'vendedor_id',
        'titulo',
        'ultimo_mensaje_at',
    ];

    /**
     * Conversión de tipos de atributos.
     */
    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'pedido_id' => 'integer',
            'public_profile_id' => 'integer',
            'comprador_id' => 'integer',
            'vendedor_id' => 'integer',
            'ultimo_mensaje_at' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function publicProfile(): BelongsTo
    {
        return $this->belongsTo(PublicProfile::class);
    }

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comprador_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeConversacion::class, 'conversacion_id');
    }

    /**
     * Helper para formatear datos comunes en respuestas/JSON
     */
    public function getCommonFields(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'data' => [
                'id' => $this->id,
                'titulo' => $this->titulo,
                'ultimo_mensaje_at' => $this->ultimo_mensaje_at,
            ],
        ];
    }
}

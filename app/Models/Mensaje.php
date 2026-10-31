<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Mensaje extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo en la base de datos.
     */
    protected $table = 'mensajes';

    /**
     * Valores por defecto que reflejan los defaults de la tabla.
     */
    protected $attributes = [
        'leido' => false,
    ];

    /**
     * Atributos asignables masivamente.
     *
     * Deben coincidir con las columnas reales de la tabla `mensajes`
     * (ver database/migrations/2026_03_15_043626_create_mensajes_table.php,
     * 2026_03_15_131411_add_attachments_to_mensajes_table.php y
     * 2026_03_29_011748_add_owner_id_to_remaining_operational_tables.php).
     */
    protected $fillable = [
        'owner_id',
        'sender_id',
        'receiver_id',
        'contenido',
        'archivo_path',
        'archivo_nombre',
        'archivo_tipo',
        'leido',
    ];

    /**
     * Conversión automática de tipos de atributos.
     */
    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'sender_id' => 'integer',
            'receiver_id' => 'integer',
            'leido' => 'boolean',
        ];
    }

    /**
     * Asigna el tenant del usuario autenticado al crear el mensaje.
     */
    protected static function booted(): void
    {
        static::creating(function (Mensaje $mensaje) {
            if (Auth::check() && ! $mensaje->owner_id) {
                $mensaje->owner_id = Auth::user()->getOwnerId();
            }
        });
    }

    /**
     * Tenant al que pertenece el mensaje.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Usuario que envía el mensaje.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Usuario que recibe el mensaje.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Helper para formatear datos comunes en respuestas o recursos.
     */
    public function getCommonFields(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'data' => [
                'id' => $this->id,
                'sender_id' => $this->sender_id,
                'receiver_id' => $this->receiver_id,
                'contenido' => $this->contenido,
                'archivo_path' => $this->archivo_path,
                'archivo_nombre' => $this->archivo_nombre,
                'archivo_tipo' => $this->archivo_tipo,
                'leido' => $this->leido,
            ],
        ];
    }
}

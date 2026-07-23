<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailConfigNotification extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'mail_config_notifications';

    protected $fillable = [
        'mail_config_id',
        'owner_id',
        'tipo',
        'mensaje',
        'destinatario',
        'leido',
    ];

    protected function casts(): array
    {
        return [
            'leido' => 'boolean',
        ];
    }

    public function mailConfig(): BelongsTo
    {
        return $this->belongsTo(MailConfig::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('leido', false);
    }

    public function getTipoLabel(): string
    {
        return match ($this->tipo) {
            'error_envio' => 'Error de Envío',
            'fallo_conexion' => 'Fallo de Conexión',
            'limite_uso' => 'Límite de Uso',
            default => 'Notificación',
        };
    }

    public function getTipoIcon(): string
    {
        return match ($this->tipo) {
            'error_envio' => '❌',
            'fallo_conexion' => '🔌',
            'limite_uso' => '⚠️',
            default => '🔔',
        };
    }
}

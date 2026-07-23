<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailTemplate extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'type',
        'slug',
        'name',
        'subject',
        'content',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function getAvailableSlugs(): array
    {
        return [
            'bienvenida' => 'Email de Bienvenida',
            'factura' => 'Email de Factura',
            'olvido-contrasena' => 'Recuperar Contraseña',
            'cotizacion' => 'Envío de Cotización',
            'pedido-confirmado' => 'Pedido Confirmado',
            'cuenta-activada' => 'Cuenta Activada',
            'nuevo_pedido' => 'Nuevo Pedido (vendedor)',
            'pedido_creado' => 'Pedido Creado (comprador)',
            'actualizacion_pedido' => 'Actualización de Pedido',
            'mensaje_chat' => 'Mensaje de Chat de Pedido',
            'nuevo_ticket' => 'Nuevo Ticket de Soporte',
            'pago_recibido' => 'Pago Recibido',
            'welcome_proveedor' => 'Bienvenida Proveedor',
            'temp_password' => 'Contraseña Temporal',
        ];
    }
}

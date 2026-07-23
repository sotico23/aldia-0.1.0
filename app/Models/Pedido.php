<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Scopes\OwnerScope;
use App\Traits\BelongsToOwner;
use App\Traits\LogsPaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Pedido extends Model
{
    use BelongsToOwner;
    use HasFactory;
    use LogsPaymentStatus;

    protected $fillable = [
        'owner_id',
        'business_id',
        'user_id',
        'public_profile_id',
        'cliente_id',
        'numero_pedido',
        'estado',
        'subtotal',
        'impuesto',
        'total',
        'notas',
        'nombre_cliente',
        'telefono_cliente',
        'direccion_cliente',
        'metodo_pago',
        'fecha_confirmacion',
        'fecha_entrega',
        'payment_id',
        'payment_status',
        'payment_data',
        'erp_synced_at',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'string',
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha_confirmacion' => 'datetime',
            'fecha_entrega' => 'datetime',
            'erp_synced_at' => 'datetime',
            'payment_data' => 'array',
        ];
    }

    public static function generarNumeroPedido(): string
    {
        do {
            $numero = 'PED-'.date('Ymd').'-'.Str::random(5);
        } while (self::withoutGlobalScope(OwnerScope::class)->where('numero_pedido', $numero)->exists());

        return strtoupper($numero);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicProfile(): BelongsTo
    {
        return $this->belongsTo(PublicProfile::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function conversacion(): HasOne
    {
        return $this->hasOne(Conversacion::class);
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleAsiento extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $table = 'detalle_asientos';

    protected $fillable = [
        'owner_id',
        'asiento_id',
        'cuenta',
        'cuenta_codigo',
        'descripcion',
        'debe',
        'haber',
    ];

    protected function casts(): array
    {
        return [
            'debe' => 'decimal:2',
            'haber' => 'decimal:2',
        ];
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class);
    }
}

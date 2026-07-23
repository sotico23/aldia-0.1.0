<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cobranza extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'cobranzas';

    protected $fillable = ['owner_id', 'factura_id', 'cliente_id', 'monto', 'fecha_pago', 'metodo_pago', 'referencia', 'estado', 'notas'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'fecha_pago' => 'date'];
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'pagos';

    protected $fillable = ['owner_id', 'factura_id', 'proveedor_id', 'monto', 'fecha_pago', 'metodo_pago', 'referencia', 'estado', 'notas'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'fecha_pago' => 'date'];
    }
}

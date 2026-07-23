<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bom extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'boms';

    protected $fillable = [
        'owner_id',
        'nombre',
        'producto_final_id',
        'producto_final',
        'cantidad',
        'materiales',
        'activo',
        'notas',
        'tipo',
    ];

    protected function casts(): array
    {
        return [
            'materiales' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function productoFinal(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_final_id');
    }
}

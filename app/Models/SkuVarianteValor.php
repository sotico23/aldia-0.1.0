<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkuVarianteValor extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'sku_variante_valores';

    protected $fillable = [
        'sku_variante_id',
        'variante_valor_id',
    ];

    public function skuVariante(): BelongsTo
    {
        return $this->belongsTo(SkuVariante::class, 'sku_variante_id');
    }

    public function varianteValor(): BelongsTo
    {
        return $this->belongsTo(VarianteValor::class, 'variante_valor_id');
    }
}

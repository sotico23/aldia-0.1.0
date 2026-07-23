<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VarianteValor extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'variante_valores';

    protected $fillable = [
        'variante_id',
        'valor',
        'codigo',
    ];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }
}

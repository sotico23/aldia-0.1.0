<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProveedorDocumento extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $fillable = [
        'proveedor_id',
        'owner_id',
        'titulo',
        'archivo',
        'descripcion',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->archivo);
    }
}

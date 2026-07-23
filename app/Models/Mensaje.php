<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensaje extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'conversacion_id',
        'user_id',
        'owner_id',
        'contenido',
        'leido',
    ];

    protected function casts(): array
    {
        return [
            'leido' => 'boolean',
        ];
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

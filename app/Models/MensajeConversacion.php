<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class MensajeConversacion extends Model
{
    use HasFactory;

    protected $table = 'mensajes_conversacion';

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && ! $model->owner_id) {
                $model->owner_id = Auth::user()->getOwnerId();
            }
        });
    }

    protected $fillable = [
        'owner_id',
        'conversacion_id',
        'sender_id',
        'receiver_id',
        'contenido',
        'file_path',
        'leido',
    ];

    protected function casts(): array
    {
        return [
            'leido' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}

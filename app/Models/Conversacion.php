<?php

namespace App\Models;

use App\Contracts\HasMessageThread;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Conversacion extends Model implements HasMessageThread
{
    use HasFactory;

    protected $table = 'conversaciones';

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
        'pedido_id',
        'public_profile_id',
        'comprador_id',
        'vendedor_id',
        'titulo',
        'ultimo_mensaje_at',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_mensaje_at' => 'datetime',
        ];
    }

    public function type(): string
    {
        return 'order';
    }

    public function participants(): array
    {
        return array_filter([$this->comprador, $this->vendedor]);
    }

    public function otherParticipant(User $user): ?User
    {
        if ($this->comprador_id === $user->id) {
            return $this->vendedor;
        }

        if ($this->vendedor_id === $user->id) {
            return $this->comprador;
        }

        return null;
    }

    public function markAsRead(User $user): int
    {
        return $this->mensajes()
            ->where('receiver_id', $user->id)
            ->where('leido', false)
            ->update(['leido' => true]);
    }

    public function unreadCount(User $user): int
    {
        return $this->mensajes()
            ->where('receiver_id', $user->id)
            ->where('leido', false)
            ->count();
    }

    public function latestMessageContent(): ?string
    {
        $last = $this->ultimoMensaje;

        return $last?->contenido;
    }

    public function lastMessageAt(): mixed
    {
        return $this->ultimo_mensaje_at ?? $this->ultimoMensaje?->created_at;
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function publicProfile(): BelongsTo
    {
        return $this->belongsTo(PublicProfile::class);
    }

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comprador_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeConversacion::class)->orderBy('created_at', 'asc');
    }

    public function ultimoMensaje(): HasOne
    {
        return $this->hasOne(MensajeConversacion::class)->latestOfMany();
    }
}

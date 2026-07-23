<?php

namespace App\Models;

use App\Contracts\HasMessageThread;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationConversation extends Model implements HasMessageThread
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'metadata',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'communication_conversation_id');
    }

    public function type(): string
    {
        return $this->type;
    }

    public function participants(): array
    {
        $meta = $this->metadata ?? [];

        return match ($this->type) {
            'order' => array_filter([
                isset($meta['comprador_id']) ? User::find($meta['comprador_id']) : null,
                isset($meta['vendedor_id']) ? User::find($meta['vendedor_id']) : null,
            ]),
            'marketplace' => array_filter([
                isset($meta['buyer_id']) ? User::find($meta['buyer_id']) : null,
                isset($meta['store_user_id']) ? User::find($meta['store_user_id']) : null,
            ]),
            'internal' => array_filter([
                isset($meta['user_a_id']) ? User::find($meta['user_a_id']) : null,
                isset($meta['user_b_id']) ? User::find($meta['user_b_id']) : null,
            ]),
            default => [],
        };
    }

    public function otherParticipant(User $user): ?User
    {
        $meta = $this->metadata ?? [];

        $participants = match ($this->type) {
            'order' => [$meta['comprador_id'] ?? null, $meta['vendedor_id'] ?? null],
            'marketplace' => [$meta['buyer_id'] ?? null, $meta['store_user_id'] ?? null],
            'internal' => [$meta['user_a_id'] ?? null, $meta['user_b_id'] ?? null],
            default => [],
        };

        foreach ($participants as $pid) {
            if ($pid && (int) $pid !== $user->id) {
                return User::find($pid);
            }
        }

        return null;
    }

    public function markAsRead(User $user): int
    {
        return $this->messages()
            ->where('receiver_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadCount(User $user): int
    {
        return $this->messages()
            ->where('receiver_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function latestMessageContent(): ?string
    {
        return $this->messages()->latest()->first()?->content;
    }

    public function lastMessageAt(): mixed
    {
        return $this->last_message_at ?? $this->messages()->latest()->first()?->created_at;
    }
}

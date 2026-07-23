<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Raffle extends Model
{
    use BelongsToOwner;
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'user_id',
        'title',
        'description',
        'slug',
        'image_url',
        'cover_image',
        'type',
        'status',
        'max_participants',
        'min_participants',
        'ticket_price',
        'allow_multiple_entries',
        'max_entries_per_user',
        'start_date',
        'end_date',
        'show_winners',
        'background_color',
        'text_color',
        'custom_settings',
    ];

    public function casts(): array
    {
        return [
            'allow_multiple_entries' => 'boolean',
            'show_winners' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'custom_settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(RafflePrize::class)->orderBy('position');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(RaffleParticipant::class);
    }

    public function winners(): HasMany
    {
        return $this->hasMany(RaffleParticipant::class)->where('is_winner', true);
    }

    public function getParticipantCount(): int
    {
        return $this->participants()->count();
    }

    public function getUniqueParticipantCount(): int
    {
        return $this->participants()->distinct('email')->count();
    }

    public function isOpen(): bool
    {
        return $this->status === 'active' && now()->between($this->start_date, $this->end_date);
    }

    public function canParticipate(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->max_participants && $this->getParticipantCount() >= $this->max_participants) {
            return false;
        }

        return true;
    }

    public function drawWinners(): array
    {
        $participants = $this->participants()->where('is_winner', false)->get();
        $prizes = $this->prizes()->where('status', 'pending')->orderBy('position')->get();

        if ($participants->isEmpty() || $prizes->isEmpty()) {
            return [];
        }

        $winners = [];
        $shuffledParticipants = $participants->shuffle();

        foreach ($prizes as $prize) {
            for ($i = 0; $i < $prize->quantity; $i++) {
                $winner = $shuffledParticipants->random();

                if ($winner && ! $winner->is_winner) {
                    $winner->update([
                        'is_winner' => true,
                        'prize_id' => $prize->id,
                        'won_at' => now(),
                    ]);

                    $prize->update(['status' => 'awarded']);

                    $winners[] = [
                        'participant' => $winner,
                        'prize' => $prize,
                    ];
                }
            }
        }

        return $winners;
    }

    public function getCoverImageAttribute($value): ?string
    {
        return $value ? asset('storage/'.$value) : null;
    }
}

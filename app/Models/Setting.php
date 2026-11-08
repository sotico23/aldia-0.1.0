<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Decrypt the value on read and encrypt it on write when the row is
     * flagged as sensitive. A broken APP_KEY returns null instead of
     * crashing the whole request.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '' || ! $this->is_encrypted) {
                    return $value;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return null;
                }
            },
            set: function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                return $this->is_encrypted
                    ? Crypt::encryptString($value)
                    : $value;
            },
        );
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'currency_code',
        'currency_symbol',
        'currency_decimals',
        'locale',
        'timezone',
        'phone_code',
        'tax_name',
        'tax_rate',
        'fiscal_id_label',
        'fiscal_id_pattern',
        'date_format',
        'is_active',
    ];

    protected $casts = [
        'currency_decimals' => 'integer',
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'country', 'code');
    }

    public static function findByCode(?string $code): ?self
    {
        if ($code === null) {
            return null;
        }

        return static::where('code', strtoupper($code))->first();
    }

    public static function getActive(): Collection
    {
        return static::where('is_active', true)->orderBy('name')->get();
    }

    public static function getDefault(): ?self
    {
        return static::findByCode('CL') ?? static::first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteFrecuente extends Model
{
    use HasFactory;

    protected $table = 'clientes_frecuentes';

    protected $fillable = [
        'wallet_id',
        'nombre',
        'account_number',
        'nota',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'reactable_id', 'reactable_type'];

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}

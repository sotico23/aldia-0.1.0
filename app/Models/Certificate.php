<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'user_id',
        'course_id',
        'certificate_number',
        'issue_date',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'issue_date' => 'date',
        ];
    }
}

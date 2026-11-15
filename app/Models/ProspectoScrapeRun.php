<?php

namespace App\Models;

use Database\Factories\ProspectoScrapeRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProspectoScrapeRun extends Model
{
    /** @use HasFactory<ProspectoScrapeRunFactory> */
    use HasFactory;

    protected $fillable = ['owner_id', 'user_id', 'query', 'location', 'max_results', 'status', 'results', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['results' => 'array', 'max_results' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}

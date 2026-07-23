<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CafFolio extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'tipo_documento',
        'folio_desde',
        'folio_hasta',
        'folio_siguiente',
        'xml_caf',
        'fecha_vencimiento',
        'ambiente',
        'agotado',
    ];

    protected function casts(): array
    {
        return [
            'tipo_documento' => 'integer',
            'folio_desde' => 'integer',
            'folio_hasta' => 'integer',
            'folio_siguiente' => 'integer',
            'fecha_vencimiento' => 'date',
            'agotado' => 'boolean',
        ];
    }

    /** Devuelve true si aún hay folios disponibles. */
    public function tieneDisponibles(): bool
    {
        return ! $this->agotado && $this->folio_siguiente <= $this->folio_hasta;
    }

    /** Reserva y retorna el próximo folio, o null si está agotado. */
    public function reservarFolio(): ?int
    {
        if (! $this->tieneDisponibles()) {
            return null;
        }

        $folio = $this->folio_siguiente;
        $this->folio_siguiente += 1;

        if ($this->folio_siguiente > $this->folio_hasta) {
            $this->agotado = true;
        }

        $this->save();

        return $folio;
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DteDocumento::class);
    }

    public function scopeVigente($query)
    {
        return $query->where('agotado', false)
            ->where(function ($q) {
                $q->whereNull('fecha_vencimiento')
                    ->orWhere('fecha_vencimiento', '>=', now()->toDateString());
            });
    }
}

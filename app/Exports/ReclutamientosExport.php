<?php

namespace App\Exports;

use App\Models\Reclutamiento;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReclutamientosExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = Reclutamiento::where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where('puesto', 'like', "%{$search}%");
        }

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        return $query->latest()->get();
    }

    public function headings(): array
    {
        return ['ID', 'Puesto', 'Candidato_ID', 'Fecha_Postulacion', 'Fecha_Entrevista', 'Estado', 'Resultado', 'Notas'];
    }

    public function map($r): array
    {
        return [
            $r->id,
            $r->puesto,
            $r->candidato_id,
            $r->fecha_postulacion,
            $r->fecha_entrevista,
            $r->estado,
            $r->resultado,
            $r->notas,
        ];
    }
}

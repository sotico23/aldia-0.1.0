<?php

namespace App\Exports;

use App\Models\Nomina;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class NominasExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = Nomina::where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['search'])) {
            $query->where('periodo', 'like', '%'.$this->filters['search'].'%');
        }

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        return $query->latest()->get();
    }

    public function headings(): array
    {
        return ['ID', 'Periodo', 'Fecha_Inicio', 'Fecha_Fin', 'Total_Bruto', 'Total_Deducciones', 'Total_Neto', 'Estado', 'Notas'];
    }

    public function map($n): array
    {
        return [
            $n->id,
            $n->periodo,
            $n->fecha_inicio,
            $n->fecha_fin,
            $n->total_bruto,
            $n->total_deducciones,
            $n->total_neto,
            $n->estado,
            $n->notas,
        ];
    }
}

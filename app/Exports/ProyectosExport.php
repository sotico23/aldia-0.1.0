<?php

namespace App\Exports;

use App\Models\Proyecto;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProyectosExport implements FromCollection, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Proyecto::orderBy('created_at', 'desc');
        if (! empty($this->filters['estado'])) {
            $query->where('estado', $this->filters['estado']);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return ['Nombre', 'Descripción', 'Cliente', 'Responsable', 'Fecha Inicio', 'Fecha Fin', 'Presupuesto', 'Gasto Real', 'Progreso', 'Estado'];
    }

    public function map($proyecto): array
    {
        return [
            $proyecto->nombre,
            $proyecto->descripcion,
            $proyecto->cliente,
            $proyecto->responsable,
            $proyecto->fecha_inicio,
            $proyecto->fecha_fin,
            $proyecto->presupuesto,
            $proyecto->gasto_real,
            $proyecto->progreso,
            $proyecto->estado,
        ];
    }
}

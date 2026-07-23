<?php

namespace App\Exports;

use App\Models\Planificacion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PlanificacionExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Planificacion::orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Título',
            'Descripción',
            'Fecha Inicio',
            'Fecha Fin',
            'Estado',
            'Prioridad',
            'Ubicación',
            'Presupuesto',
            'Categoría',
            'Objetivo',
        ];
    }

    public function map($plan): array
    {
        return [
            $plan->titulo,
            $plan->descripcion,
            $plan->fecha_inicio?->format('Y-m-d'),
            $plan->fecha_fin?->format('Y-m-d'),
            $plan->estado,
            $plan->prioridad,
            $plan->ubicacion,
            $plan->presupuesto,
            $plan->categoria,
            $plan->objetivo,
        ];
    }
}

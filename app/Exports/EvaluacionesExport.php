<?php

namespace App\Exports;

use App\Models\Evaluacion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EvaluacionesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = Evaluacion::where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        if (! empty($this->filters['tipo']) && $this->filters['tipo'] !== 'all') {
            $query->where('tipo', $this->filters['tipo']);
        }

        return $query->latest()->get();
    }

    public function headings(): array
    {
        return ['ID', 'Empleado_ID', 'Evaluador_ID', 'Fecha', 'Periodo', 'Puntuacion', 'Tipo', 'Estado', 'Comentarios'];
    }

    public function map($e): array
    {
        return [
            $e->id,
            $e->empleado_id,
            $e->evaluador_id,
            $e->fecha,
            $e->periodo,
            $e->puntuacion,
            $e->tipo,
            $e->estado,
            $e->comentarios,
        ];
    }
}

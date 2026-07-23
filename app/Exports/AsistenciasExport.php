<?php

namespace App\Exports;

use App\Models\Asistencia;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AsistenciasExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = Asistencia::with('empleado')
            ->where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        return $query->latest('fecha')->get();
    }

    public function headings(): array
    {
        return ['ID', 'Empleado_ID', 'Empleado', 'Fecha', 'Hora_Entrada', 'Hora_Salida', 'Horas_Trabajadas', 'Estado', 'Notas'];
    }

    public function map($a): array
    {
        return [
            $a->id,
            $a->empleado_id,
            $a->empleado ? "{$a->empleado->nombre} {$a->empleado->apellido}" : '',
            $a->fecha,
            $a->hora_entrada,
            $a->hora_salida,
            $a->horas_trabajadas,
            $a->estado,
            $a->notas,
        ];
    }
}

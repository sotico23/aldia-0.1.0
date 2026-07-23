<?php

namespace App\Exports;

use App\Models\Hito;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HitosExport implements FromCollection, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        return Hito::orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return ['Nombre', 'Descripción', 'Fecha Prevista', 'Fecha Real', 'Estado'];
    }

    public function map($hito): array
    {
        return [
            $hito->nombre,
            $hito->descripcion,
            $hito->fecha_prevista,
            $hito->fecha_real,
            $hito->estado,
        ];
    }
}

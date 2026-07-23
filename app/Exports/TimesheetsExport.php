<?php

namespace App\Exports;

use App\Models\Timesheet;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TimesheetsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        return Timesheet::orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return ['Fecha', 'Horas', 'Descripción', 'Tipo', 'Estado'];
    }

    public function map($timesheet): array
    {
        return [
            $timesheet->fecha,
            $timesheet->horas,
            $timesheet->descripcion,
            $timesheet->tipo,
            $timesheet->estado,
        ];
    }
}

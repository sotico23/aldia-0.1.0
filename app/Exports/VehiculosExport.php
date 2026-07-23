<?php

namespace App\Exports;

use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VehiculosExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    protected $query;

    public function __construct($query = null)
    {
        $this->query = $query;
    }

    public function query(): Builder
    {
        return $this->query ?? Vehiculo::query();
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Placa',
            'IMEI',
            'Marca',
            'Modelo',
            'Tipo',
            'Ano',
            'Color',
            'Estado',
            'Kilometraje',
            'Notas',
        ];
    }

    public function map($v): array
    {
        return [
            $v->id,
            $v->placa,
            $v->imei,
            $v->marca,
            $v->modelo,
            $v->tipo,
            $v->año ?? $v->ano ?? null,
            $v->color,
            $v->estado,
            $v->kilometraje,
            $v->notas,
        ];
    }
}

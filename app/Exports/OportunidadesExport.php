<?php

namespace App\Exports;

use App\Models\Oportunidad;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OportunidadesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = Oportunidad::with('cliente')->where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nombre', 'like', "%{$search}%");
                    })
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['cliente_id']) && $this->filters['cliente_id'] !== 'all') {
            $query->where('cliente_id', $this->filters['cliente_id']);
        }

        if (! empty($this->filters['etapa']) && $this->filters['etapa'] !== 'all') {
            $query->where('etapa', $this->filters['etapa']);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Cliente',
            'Telefono',
            'Email',
            'Valor',
            'Etapa',
            'Probabilidad',
            'Fecha_Cierre_Estimada',
            'Descripcion',
        ];
    }

    public function map($oportunidad): array
    {
        return [
            $oportunidad->nombre,
            $oportunidad->cliente?->nombre ?? '',
            $oportunidad->cliente?->telefono ?? '',
            $oportunidad->cliente?->email ?? '',
            $oportunidad->valor,
            $oportunidad->etapa,
            $oportunidad->probabilidad,
            $oportunidad->fecha_cierre_estimada ? $oportunidad->fecha_cierre_estimada->format('Y-m-d') : '',
            $oportunidad->descripcion,
        ];
    }
}

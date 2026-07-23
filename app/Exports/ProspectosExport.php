<?php

namespace App\Exports;

use App\Models\Prospecto;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProspectosExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = Prospecto::where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('empresa', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        if (! empty($this->filters['fuente']) && $this->filters['fuente'] !== 'all') {
            $query->where('fuente', $this->filters['fuente']);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'RUT',
            'Email',
            'Telefono',
            'Empresa',
            'Cargo',
            'Estado',
            'Prioridad',
            'Valor_Estimado',
            'Fuente',
        ];
    }

    public function map($p): array
    {
        return [
            $p->id,
            $p->nombre,
            $p->rut,
            $p->email,
            $p->telefono,
            $p->empresa,
            $p->cargo,
            $p->estado,
            $p->prioridad,
            $p->valor_estimado,
            $p->fuente,
        ];
    }
}

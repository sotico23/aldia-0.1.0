<?php

namespace App\Exports;

use App\Models\Campana;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CampanasExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = Campana::where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhere('canal', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Descripción',
            'Tipo',
            'Canal',
            'Objetivo',
            'Fecha_Inicio',
            'Fecha_Fin',
            'Presupuesto',
            'Presupuesto_Real',
            'Visitas',
            'Leads',
            'Conversiones',
            'ROI',
            'Estado',
        ];
    }

    public function map($campana): array
    {
        return [
            $campana->nombre,
            $campana->descripcion,
            $campana->tipo,
            $campana->canal,
            $campana->objetivo,
            $campana->fecha_inicio ? $campana->fecha_inicio->format('Y-m-d') : '',
            $campana->fecha_fin ? $campana->fecha_fin->format('Y-m-d') : '',
            $campana->presupuesto,
            $campana->presupuesto_real,
            $campana->visitas,
            $campana->leads,
            $campana->conversiones,
            $campana->roi,
            $campana->estado,
        ];
    }
}

<?php

namespace App\Exports;

use App\Models\LlamadaCallCenter;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LlamadasCallCenterExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = LlamadaCallCenter::with(['cliente', 'prospecto'])->where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('numero_telefono', 'like', "%{$search}%")
                    ->orWhereHas('cliente', fn ($q2) => $q2->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('prospecto', fn ($q3) => $q3->where('nombre', 'like', "%{$search}%"));
            });
        }

        if (! empty($this->filters['tipo']) && $this->filters['tipo'] !== 'all') {
            $query->where('tipo', $this->filters['tipo']);
        }

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        return $query->orderBy('fecha', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Tipo',
            'Numero Emisor',
            'Numero Remitente',
            'Numero Telefono',
            'Estado',
            'Duracion',
            'Fecha',
            'Notas',
            'Cliente',
            'Prospecto',
        ];
    }

    public function map($llamada): array
    {
        return [
            $llamada->tipo,
            $llamada->numero_emisor,
            $llamada->numero_remitente,
            $llamada->numero_telefono,
            $llamada->estado,
            $llamada->duracion,
            $llamada->fecha ? $llamada->fecha->format('Y-m-d H:i:s') : '',
            $llamada->notas,
            $llamada->cliente?->nombre ?? '',
            $llamada->prospecto?->nombre ?? '',
        ];
    }
}

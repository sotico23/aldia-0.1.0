<?php

namespace App\Exports;

use App\Models\Factura;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FacturasExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function query()
    {
        $ownerId = Auth::user()->getOwnerId();

        $query = Factura::with('cliente')->where('owner_id', $ownerId);

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('estado', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nombre', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        if (! empty($this->filters['tipo']) && $this->filters['tipo'] !== 'all') {
            $query->where('tipo', $this->filters['tipo']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Numero',
            'Cliente',
            'Fecha',
            'Vence',
            'Subtotal',
            'Impuesto',
            'Total',
            'Tipo',
            'Estado',
            'Notas',
            'Fecha Creacion',
        ];
    }

    public function map($factura): array
    {
        return [
            $factura->id,
            $factura->numero,
            $factura->cliente?->nombre ?? '',
            $factura->fecha ? $factura->fecha->format('Y-m-d') : '',
            $factura->fecha_vencimiento ? $factura->fecha_vencimiento->format('Y-m-d') : '',
            $factura->subtotal,
            $factura->impuesto,
            $factura->total,
            $factura->tipo,
            $factura->estado,
            $factura->notas,
            $factura->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}

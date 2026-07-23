<?php

namespace App\Exports;

use App\Models\Cotizacion;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CotizacionesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Cotizacion::with('cliente')->where('owner_id', $ownerId);

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('notas', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nombre', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($this->filters['cliente_id']) && $this->filters['cliente_id'] !== 'all') {
            $query->where('cliente_id', $this->filters['cliente_id']);
        }

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        if (! empty($this->filters['fechaDesde'])) {
            $query->where('fecha', '>=', Carbon::parse($this->filters['fechaDesde'])->startOfDay());
        }

        if (! empty($this->filters['fechaHasta'])) {
            $query->where('fecha', '<=', Carbon::parse($this->filters['fechaHasta'])->endOfDay());
        }

        return $query->orderBy('fecha', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Numero',
            'Fecha',
            'Vence',
            'Cliente Email',
            'Estado',
            'Subtotal',
            'Impuesto',
            'Total',
            'Notas',
        ];
    }

    public function map($cotizacion): array
    {
        return [
            $cotizacion->numero,
            $cotizacion->fecha ? $cotizacion->fecha->format('Y-m-d') : '',
            $cotizacion->fecha_validez ? $cotizacion->fecha_validez->format('Y-m-d') : '',
            $cotizacion->cliente->email ?? '',
            $cotizacion->estado,
            $cotizacion->subtotal,
            $cotizacion->impuesto,
            $cotizacion->total,
            $cotizacion->notas,
        ];
    }
}

<?php

namespace App\Exports;

use App\Models\Pago;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PagosExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function query()
    {
        $ownerId = Auth::user()->getOwnerId();

        $query = Pago::where('owner_id', $ownerId);

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('proveedor', 'like', "%{$search}%")
                    ->orWhere('factura', 'like', "%{$search}%")
                    ->orWhere('estado', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Proveedor',
            'Factura',
            'Monto',
            'Fecha Pago',
            'Metodo',
            'Estado',
            'Notas',
            'Fecha Creacion',
        ];
    }

    public function map($pago): array
    {
        return [
            $pago->id,
            $pago->proveedor,
            $pago->factura,
            $pago->monto,
            $pago->fecha_pago ? $pago->fecha_pago->format('Y-m-d') : '',
            $pago->metodo,
            $pago->estado,
            $pago->notas,
            $pago->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}

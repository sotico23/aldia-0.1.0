<?php

namespace App\Exports;

use App\Models\Cobranza;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CobranzasExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function query()
    {
        $ownerId = Auth::user()->getOwnerId();

        $query = Cobranza::where('owner_id', $ownerId);

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('cliente', 'like', "%{$search}%")
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
            'Cliente',
            'Factura',
            'Monto',
            'Fecha Pago',
            'Metodo',
            'Estado',
            'Notas',
            'Fecha Creacion',
        ];
    }

    public function map($cobranza): array
    {
        return [
            $cobranza->id,
            $cobranza->cliente,
            $cobranza->factura,
            $cobranza->monto,
            $cobranza->fecha_pago ? $cobranza->fecha_pago->format('Y-m-d') : '',
            $cobranza->metodo,
            $cobranza->estado,
            $cobranza->notas,
            $cobranza->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}

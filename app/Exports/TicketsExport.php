<?php

namespace App\Exports;

use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Ticket::with(['cliente', 'producto'])->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Titulo',
            'Descripcion',
            'Cliente',
            'Producto',
            'Prioridad',
            'Estado',
            'Categoria',
            'Asignado_a',
            'Created_at',
        ];
    }

    public function map($ticket): array
    {
        return [
            $ticket->titulo,
            $ticket->descripcion,
            $ticket->cliente?->nombre ?? '',
            $ticket->producto?->nombre ?? '',
            $ticket->prioridad,
            $ticket->estado,
            $ticket->categoria,
            $ticket->asignado_a,
            $ticket->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}

<?php

namespace App\Exports;

use App\Models\Raffle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RafflesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = Raffle::withCount('participants', 'prizes')
            ->where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        if (! empty($this->filters['type']) && $this->filters['type'] !== 'all') {
            $query->where('type', $this->filters['type']);
        }

        return $query->latest()->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Titulo',
            'Tipo',
            'Estado',
            'Participantes',
            'Premios',
            'Precio_Ticket',
            'Max_Participantes',
            'Fecha_Inicio',
            'Fecha_Fin',
        ];
    }

    public function map($r): array
    {
        return [
            $r->id,
            $r->title,
            $r->type,
            $r->status,
            $r->participants_count,
            $r->prizes_count,
            $r->ticket_price,
            $r->max_participants,
            $r->start_date,
            $r->end_date,
        ];
    }
}

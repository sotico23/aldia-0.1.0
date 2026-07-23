<?php

namespace App\Imports;

use App\Models\Raffle;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class RafflesImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): ?Raffle
    {
        $ownerId = auth()->user()->getOwnerId();

        return Raffle::updateOrCreate(
            [
                'title' => $row['titulo'],
                'owner_id' => $ownerId,
            ],
            [
                'owner_id' => $ownerId,
                'user_id' => auth()->id(),
                'title' => $row['titulo'],
                'slug' => Str::slug($row['titulo']).'-'.rand(1000, 9999),
                'type' => in_array($row['tipo'] ?? '', ['raffle', 'draw', 'competition']) ? $row['tipo'] : 'raffle',
                'status' => in_array($row['estado'] ?? '', ['draft', 'published', 'active', 'completed', 'cancelled']) ? $row['estado'] : 'draft',
                'ticket_price' => is_numeric($row['precio_ticket'] ?? null) ? $row['precio_ticket'] : 0,
                'max_participants' => is_numeric($row['max_participantes'] ?? null) ? $row['max_participantes'] : null,
                'start_date' => ! empty($row['fecha_inicio']) ? $row['fecha_inicio'] : null,
                'end_date' => ! empty($row['fecha_fin']) ? $row['fecha_fin'] : null,
                'show_winners' => true,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'titulo' => 'required|string',
        ];
    }
}

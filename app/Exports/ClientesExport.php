<?php

namespace App\Exports;

use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClientesExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function query()
    {
        $userId = Auth::user()->getOwnerId();

        $query = Cliente::with('categoria')
            ->where('owner_id', '=', $userId);

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('nit', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%")
                    ->orWhere('ciudad', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['categoria_id']) && $this->filters['categoria_id'] !== 'all') {
            $query->where('categoria_id', $this->filters['categoria_id']);
        }

        return $query->orderBy('nombre');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'NIT',
            'RUT',
            'Teléfono',
            'Email',
            'Dirección',
            'Ciudad',
            'Región',
            'Comuna',
            'Giro',
            'Contacto',
            'Teléfono Contacto',
            'Categoría',
            'Activo',
            'Notas',
        ];
    }

    public function map($cliente): array
    {
        return [
            $cliente->id,
            $cliente->nombre,
            $cliente->nit,
            $cliente->rut,
            $cliente->telefono,
            $cliente->email,
            $cliente->direccion,
            $cliente->ciudad,
            $cliente->region,
            $cliente->comuna,
            $cliente->giro,
            $cliente->contacto,
            $cliente->telefono_contacto,
            $cliente->categoria?->nombre,
            $cliente->activo ? 'Sí' : 'No',
            $cliente->notes ?? $cliente->notas,
        ];
    }
}

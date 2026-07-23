<?php

namespace App\Exports;

use App\Models\Categoria;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CategoriasExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Categoria::where('owner_id', $ownerId);

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where('nombre', 'like', "%{$search}%")
                ->orWhere('id', 'like', "%{$search}%");
        }

        if (! empty($this->filters['tipo'])) {
            $query->where('tipo', $this->filters['tipo']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Descripción',
            'Tipo',
            'Activo',
            'Mostrar en Perfil',
        ];
    }

    public function map($categoria): array
    {
        return [
            $categoria->id,
            $categoria->nombre,
            $categoria->descripcion,
            $categoria->tipo,
            $categoria->activo ? 'Sí' : 'No',
            $categoria->mostrar_en_perfil ? 'Sí' : 'No',
        ];
    }
}

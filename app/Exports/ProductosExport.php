<?php

namespace App\Exports;

use App\Models\Producto;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductosExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    /** @return Collection */
    public function collection()
    {
        $query = Producto::with(['categoria', 'inventarios', 'envaseProducto', 'padre'])
            ->where('owner_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['categoria_id']) && $this->filters['categoria_id'] !== 'all') {
            $query->where('categoria_id', $this->filters['categoria_id']);
        }

        if (! empty($this->filters['stock_bajo'])) {
            $query->whereHas('inventarios', function ($q) {
                $q->whereColumn('cantidad', '<=', 'cantidad_minima');
            });
        }

        return $query->orderBy('codigo')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Codigo',
            'Nombre',
            'Descripcion',
            'Precio Compra',
            'Precio Venta',
            'Stock',
            'Stock Minimo',
            'Unidad Medida',
            'Categoria',
            'Activo',
            'Medida Pesable',
            'Tipo Medida',
            'Cantidad Medida',
            'Peso Base',
            'Contenido Por Unidad',
            'Envase Retornable',
            'Envase Codigo',
            'Tipo Envase',
            'Is Service',
            'Duracion',
            'Requires Appointment',
            'Talla',
            'Color',
            'Tiene Variantes',
            'Parent Codigo',
            'Fecha Vencimiento',
            'Peso Por Unidad',
            'Mostrar En Perfil',
        ];
    }

    public function map($producto): array
    {
        return [
            $producto->id,
            $producto->codigo,
            $producto->nombre,
            $producto->descripcion,
            $producto->precio_compra,
            $producto->precio_venta,
            (float) ($producto->inventarios->sum('cantidad') ?? 0),
            $producto->stock_minimo,
            $producto->unidad_medida,
            $producto->categoria->nombre ?? '',
            $producto->activo ? 'Si' : 'No',
            $producto->medida_pesable ? 'Si' : 'No',
            $producto->tipo_medida,
            $producto->cantidad_medida,
            $producto->peso_base,
            $producto->contenido_por_unidad,
            $producto->envase_retornable ? 'Si' : 'No',
            $producto->envaseProducto?->codigo,
            $producto->tipo_envase,
            $producto->is_service ? 'Si' : 'No',
            $producto->duracion,
            $producto->requires_appointment ? 'Si' : 'No',
            $producto->talla,
            $producto->color,
            $producto->tiene_variantes ? 'Si' : 'No',
            $producto->padre?->codigo,
            $producto->fecha_vencimiento ? $producto->fecha_vencimiento->format('Y-m-d') : null,
            $producto->peso_por_unidad,
            $producto->mostrar_en_perfil ? 'Si' : 'No',
        ];
    }
}

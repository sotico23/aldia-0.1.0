<?php

namespace App\Exports;

use App\Models\Empleado;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmpleadosExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $query = Empleado::with('almacen')
            ->where('creator_id', auth()->user()->getOwnerId());

        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('apellido', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cargo', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('estado', $this->filters['estado']);
        }

        return $query->orderBy('nombre')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Apellido',
            'RUT',
            'Email',
            'Telefono',
            'Cargo',
            'Departamento',
            'Salario',
            'Sueldo Liquido Pactado',
            'Estado',
            'Fecha Contratacion',
            'Fecha Nacimiento',
            'Nacionalidad',
            'Estado Civil',
            'Direccion',
            'Comuna',
            'AFP',
            'Sistema Salud',
            'Isapre Nombre',
            'Banco Nombre',
            'Banco Tipo Cuenta',
            'Banco Numero Cuenta',
            'Hora Entrada',
            'Hora Salida',
            'Codigo Almacen',
            'Notas',
        ];
    }

    public function map($e): array
    {
        return [
            $e->id,
            $e->nombre,
            $e->apellido,
            $e->rut,
            $e->email,
            $e->telefono,
            $e->cargo,
            $e->departamento,
            $e->salario,
            $e->sueldo_liquido_pactado,
            $e->estado,
            $e->fecha_contratacion,
            $e->fecha_nacimiento,
            $e->nacionalidad,
            $e->estado_civil,
            $e->direccion,
            $e->comuna,
            $e->afp,
            $e->sistema_salud,
            $e->isapre_nombre,
            $e->banco_nombre,
            $e->banco_tipo_cuenta,
            $e->banco_numero_cuenta,
            $e->hora_entrada,
            $e->hora_salida,
            $e->almacen?->codigo,
            $e->notas,
        ];
    }
}

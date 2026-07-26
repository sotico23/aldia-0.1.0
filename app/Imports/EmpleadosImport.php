<?php

namespace App\Imports;

use App\Models\Almacen;
use App\Models\Empleado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmpleadosImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        $ownerId = Auth::user()->getOwnerId();

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $email = $row['email'] ?? null;
            $nombre = $row['nombre'] ?? null;

            if (empty($nombre) && empty($id) && empty($email)) {
                continue;
            }

            // Resolve Almacen by code or name
            $almacenId = null;
            $codigoAlmacen = $row['codigo_almacen'] ?? $row['almacen'] ?? null;
            if (! empty($codigoAlmacen)) {
                $almacen = Almacen::where('owner_id', $ownerId)
                    ->where(function ($q) use ($codigoAlmacen) {
                        $q->where('codigo', $codigoAlmacen)
                            ->orWhere('nombre', $codigoAlmacen);
                    })->first();
                $almacenId = $almacen?->id;
            }

            // Map variables, taking care of potential accent/space variations in keys
            $rut = $row['rut'] ?? null;
            $telefono = $row['telefono'] ?? $row['telefono'] ?? null;
            $cargo = $row['cargo'] ?? null;
            $departamento = $row['departamento'] ?? null;

            $salario = isset($row['salario']) && is_numeric($row['salario']) ? $row['salario'] : null;
            $sueldoLiquido = isset($row['sueldo_liquido_pactado']) && is_numeric($row['sueldo_liquido_pactado'])
                ? $row['sueldo_liquido_pactado']
                : ($row['sueldo_liquido'] ?? null);

            $estado = $row['estado'] ?? 'Activo';
            $fechaContratacion = ! empty($row['fecha_contratacion']) ? $row['fecha_contratacion'] : null;
            $fechaNacimiento = ! empty($row['fecha_nacimiento']) ? $row['fecha_nacimiento'] : null;

            $nacionalidad = $row['nacionalidad'] ?? null;
            $estadoCivil = $row['estado_civil'] ?? $row['estado_civil'] ?? null;
            $direccion = $row['direccion'] ?? $row['direccion'] ?? null;
            $comuna = $row['comuna'] ?? null;

            $afp = $row['afp'] ?? null;
            $sistemaSalud = $row['sistema_salud'] ?? $row['sistema_salud'] ?? null;
            $isapreNombre = $row['isapre_nombre'] ?? $row['isapre_nombre'] ?? null;

            $bancoNombre = $row['banco_nombre'] ?? $row['banco'] ?? null;
            $bancoTipoCuenta = $row['banco_tipo_cuenta'] ?? $row['tipo_cuenta'] ?? null;
            $bancoNumeroCuenta = $row['banco_numero_cuenta'] ?? $row['cuenta_bancaria'] ?? $row['numero_cuenta'] ?? null;

            $horaEntrada = $row['hora_entrada'] ?? null;
            $horaSalida = $row['hora_salida'] ?? null;
            $notas = $row['notas'] ?? null;

            $match = [];
            if ($id) {
                $match = ['id' => $id, 'creator_id' => $ownerId];
            } elseif ($email) {
                $match = ['email' => $email, 'creator_id' => $ownerId];
            } else {
                $match = ['nombre' => $nombre, 'apellido' => $row['apellido'] ?? '', 'creator_id' => $ownerId];
            }

            Empleado::updateOrCreate(
                $match,
                [
                    'creator_id' => $ownerId,
                    'owner_id' => $ownerId,
                    'user_id' => null,
                    'almacen_id' => $almacenId,
                    'nombre' => $nombre ?? ($id ? Empleado::find($id)?->nombre : 'Sin Nombre'),
                    'apellido' => $row['apellido'] ?? '',
                    'email' => $email ?? ($id ? Empleado::find($id)?->email : ''),
                    'telefono' => $telefono,
                    'rut' => $rut,
                    'cargo' => $cargo,
                    'departamento' => $departamento,
                    'salario' => $salario,
                    'sueldo_liquido_pactado' => $sueldoLiquido,
                    'estado' => $estado,
                    'fecha_contratacion' => $fechaContratacion,
                    'fecha_nacimiento' => $fechaNacimiento,
                    'nacionalidad' => $nacionalidad,
                    'estado_civil' => $estadoCivil,
                    'direccion' => $direccion,
                    'comuna' => $comuna,
                    'afp' => $afp,
                    'sistema_salud' => $sistemaSalud,
                    'isapre_nombre' => $isapreNombre,
                    'banco_nombre' => $bancoNombre,
                    'banco_tipo_cuenta' => $bancoTipoCuenta,
                    'banco_numero_cuenta' => $bancoNumeroCuenta,
                    'hora_entrada' => $horaEntrada,
                    'hora_salida' => $horaSalida,
                    'notas' => $notas,
                ]
            );
        }
    }
}

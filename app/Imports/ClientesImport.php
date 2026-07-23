<?php

namespace App\Imports;

use App\Models\Categoria;
use App\Models\Cliente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        $ownerId = auth()->user()->getOwnerId();

        DB::transaction(function () use ($rows, $ownerId) {
            foreach ($rows as $row) {
                $nombre = $row['nombre'] ?? null;
                $id = $row['id'] ?? null;

                if (empty($nombre) && empty($id)) {
                    continue;
                }

                $nit = $row['nit'] ?? $row['rut'] ?? null;
                $rut = $row['rut'] ?? null;
                $email = $row['email'] ?? null;

                // Handle column headers that might slugify differently due to accents
                $telefono = $row['telefono'] ?? $row['telefono'] ?? null;
                $direccion = $row['direccion'] ?? $row['direccion'] ?? null;
                $region = $row['region'] ?? $row['region'] ?? null;
                $telefonoContacto = $row['telefono_contacto'] ?? $row['telefono_contacto'] ?? null;
                $categoriaName = $row['categoria'] ?? $row['categoria'] ?? null;
                $notas = $row['notas'] ?? null;

                // Find or create category of type 'cliente'
                $categoriaId = null;
                if (! empty($categoriaName)) {
                    $categoria = Categoria::firstOrCreate(
                        ['nombre' => $categoriaName, 'owner_id' => $ownerId],
                        ['tipo' => 'cliente', 'activo' => true]
                    );
                    $categoriaId = $categoria->id;
                }

                // Parse active state
                $activo = true;
                if (isset($row['activo'])) {
                    $activo = ! in_array(strtolower((string) $row['activo']), ['no', '0', 'false']);
                }

                // Determine match criteria
                $cliente = null;
                if ($id) {
                    $cliente = Cliente::where('id', $id)->where('owner_id', $ownerId)->first();
                }

                if (! $cliente) {
                    // Fallbacks for duplicate prevention if ID is not matching or not provided
                    $query = Cliente::where('owner_id', $ownerId);
                    if ($nit) {
                        $query->where('nit', $nit);
                    } elseif ($rut) {
                        $query->where('rut', $rut);
                    } elseif ($email) {
                        $query->where('email', $email);
                    } else {
                        $query->where('nombre', $nombre);
                    }
                    $cliente = $query->first();
                }

                $data = [
                    'nombre' => $nombre ?? ($cliente ? $cliente->nombre : 'Sin Nombre'),
                    'nit' => $nit ?: ($cliente ? $cliente->nit : null),
                    'rut' => $rut ?: ($cliente ? $cliente->rut : null),
                    'telefono' => $telefono,
                    'email' => $email,
                    'direccion' => $direccion,
                    'ciudad' => $row['ciudad'] ?? null,
                    'region' => $region,
                    'comuna' => $row['comuna'] ?? null,
                    'giro' => $row['giro'] ?? null,
                    'contacto' => $row['contacto'] ?? null,
                    'telefono_contacto' => $telefonoContacto,
                    'categoria_id' => $categoriaId,
                    'activo' => $activo,
                    'notas' => $notas,
                ];

                if ($cliente) {
                    $cliente->update($data);
                } else {
                    $data['owner_id'] = $ownerId;
                    $data['user_id'] = Auth::id();
                    Cliente::create($data);
                }
            }
        });
    }
}

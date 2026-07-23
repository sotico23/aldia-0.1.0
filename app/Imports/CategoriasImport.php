<?php

namespace App\Imports;

use App\Models\Categoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CategoriasImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        $ownerId = Auth::user()->getOwnerId();

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $nombre = $row['nombre'] ?? null;

            if (! $nombre && ! $id) {
                continue;
            }

            $activo = ! in_array(strtolower($row['activo'] ?? ''), ['no', '0', 'false']);
            $mostrarEnPerfil = in_array(strtolower($row['mostrar_en_perfil'] ?? ''), ['si', 'sí', '1', 'true']);

            // Handle description column which might have accent in the header (Descripción -> descripcion)
            $descripcion = $row['descripcion'] ?? $row['descripcion'] ?? null;

            $match = [];
            if ($id) {
                $match = ['id' => $id, 'owner_id' => $ownerId];
            } else {
                $match = ['nombre' => $nombre, 'owner_id' => $ownerId];
            }

            Categoria::updateOrCreate(
                $match,
                [
                    'nombre' => $nombre ?? '',
                    'descripcion' => $descripcion,
                    'tipo' => $row['tipo'] ?? 'producto',
                    'activo' => $activo,
                    'mostrar_en_perfil' => $mostrarEnPerfil,
                    'user_id' => Auth::id(),
                    'owner_id' => $ownerId,
                ]
            );
        }
    }
}

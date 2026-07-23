<?php

namespace App\Imports;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductosImport implements ToCollection, WithHeadingRow
{
    protected $ownerId;

    public function __construct($ownerId = null)
    {
        $this->ownerId = $ownerId ?: Auth::user()->getOwnerId();
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            $relationsToResolve = [];

            foreach ($rows as $row) {
                $id = $row['id'] ?? null;
                $codigo = $row['codigo'] ?? null;

                if (! $codigo && ! $id) {
                    continue;
                }

                // Find or create category of type 'producto' or 'servicio' (defaults to 'producto')
                $categoriaId = null;
                $categoriaName = $row['categoria'] ?? null;
                if (! empty($categoriaName)) {
                    $tipoCat = in_array(strtolower($row['is_service'] ?? ''), ['si', 'sí', '1', 'true']) ? 'servicio' : 'producto';
                    $categoria = Categoria::firstOrCreate(
                        ['nombre' => $categoriaName, 'owner_id' => $this->ownerId],
                        ['tipo' => $tipoCat, 'activo' => true]
                    );
                    $categoriaId = $categoria->id;
                }

                // Normalize booleans
                $activo = ! in_array(strtolower((string) ($row['activo'] ?? '')), ['no', '0', 'false']);
                $medidaPesable = in_array(strtolower((string) ($row['medida_pesable'] ?? '')), ['si', 'sí', '1', 'true']);
                $envaseRetornable = in_array(strtolower((string) ($row['envase_retornable'] ?? '')), ['si', 'sí', '1', 'true']);
                $isService = in_array(strtolower((string) ($row['is_service'] ?? '')), ['si', 'sí', '1', 'true']);
                $requiresAppointment = in_array(strtolower((string) ($row['requires_appointment'] ?? '')), ['si', 'sí', '1', 'true']);
                $tieneVariantes = in_array(strtolower((string) ($row['tiene_variantes'] ?? '')), ['si', 'sí', '1', 'true']);

                $match = [];
                if ($id) {
                    $match = ['id' => $id, 'owner_id' => $this->ownerId];
                } else {
                    $match = ['codigo' => $codigo, 'owner_id' => $this->ownerId];
                }

                $data = [
                    'codigo' => $codigo ?? ($id ? Producto::find($id)?->codigo : null),
                    'nombre' => $row['nombre'] ?? 'Sin Nombre',
                    'descripcion' => $row['descripcion'] ?? null,
                    'precio_compra' => isset($row['precio_compra']) ? (float) $row['precio_compra'] : 0.0,
                    'precio_venta' => isset($row['precio_venta']) ? (float) $row['precio_venta'] : 0.0,
                    'stock_minimo' => isset($row['stock_minimo']) ? (float) $row['stock_minimo'] : 0.0,
                    'unidad_medida' => in_array($row['unidad_medida'] ?? '', ['unidad', 'kg', 'lt']) ? $row['unidad_medida'] : 'unidad',
                    'categoria_id' => $categoriaId,
                    'user_id' => Auth::id(),
                    'activo' => $activo,
                    'medida_pesable' => $medidaPesable,
                    'tipo_medida' => in_array($row['tipo_medida'] ?? '', ['unidad', 'kilo', 'litro']) ? $row['tipo_medida'] : 'unidad',
                    'cantidad_medida' => isset($row['cantidad_medida']) && is_numeric($row['cantidad_medida']) ? (float) $row['cantidad_medida'] : 0,
                    'peso_base' => isset($row['peso_base']) && is_numeric($row['peso_base']) ? (float) $row['peso_base'] : 0.0,
                    'contenido_por_unidad' => isset($row['contenido_por_unidad']) && is_numeric($row['contenido_por_unidad']) ? (float) $row['contenido_por_unidad'] : 0,
                    'envase_retornable' => $envaseRetornable,
                    'tipo_envase' => $row['tipo_envase'] ?? null,
                    'is_service' => $isService,
                    'duracion' => isset($row['duracion']) ? (int) $row['duracion'] : null,
                    'requires_appointment' => $requiresAppointment,
                    'talla' => $row['talla'] ?? null,
                    'color' => $row['color'] ?? null,
                    'tiene_variantes' => $tieneVariantes,
                    'fecha_vencimiento' => ! empty($row['fecha_vencimiento']) ? $row['fecha_vencimiento'] : null,
                    'peso_por_unidad' => isset($row['peso_por_unidad']) && is_numeric($row['peso_por_unidad']) ? (float) $row['peso_por_unidad'] : 0,
                    'mostrar_en_perfil' => in_array(strtolower((string) ($row['mostrar_en_perfil'] ?? '')), ['si', 'sí', '1', 'true']),
                ];

                $producto = Producto::updateOrCreate($match, $data);

                // Sync stock to the first/latest inventory record
                if (isset($row['stock'])) {
                    $inventario = Inventario::where('producto_id', $producto->id)
                        ->orderBy('id')
                        ->first();

                    if ($inventario) {
                        $inventario->update([
                            'cantidad' => (float) $row['stock'],
                            'cantidad_minima' => (float) ($row['stock_minimo'] ?? 0),
                        ]);
                    } else {
                        // Fallback: If no inventory record exists (e.g. newly created product without a warehouse),
                        // we can either leave it to be created by warehouse assignment or create a default one with null/first warehouse.
                        // We will try to find the first warehouse of the owner to initialize the stock, otherwise we leave it.
                        $firstWarehouse = Almacen::where('owner_id', $this->ownerId)->orderBy('id')->first();
                        if ($firstWarehouse) {
                            Inventario::create([
                                'producto_id' => $producto->id,
                                'almacen_id' => $firstWarehouse->id,
                                'cantidad' => (float) $row['stock'],
                                'cantidad_minima' => (float) ($row['stock_minimo'] ?? 0),
                            ]);
                        }
                    }
                }

                // Queue foreign key relationships for resolution
                $envaseCodigo = $row['envase_codigo'] ?? $row['envase_codigo'] ?? null;
                $parentCodigo = $row['parent_codigo'] ?? $row['parent_codigo'] ?? null;

                if (! empty($envaseCodigo) || ! empty($parentCodigo)) {
                    $relationsToResolve[] = [
                        'id' => $producto->id,
                        'envase_codigo' => $envaseCodigo,
                        'parent_codigo' => $parentCodigo,
                    ];
                }
            }

            // Resolve relationships
            foreach ($relationsToResolve as $rel) {
                $p = Producto::find($rel['id']);
                if (! $p) {
                    continue;
                }

                $envaseId = null;
                if (! empty($rel['envase_codigo'])) {
                    $envase = Producto::where('codigo', $rel['envase_codigo'])
                        ->where('owner_id', $this->ownerId)
                        ->first();
                    $envaseId = $envase?->id;
                }

                $parentId = null;
                if (! empty($rel['parent_codigo'])) {
                    $parent = Producto::where('codigo', $rel['parent_codigo'])
                        ->where('owner_id', $this->ownerId)
                        ->first();
                    $parentId = $parent?->id;
                }

                $updateData = [];
                if ($envaseId) {
                    $updateData['envase_producto_id'] = $envaseId;
                }
                if ($parentId) {
                    $updateData['parent_id'] = $parentId;
                }

                if (! empty($updateData)) {
                    $p->update($updateData);
                }
            }
        });
    }
}

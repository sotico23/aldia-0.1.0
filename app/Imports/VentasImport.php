<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VentasImport implements ToCollection, WithHeadingRow
{
    protected $ownerId;

    protected $errors = [];

    protected $importedCount = 0;

    protected $skippedCount = 0;

    public function __construct($ownerId = null)
    {
        $this->ownerId = $ownerId ?: Auth::user()->getOwnerId();
    }

    public function collection(Collection $rows): void
    {
        $this->errors = [];
        $this->importedCount = 0;
        $this->skippedCount = 0;

        $grouped = $rows->groupBy(function ($item) {
            return $item['numero'] ?? $item['Numero'] ?? null;
        })->filter(fn ($group, $key) => ! empty($key));

        DB::transaction(function () use ($grouped) {
            foreach ($grouped as $numero => $items) {
                $first = $items->first();
                $email = $first['cliente_email'] ?? $first['Cliente_Email'] ?? $first['Cliente Email'] ?? null;

                $cliente = null;
                if ($email) {
                    $cliente = Cliente::where('email', $email)
                        ->where('owner_id', $this->ownerId)
                        ->first();
                }

                // Si no hay cliente con ese email, crear uno genérico
                if (! $cliente) {
                    if ($email) {
                        $this->addError("Cliente con email '{$email}' no encontrado para venta #{$numero}. Se creará cliente genérico.");
                    } else {
                        $this->addError("Venta #{$numero} no tiene email de cliente. Se creará cliente genérico.");
                    }

                    $cliente = Cliente::firstOrCreate(
                        ['owner_id' => $this->ownerId, 'email' => $email ?? "import-{$numero}@sistema.local"],
                        [
                            'nombre' => $first['cliente_nombre'] ?? $first['Cliente_Nombre'] ?? "Cliente Importado #{$numero}",
                            'rut' => $first['cliente_rut'] ?? $first['Cliente_Rut'] ?? null,
                            'telefono' => $first['cliente_telefono'] ?? $first['Cliente_Telefono'] ?? null,
                            'direccion' => $first['cliente_direccion'] ?? $first['Cliente_Direccion'] ?? null,
                            'activo' => true,
                        ]
                    );
                }

                $estado = $first['estado'] ?? $first['Estado'] ?? 'pendiente';

                $venta = Venta::updateOrCreate(
                    ['numero' => $numero, 'owner_id' => $this->ownerId],
                    [
                        'cliente_id' => $cliente->id,
                        'user_id' => Auth::id(),
                        'fecha' => $first['fecha'] ?? $first['Fecha'] ?? now(),
                        'estado' => $estado ?: 'pendiente',
                        'notas' => $first['notas'] ?? $first['Notas'] ?? null,
                        'subtotal' => 0,
                        'iva' => 0,
                        'total' => 0,
                    ]
                );

                $venta->detalleVentas()->delete();
                $subtotal = 0;
                $itemsImported = 0;

                foreach ($items as $item) {
                    $desc = $item['item_descripcion'] ?? $item['Item Descripcion'] ?? $item['Item_Descripcion'] ?? null;
                    if (empty($desc)) {
                        continue;
                    }

                    $producto = Producto::where('nombre', $desc)
                        ->where('owner_id', $this->ownerId)
                        ->first();

                    if (! $producto) {
                        $this->addError("Producto '{$desc}' no encontrado para venta #{$numero}. Se omitirá esta línea.");
                        $this->skippedCount++;

                        continue;
                    }

                    $cantidad = (int) ($item['item_cantidad'] ?? $item['Item Cantidad'] ?? $item['Item_Cantidad'] ?? 1);
                    $precio = round((float) ($item['item_precio'] ?? $item['Item Precio'] ?? $item['Item_Precio'] ?? 0));
                    $itemSubtotal = round($cantidad * $precio);

                    DetalleVenta::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio,
                        'subtotal' => (int) $itemSubtotal,
                    ]);

                    $subtotal += $itemSubtotal;
                    $itemsImported++;
                }

                if ($itemsImported === 0) {
                    $this->addError("Venta #{$numero} no tiene items válidos. Se eliminará la venta.");
                    $venta->delete();
                    $this->skippedCount++;

                    continue;
                }

                $iva = round($subtotal * config('taxes.iva_rate', 0.19));
                $venta->update([
                    'subtotal' => (int) $subtotal,
                    'iva' => (int) $iva,
                    'total' => (int) ($subtotal + $iva),
                ]);

                $this->importedCount++;
            }
        });
    }

    protected function addError(string $message): void
    {
        $this->errors[] = $message;
        Log::warning('Venta Import Error: '.$message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}

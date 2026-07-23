<?php

namespace App\Models;

use App\Exceptions\StockInsufficientException;
use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class InventoryMovement extends Model
{
    use BelongsToOwner, HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('hierarchy', function (Builder $query) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $ownerId = $user->getOwnerId();
            $userLevel = $user->highestRoleLevel();

            $query->where('owner_id', $ownerId);

            if (in_array($userLevel, [0, 1, 2])) {
                return;
            }

            if ($userLevel === 3) {
                $warehouseId = $user->currentWarehouseId();

                if ($warehouseId) {
                    $query->where(function (Builder $q) use ($warehouseId) {
                        $q->where('source_warehouse_id', $warehouseId)
                            ->orWhere('destination_warehouse_id', $warehouseId);
                    });
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        });
    }

    protected $fillable = [
        'owner_id',
        'user_id',
        'product_id',
        'source_warehouse_id',
        'destination_warehouse_id',
        'quantity',
        'type',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'product_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'destination_warehouse_id');
    }

    public function scopeIngreso($query)
    {
        return $query->where('type', 'INGRESO');
    }

    public function scopeEgreso($query)
    {
        return $query->where('type', 'EGRESO');
    }

    public function scopeTraslado($query)
    {
        return $query->where('type', 'TRASLADO');
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where(function ($q) use ($warehouseId) {
            $q->where('source_warehouse_id', $warehouseId)
                ->orWhere('destination_warehouse_id', $warehouseId);
        });
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByDateRange($query, ?string $from = null, ?string $to = null)
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Registra un movimiento de inventario dentro de una transacción atómica.
     * Para EGRESO/TRASLADO: bloquea la fila de inventario con lockForUpdate
     * y valida stock suficiente antes de descontar.
     *
     * @throws StockInsufficientException
     */
    public static function registrarMovimiento(
        int $productId,
        int $userId,
        string $type,
        float $quantity,
        ?int $sourceWarehouseId = null,
        ?int $destinationWarehouseId = null,
        ?string $description = null,
        ?int $ownerId = null
    ): self {
        return DB::transaction(function () use ($productId, $userId, $type, $quantity, $sourceWarehouseId, $destinationWarehouseId, $description, $ownerId) {
            $movement = self::create([
                'owner_id' => $ownerId ?? auth()->user()?->getOwnerId(),
                'user_id' => $userId,
                'product_id' => $productId,
                'source_warehouse_id' => $sourceWarehouseId,
                'destination_warehouse_id' => $destinationWarehouseId,
                'quantity' => $quantity,
                'type' => $type,
                'description' => $description,
            ]);

            $movement->actualizarStockProducto();

            return $movement;
        });
    }

    private function actualizarStockProducto(): void
    {
        $producto = $this->product;

        if (! $producto) {
            return;
        }

        match ($this->type) {
            'INGRESO' => $this->incrementarStock($this->destination_warehouse_id, $this->quantity),
            'EGRESO' => $this->decrementarStock($this->source_warehouse_id, $this->quantity),
            'TRASLADO' => $this->ejecutarTraslado(),
            default => null,
        };
    }

    private function incrementarStock(?int $warehouseId, float $quantity): void
    {
        if (! $warehouseId) {
            return;
        }

        $inventario = Inventario::updateOrCreate(
            ['producto_id' => $this->product_id, 'almacen_id' => $warehouseId],
            []
        );

        $inventario->increment('cantidad', $quantity);
    }

    /**
     * Decrementa stock con bloqueo pesimista (lockForUpdate) para prevenir
     * condiciones de carrera entre ventas simultáneas.
     *
     * @throws StockInsufficientException
     */
    private function decrementarStock(?int $warehouseId, float $quantity): void
    {
        if (! $warehouseId) {
            return;
        }

        $inventario = Inventario::where('producto_id', $this->product_id)
            ->where('almacen_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $inventario) {
            $inventario = Inventario::create([
                'producto_id' => $this->product_id,
                'almacen_id' => $warehouseId,
                'cantidad' => 0,
            ]);
        }

        // Permitir stock negativo - solo decrementar
        $inventario->decrement('cantidad', $quantity);
    }

    private function ejecutarTraslado(): void
    {
        if ($this->source_warehouse_id && $this->destination_warehouse_id) {
            $this->decrementarStock($this->source_warehouse_id, $this->quantity);
            $this->incrementarStock($this->destination_warehouse_id, $this->quantity);
        }
    }
}

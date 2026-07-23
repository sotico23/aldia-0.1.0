<?php

namespace App\Models;

use App\Helpers\NotificationHelper;
use App\Notifications\CuponLimiteNotificacion;
use App\Traits\BelongsToOwner;
use Database\Factories\CuponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cupon extends Model
{
    /** @use HasFactory<CuponFactory> */
    use BelongsToOwner, HasFactory;

    protected $table = 'cupones';

    protected $fillable = [
        'owner_id',
        'user_id',
        'codigo',
        'tipo',
        'valor',
        'descripcion',
        'plantilla_html',
        'variables_ejemplo',
        'max_usos',
        'usos_actuales',
        'usos_por_cliente',
        'compra_minima',
        'fecha_inicio',
        'fecha_fin',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'compra_minima' => 'decimal:2',
            'variables_ejemplo' => 'array',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'activa' => 'boolean',
            'usos_actuales' => 'integer',
            'max_usos' => 'integer',
            'usos_por_cliente' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usos(): HasMany
    {
        return $this->hasMany(CuponUso::class);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'cupon_producto', 'cupon_id', 'producto_id')
            ->withPivot('descuento_tipo', 'descuento_valor');
    }

    public function scopeActive($query)
    {
        return $query->where('activa', true);
    }

    public function scopeVigente($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now());
        })->where(function ($q) {
            $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now());
        });
    }

    public function scopeValidoPara($query, ?float $monto = null)
    {
        return $query->where(function ($q) use ($monto) {
            if ($monto !== null) {
                $q->whereNull('compra_minima')->orWhere('compra_minima', '<=', $monto);
            }
        })->where(function ($q) {
            $q->where('max_usos', '<=', 0)->orWhereColumn('usos_actuales', '<', 'max_usos');
        });
    }

    public function canjear(?int $userId = null): bool
    {
        $query = static::where('id', $this->id)
            ->where('activa', true)
            ->where(function ($q) {
                $q->where('max_usos', '<=', 0)
                    ->orWhereColumn('usos_actuales', '<', 'max_usos');
            });

        $affected = $query->increment('usos_actuales');

        if ($affected > 0) {
            $this->refresh();

            // Notificar al admin si se alcanza el 80% de max_usos
            if ($this->max_usos > 0 && $this->usos_actuales >= (int) ($this->max_usos * 0.8)) {
                $this->notificarLimiteCercano();
            }

            return true;
        }

        return false;
    }

    public function validar(?float $monto = null, ?int $userId = null): bool
    {
        if (! $this->activa) {
            return false;
        }

        if ($this->fecha_inicio && $this->fecha_inicio->isFuture()) {
            return false;
        }

        if ($this->fecha_fin && $this->fecha_fin->isPast()) {
            return false;
        }

        if ($this->max_usos > 0 && $this->usos_actuales >= $this->max_usos) {
            return false;
        }

        if ($monto !== null && $this->compra_minima !== null && $monto < (float) $this->compra_minima) {
            return false;
        }

        if ($userId !== null && $this->usos_por_cliente > 0) {
            $usosCliente = CuponUso::where('cupon_id', $this->id)
                ->where('user_id', $userId)
                ->count();

            if ($usosCliente >= $this->usos_por_cliente) {
                return false;
            }
        }

        return true;
    }

    public function calcularDescuento(float $monto): float
    {
        return match ($this->tipo) {
            'porcentaje' => round($monto * ((float) $this->valor / 100), 2),
            'precio_fijo' => min((float) $this->valor, $monto),
            'envio_gratis' => 0.0,
            'vale_producto' => 0.0,
            default => 0.0,
        };
    }

    public function calcularDescuentoProductos(array $items): float
    {
        // Normalizar items para aceptar tanto 'precio_unitario' como 'precio', y 'producto_id' como 'id'
        $itemsNormalizados = array_map(function ($item) {
            return [
                'producto_id' => (int) ($item['producto_id'] ?? $item['id'] ?? 0),
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'precio_unitario' => (float) ($item['precio_unitario'] ?? $item['precio'] ?? 0),
            ];
        }, $items);

        if ($this->tipo !== 'vale_producto') {
            $montoTotal = array_sum(array_map(fn ($item) => $item['cantidad'] * $item['precio_unitario'], $itemsNormalizados));

            return $this->calcularDescuento($montoTotal);
        }

        $descuento = 0.0;
        $productosConfig = $this->productos->keyBy('id');

        foreach ($itemsNormalizados as $item) {
            $productoId = $item['producto_id'];

            if (! $productosConfig->has($productoId)) {
                continue;
            }

            $pivot = $productosConfig->get($productoId)->pivot;
            $cantidad = $item['cantidad'];
            $precioUnitario = $item['precio_unitario'];

            if ($pivot->descuento_tipo === 'precio_fijo' && $pivot->descuento_valor > 0) {
                $descuento += max(0, ($precioUnitario - (float) $pivot->descuento_valor) * $cantidad);
            } else {
                $descuento += round($precioUnitario * $cantidad * ((float) $this->valor / 100), 2);
            }
        }

        return round($descuento, 2);
    }

    public function esValeProducto(): bool
    {
        return $this->tipo === 'vale_producto';
    }

    public function renderizarPlantilla(?array $data = null): string
    {
        $html = $this->plantilla_html ?? '';

        $variables = $data ?? $this->variables_ejemplo ?? $this->getDefaultVariables();

        foreach ($variables as $key => $value) {
            $html = str_replace('{{'.$key.'}}', (string) $value, $html);
        }

        return $html;
    }

    public function renderizarPreview(): string
    {
        return $this->renderizarPlantilla($this->variables_ejemplo);
    }

    private function notificarLimiteCercano(): void
    {
        $admin = User::where('id', $this->owner_id)->first();
        if ($admin && method_exists($admin, 'notify')) {
            NotificationHelper::send($admin, new CuponLimiteNotificacion($this));
        }
    }

    private function getDefaultVariables(): array
    {
        return [
            'codigo' => $this->codigo,
            'valor' => (string) $this->valor,
            'tipo' => match ($this->tipo) {
                'porcentaje' => 'Porcentaje',
                'precio_fijo' => 'Precio Fijo',
                'envio_gratis' => 'Envío Gratis',
                'vale_producto' => 'Vale por Producto',
                default => $this->tipo,
            },
            'vencimiento' => $this->fecha_fin?->format('d-m-Y') ?? 'Sin vencimiento',
            'descripcion' => $this->descripcion ?? '',
            'compra_minima' => $this->compra_minima ? '$'.number_format((float) $this->compra_minima, 0, ',', '.') : 'Sin mínimo',
        ];
    }
}

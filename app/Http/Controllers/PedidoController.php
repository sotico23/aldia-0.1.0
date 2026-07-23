<?php

namespace App\Http\Controllers;

use App\Events\PedidoCreado;
use App\Helpers\NotificationHelper;
use App\Models\Conversacion;
use App\Models\Inventario;
use App\Models\PaymentConfig;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use App\Notifications\ActualizacionEstadoPedidoNotification;
use App\Notifications\NuevoPedidoNotification;
use App\Scopes\OwnerScope;
use App\Traits\ErpSyncTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PedidoController extends Controller
{
    use ErpSyncTrait;

    public function crear(Request $request, string $slug): RedirectResponse
    {
        $publicProfile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $validated = $request->validate([
            'public_profile_id' => ['required', 'integer', function ($attribute, $value, $fail) use ($publicProfile) {
                if ((int) $value !== (int) $publicProfile->id) {
                    $fail('El perfil público no coincide con la tienda solicitada.');
                }
            }],
            'items' => 'required|array|min:1',
            'items.*.producto_id' => [
                'required',
                Rule::exists('productos', 'id')->where(function ($query) use ($publicProfile) {
                    $query->where('public_profile_id', $publicProfile->id)
                        ->where('owner_id', $publicProfile->owner_id)
                        ->where('activo', true);
                }),
            ],
            'items.*.cantidad' => 'required|numeric|min:0.001',
            'nombre_cliente' => 'required|string|max:255',
            'telefono_cliente' => 'nullable|string|max:50',
            'direccion_cliente' => 'nullable|string',
            'metodo_pago' => ['nullable', 'string', 'max:100', function ($attribute, $value, $fail) use ($publicProfile) {
                if (! $value) {
                    return;
                }

                $config = PaymentConfig::withoutGlobalScope(OwnerScope::class)
                    ->where('owner_id', $publicProfile->owner_id)
                    ->where(function ($q) {
                        $q->where('is_active', true)
                            ->orWhere('paypal_active', true)
                            ->orWhere('mercadopago_active', true);
                    })
                    ->first();

                $gatewayMethods = [];
                if ($config) {
                    if ($config->is_active) {
                        $gatewayMethods[] = 'webpay';
                    }
                    if ($config->paypal_active) {
                        $gatewayMethods[] = 'paypal';
                    }
                    if ($config->mercadopago_active) {
                        $gatewayMethods[] = 'mercadopago';
                    }
                }

                $allowed = array_merge(['efectivo', 'transferencia'], $gatewayMethods);
                if (! in_array(strtolower($value), $allowed)) {
                    $fail('El método de pago seleccionado no está disponible para esta tienda.');
                }
            }],
            'notas' => 'nullable|string',
        ]);

        $validated['public_profile_id'] = $publicProfile->id;

        try {
            $pedido = DB::transaction(function () use ($validated, $publicProfile) {
                foreach ($validated['items'] as $item) {
                    $producto = Producto::query()
                        ->whereKey($item['producto_id'])
                        ->where('public_profile_id', $publicProfile->id)
                        ->where('owner_id', $publicProfile->owner_id)
                        ->firstOrFail();
                    $cantidad = $item['cantidad'];

                    $inventarios = Inventario::query()
                        ->where('producto_id', $producto->id)
                        ->where('owner_id', $publicProfile->owner_id)
                        ->lockForUpdate()
                        ->get();

                    $inventarioTotal = $inventarios->sum('cantidad');
                    $tieneInventario = $inventarios->isNotEmpty();

                    if ($tieneInventario && $inventarioTotal < $cantidad) {
                        throw new \RuntimeException("Stock insuficiente para {$producto->nombre}. Disponible: {$inventarioTotal}, solicitado: {$cantidad}.");
                    }
                }

                $pedido = Pedido::create([
                    'user_id' => $publicProfile->user_id,
                    'owner_id' => $publicProfile->owner_id,
                    'public_profile_id' => $validated['public_profile_id'],
                    'cliente_id' => Auth::id(),
                    'numero_pedido' => Pedido::generarNumeroPedido(),
                    'estado' => 'pendiente',
                    'nombre_cliente' => $validated['nombre_cliente'],
                    'telefono_cliente' => $validated['telefono_cliente'] ?? null,
                    'direccion_cliente' => $validated['direccion_cliente'] ?? null,
                    'metodo_pago' => $validated['metodo_pago'] ?? null,
                    'notas' => $validated['notas'] ?? null,
                ]);

                $subtotal = 0;

                foreach ($validated['items'] as $item) {
                    $producto = Producto::query()
                        ->whereKey($item['producto_id'])
                        ->where('public_profile_id', $publicProfile->id)
                        ->where('owner_id', $publicProfile->owner_id)
                        ->firstOrFail();
                    $cantidad = $item['cantidad'];
                    $precio = $producto->precio_venta;
                    $itemSubtotal = $precio * $cantidad;
                    $subtotal += $itemSubtotal;

                    PedidoItem::create([
                        'pedido_id' => $pedido->id,
                        'producto_id' => $producto->id,
                        'nombre_producto' => $producto->nombre,
                        'precio_unitario' => $precio,
                        'cantidad' => $cantidad,
                        'subtotal' => $itemSubtotal,
                    ]);
                }

                $impuesto = $subtotal * config('taxes.iva_rate');
                $total = $subtotal + $impuesto;

                $pedido->update([
                    'subtotal' => $subtotal,
                    'impuesto' => $impuesto,
                    'total' => $total,
                ]);

                // Always create the conversation before any payment redirect
                Conversacion::create([
                    'pedido_id' => $pedido->id,
                    'public_profile_id' => $validated['public_profile_id'],
                    'comprador_id' => Auth::id(),
                    'vendedor_id' => $publicProfile->user_id,
                    'titulo' => "Pedido #{$pedido->numero_pedido}",
                ]);

                return $pedido;
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['stock' => $e->getMessage()])->withInput();
        }

        // Notificar al vendedor que tiene un nuevo pedido
        $vendedor = User::find($publicProfile->user_id);
        if ($vendedor) {
            NotificationHelper::send($vendedor, new NuevoPedidoNotification($pedido));
        }

        // Dispatch event for buyer notification
        PedidoCreado::dispatch($pedido);

        // --- MANEJO DE PAGOS ---
        $metodoPago = strtolower($validated['metodo_pago'] ?? 'efectivo');

        if ($metodoPago === 'paypal') {
            return redirect()->route('paypal.pay', ['pedidoId' => $pedido->id]);
        }

        if ($metodoPago === 'mercadopago') {
            return redirect()->route('mercadopago.pay', ['pedidoId' => $pedido->id]);
        }

        if ($metodoPago === 'webpay') {
            return redirect()->route('webpay.pay', ['pedido' => $pedido->id]);
        }

        // Si es efectivo o local, sincronizar ahora mismo y confirmar
        if ($metodoPago === 'efectivo' || empty($metodoPago)) {
            $this->syncPedidoToErp($pedido);
            $pedido->update(['estado' => 'confirmado', 'payment_status' => 'local']);

            $comprador = Auth::user();
            if ($comprador) {
                NotificationHelper::send($comprador, new ActualizacionEstadoPedidoNotification($pedido, 'pendiente', 'confirmado'));
            }
        }

        return redirect()->route('tienda.confirmacion', [
            'slug' => $publicProfile->slug,
            'pedidoId' => $pedido->id,
        ]);
    }

    public function confirmacion(string $slug, int $pedidoId): Response
    {
        $publicProfile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)
            ->with(['items', 'publicProfile' => function ($query) {
                $query->withoutGlobalScope(OwnerScope::class);
            }, 'conversacion', 'user'])
            ->where('id', $pedidoId)
            ->where('cliente_id', Auth::id())
            ->where('public_profile_id', $publicProfile->id)
            ->firstOrFail();

        return Inertia::render('marketplace/Confirmacion', [
            'pedido' => $pedido,
            'tienda' => $pedido->publicProfile,
            'conversacion' => $pedido->conversacion,
            'vendedor' => [
                'name' => $pedido->user->name,
                'telefono' => $pedido->user->telefono,
            ],
        ]);
    }

    public function misPedidos(): Response
    {
        $userId = Auth::id();

        $compras = Pedido::withoutGlobalScope(OwnerScope::class)->with([
            'publicProfile' => function ($query) {
                $query->withoutGlobalScope(OwnerScope::class)->select('id', 'user_id', 'owner_id', 'title', 'slug');
            },
            'items',
            'conversacion',
        ])
            ->where('cliente_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $ventas = Pedido::with([
            'publicProfile' => function ($query) {
                $query->withoutGlobalScope(OwnerScope::class)->select('id', 'user_id', 'owner_id', 'title', 'slug');
            },
            'items',
            'conversacion',
            'cliente:id,name',
        ])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Debug: ensure relations are loaded
        foreach ($ventas as $pedido) {
            if (! $pedido->relationLoaded('publicProfile')) {
                $pedido->load('publicProfile');
            }
        }

        return Inertia::render('marketplace/MisPedidos', [
            'compras' => $compras,
            'ventas' => $ventas,
        ]);
    }

    public function verPedido(Pedido $pedido): Response
    {
        if ($pedido->cliente_id !== Auth::id() && $pedido->user_id !== Auth::id()) {
            abort(403);
        }

        $pedido->load([
            'items',
            'publicProfile' => function ($query) {
                $query->withoutGlobalScope(OwnerScope::class);
            },
            'conversacion' => function ($query) {
                $query->with(['mensajes' => function ($q) {
                    $q->with('sender:id,name,profile_photo_path')->orderBy('created_at', 'asc');
                }]);
            },
            'cliente:id,name',
        ]);

        return Inertia::render('marketplace/PedidoDetalle', [
            'pedido' => $pedido,
        ]);
    }

    public function estado(Pedido $pedido): array
    {
        if ($pedido->cliente_id !== Auth::id() && $pedido->user_id !== Auth::id()) {
            abort(403);
        }

        return [
            'estado' => $pedido->estado,
            'historial' => $this->getHistorialEstado($pedido),
        ];
    }

    private function getHistorialEstado(Pedido $pedido): array
    {
        $estados = [
            'pendiente' => ['label' => 'Pedido recibido', 'icon' => 'clock', 'color' => 'yellow'],
            'confirmado' => ['label' => 'Confirmado', 'icon' => 'check', 'color' => 'blue'],
            'preparando' => ['label' => 'En preparación', 'icon' => 'package', 'color' => 'orange'],
            'enviado' => ['label' => 'Enviado', 'icon' => 'truck', 'color' => 'purple'],
            'entregado' => ['label' => 'Entregado', 'icon' => 'check-circle', 'color' => 'green'],
            'cancelado' => ['label' => 'Cancelado', 'icon' => 'x', 'color' => 'red'],
        ];

        $pedidoEstado = array_search($pedido->estado, array_keys($estados));

        return collect($estados)->map(function ($item, $idx) use ($pedidoEstado) {
            return array_merge($item, [
                'completado' => $idx <= $pedidoEstado,
                'actual' => $idx === $pedidoEstado,
            ]);
        })->values()->toArray();
    }
}

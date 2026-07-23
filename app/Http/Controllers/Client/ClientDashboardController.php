<?php

namespace App\Http\Controllers\Client;

use App\Events\PedidoCreado;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Http\Exceptions\SlotUnavailableException;
use App\Mail\AppointmentBooked;
use App\Models\Almacen;
use App\Models\Appointment;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Entrega;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\NuevoPedidoNotification;
use App\Notifications\NuevoTicketNotification;
use App\Scopes\OwnerScope;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientDashboardController extends Controller
{
    /**
     * Display the client dashboard (catalog & orders).
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $clienteRecord = Cliente::withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $user->id)
            ->first();
        $ownerId = $clienteRecord?->owner_id ?? $user->getOwnerId();

        // 1. Get Owner / Business details and branding
        $owner = User::findOrFail($ownerId);
        $publicProfile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->first();

        // 2. Fetch Owner's active products
        $productosQuery = Producto::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->where('activo', true);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $productosQuery->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $productosQuery->where('categoria_id', $request->input('categoria_id'));
        }

        $productos = $productosQuery->with('categoria', 'providers', 'course')
            ->orderBy('nombre')
            ->get()
            ->map(function ($producto) {
                $profileSlug = null;
                if ($producto->public_profile_id) {
                    $profile = PublicProfile::withoutGlobalScope(OwnerScope::class)->find($producto->public_profile_id);
                    $profileSlug = $profile?->slug;
                }
                if (! $profileSlug && $producto->relationLoaded('categoria') && $producto->categoria?->public_profile_id) {
                    $profile = PublicProfile::withoutGlobalScope(OwnerScope::class)->find($producto->categoria->public_profile_id);
                    $profileSlug = $profile?->slug;
                }

                return [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'codigo' => $producto->codigo,
                    'descripcion' => $producto->descripcion,
                    'precio_venta' => (float) $producto->precio_venta,
                    'imagen' => $producto->imagen,
                    'categoria_id' => $producto->categoria_id,
                    'categoria' => $producto->categoria ? $producto->categoria->nombre : null,
                    'stock' => (float) $producto->inventarios()->sum('cantidad'),
                    'requires_appointment' => (bool) $producto->requires_appointment,
                    'duracion' => $producto->duracion,
                    'is_service' => (bool) $producto->is_service,
                    'course_id' => $producto->course_id,
                    'course_slug' => $producto->course?->slug,
                    'booking_slug' => $profileSlug,
                    'providers' => $producto->providers->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'photo' => $p->profilePhotoUrl(),
                    ]),
                ];
            });

        // 3. Fetch Owner's active categories
        $categorias = Categoria::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        // 4. Fetch Client's order history
        $pedidos = Pedido::withoutGlobalScope(OwnerScope::class)
            ->where('cliente_id', $user->id)
            ->with(['items'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        // 5. Fetch Client's booked appointments
        $citasCliente = Appointment::withoutGlobalScope(OwnerScope::class)
            ->where('client_id', $user->id)
            ->with(['producto'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 6. Fetch existing appointments to block booked time slots
        $appointments = Appointment::where('owner_id', $ownerId)
            ->whereIn('status', ['pendiente', 'confirmada'])
            ->get(['start_time', 'end_time', 'producto_id', 'provider_id']);

        // 7. Fetch Client's support tickets
        $tickets = collect();
        if ($clienteRecord) {
            $tickets = Ticket::withoutGlobalScope(OwnerScope::class)
                ->where('cliente_id', $clienteRecord->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return Inertia::render('Cliente', [
            'productos' => $productos,
            'categorias' => $categorias,
            'pedidos' => $pedidos,
            'citasCliente' => $citasCliente,
            'tickets' => $tickets,
            'filters' => $request->only(['search', 'categoria_id']),
            'business' => [
                'name' => $owner->dashboard_name ?: $owner->name,
                'logo' => $owner->businessLogoUrl(),
                'cover' => $owner->businessCoverUrl(),
                'primary_color' => $owner->primary_color ?: '#4f46e5',
                'secondary_color' => $owner->secondary_color ?: '#06b6d4',
                'phone' => $owner->telefono,
                'email' => $owner->email,
                'owner_id' => $owner->id,
            ],
            'store' => $publicProfile,
            'appointments' => $appointments,
        ]);
    }

    /**
     * Place a new order.
     */
    public function storeOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.producto_id' => [
                'required',
                Rule::exists('productos', 'id')->where(function ($query) use ($ownerId) {
                    $query->where('owner_id', $ownerId)
                        ->where('activo', true);
                }),
            ],
            'items.*.cantidad' => 'required|integer|min:1',
            'notas' => 'nullable|string',
            'nombre_cliente' => 'required|string|max:255',
            'telefono_cliente' => 'required|string|max:50',
            'direccion_cliente' => 'required|string|max:500',
            'metodo_pago' => 'nullable|string|max:100',
        ]);

        $user = Auth::user();
        $clienteRecord = Cliente::withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $user->id)
            ->first();
        $ownerId = $clienteRecord?->owner_id ?? $user->getOwnerId();

        try {
            $pedido = DB::transaction(function () use ($validated, $user, $ownerId, $clienteRecord) {
                // 1. Create the Pedido record
                $pedido = Pedido::create([
                    'owner_id' => $ownerId,
                    'business_id' => $ownerId,
                    'user_id' => $ownerId,
                    'cliente_id' => $user->id,
                    'numero_pedido' => Pedido::generarNumeroPedido(),
                    'estado' => 'pendiente',
                    'nombre_cliente' => $validated['nombre_cliente'],
                    'telefono_cliente' => $validated['telefono_cliente'],
                    'direccion_cliente' => $validated['direccion_cliente'],
                    'metodo_pago' => $validated['metodo_pago'] ?? 'efectivo',
                    'notas' => $validated['notas'] ?? null,
                ]);

                $subtotal = 0;

                // 2. Add items
                foreach ($validated['items'] as $item) {
                    $producto = Producto::query()
                        ->whereKey($item['producto_id'])
                        ->where('owner_id', $ownerId)
                        ->where('activo', true)
                        ->firstOrFail();
                    $cantidad = $item['cantidad'];

                    $precio = (float) $producto->precio_venta;
                    $itemSubtotal = $precio * $cantidad;
                    $subtotal += $itemSubtotal;

                    // Create Item with price snapshot
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

                // 3. Create a Venta (sale) linked to the owner's CRM client
                $venta = Venta::create([
                    'owner_id' => $ownerId,
                    'cliente_id' => $clienteRecord?->id,
                    'user_id' => $ownerId,
                    'numero' => 'PEDIDO-'.$pedido->numero_pedido,
                    'fecha' => now(),
                    'subtotal' => $subtotal,
                    'iva' => $impuesto,
                    'total' => $total,
                    'metodo_pago' => $validated['metodo_pago'] ?? 'efectivo',
                    'tipo_documento' => 'boleta',
                    'estado' => 'pendiente',
                    'notas' => 'Generado desde Pedido #'.$pedido->numero_pedido,
                ]);

                foreach ($validated['items'] as $item) {
                    $producto = Producto::query()
                        ->whereKey($item['producto_id'])
                        ->where('owner_id', $ownerId)
                        ->where('activo', true)
                        ->firstOrFail();
                    DetalleVenta::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $producto->id,
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => (float) $producto->precio_venta,
                        'subtotal' => (float) $producto->precio_venta * $item['cantidad'],
                    ]);
                }

                // 4. Automatically generate an Entrega record linked to the Venta
                $entrega = Entrega::create([
                    'owner_id' => $ownerId,
                    'venta_id' => $venta->id,
                    'cliente' => $pedido->nombre_cliente,
                    'direccion' => $pedido->direccion_cliente,
                    'fecha_entrega' => now()->addDays(1),
                    'estado' => 'pendiente',
                    'descripcion' => "Entrega para Pedido #{$pedido->numero_pedido}",
                    'notas' => $pedido->notas,
                ]);

                foreach ($validated['items'] as $item) {
                    $producto = Producto::withoutGlobalScope(OwnerScope::class)->findOrFail($item['producto_id']);
                    $entrega->items()->create([
                        'producto_id' => $producto->id,
                        'cantidad_pedida' => $item['cantidad'],
                        'cantidad_entregada' => $item['cantidad'],
                        'unidad_medida' => $producto->unidad_medida ?? 'unidad',
                        'owner_id' => $ownerId,
                    ]);
                }

                return $pedido;
            });

            // 4. Send Notifications and Dispatch Event (Fase 5)
            $vendedor = User::find($ownerId);
            if ($vendedor) {
                NotificationHelper::send($vendedor, new NuevoPedidoNotification($pedido));
            }
            PedidoCreado::dispatch($pedido);

            return redirect()->route('cliente.dashboard')->with('success', "Pedido #{$pedido->numero_pedido} realizado exitosamente.");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel an order.
     */
    public function cancelOrder(Pedido $pedido): RedirectResponse
    {
        // Security check
        if ($pedido->cliente_id !== Auth::id()) {
            abort(403);
        }

        if ($pedido->estado !== 'pendiente') {
            return back()->withErrors(['error' => 'Solo se pueden cancelar pedidos que estén en estado pendiente.']);
        }

        try {
            DB::transaction(function () use ($pedido) {
                // Restore Stock
                foreach ($pedido->items as $item) {
                    $producto = Producto::withoutGlobalScope(OwnerScope::class)->find($item->producto_id);
                    if ($producto) {
                        $firstInv = $producto->inventarios()->first();
                        if ($firstInv) {
                            $firstInv->increment('cantidad', $item->cantidad);
                        } else {
                            $almacen = Almacen::where('owner_id', $pedido->owner_id)->first();
                            if ($almacen) {
                                Inventario::create([
                                    'owner_id' => $pedido->owner_id,
                                    'producto_id' => $producto->id,
                                    'almacen_id' => $almacen->id,
                                    'cantidad' => $item->cantidad,
                                ]);
                            }
                        }
                    }
                }

                $pedido->update(['estado' => 'cancelado']);
            });

            return redirect()->route('cliente.dashboard')->with('success', 'Pedido cancelado y stock restablecido.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function storeAppointment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'start_time' => 'required|date',
            'provider_id' => 'nullable|exists:users,id',
        ]);

        $user = Auth::user();
        $producto = Producto::withoutGlobalScope(OwnerScope::class)->findOrFail($validated['producto_id']);

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = $startTime->copy()->addMinutes((int) ($producto->duracion ?? 30));

        $ownerId = $producto->owner_id ?? $user->getOwnerId();
        $providerId = $validated['provider_id'] ?? $ownerId;

        $appointmentService = app(AppointmentService::class);

        $appointment = DB::transaction(function () use (
            $providerId, $startTime, $endTime, $ownerId, $user, $producto, $appointmentService
        ) {
            if ($appointmentService->hasConflictLocked($providerId, $startTime, $endTime)) {
                throw new SlotUnavailableException(
                    'El profesional ya tiene una cita agendada en ese horario.'
                );
            }

            return Appointment::create([
                'owner_id' => $ownerId,
                'client_id' => $user->id,
                'provider_id' => $providerId,
                'producto_id' => $producto->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'pendiente',
                'payment_status' => 'pendiente',
                'amount_paid' => 0,
                'notes' => 'Agendado desde portal cliente',
            ]);
        });

        try {
            Mail::to($user->email)->send(new AppointmentBooked($appointment));
        } catch (\Exception $e) {
            // Silently fail
        }

        return redirect()->route('cliente.dashboard')->with('success', 'Cita agendada correctamente. Revisa la sección de pedidos.');
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'prioridad' => 'required|in:baja,media,alta,urgente',
        ]);

        $user = Auth::user();
        $ownerId = $user->getOwnerId();

        $clienteRecord = Cliente::withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $user->id)
            ->first();

        $ticket = Ticket::create([
            'owner_id' => $ownerId,
            'cliente_id' => $clienteRecord?->id,
            'titulo' => $validated['titulo'],
            'descripcion' => $validated['descripcion'] ?? '',
            'prioridad' => $validated['prioridad'],
            'estado' => 'abierto',
            'asignado_a' => null,
        ]);

        $owner = User::find($ownerId);
        if ($owner) {
            NotificationHelper::send($owner, new NuevoTicketNotification($ticket));
        }

        return redirect()->route('cliente.dashboard')->with('success', 'Ticket enviado correctamente.');
    }
}

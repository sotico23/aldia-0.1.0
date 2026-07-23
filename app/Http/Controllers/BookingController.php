<?php

namespace App\Http\Controllers;

use App\Helpers\NotificationHelper;
use App\Http\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Conversacion;
use App\Models\PaymentConfig;
use App\Models\PaymentSession;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use App\Models\WebpayTransaction;
use App\Notifications\NuevoPedidoNotification;
use App\Scopes\OwnerScope;
use App\Services\AppointmentService;
use App\Services\WebpayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function show($slug)
    {
        $profile = PublicProfile::withoutGlobalScopes()->where('slug', $slug)->firstOrFail();
        $services = Producto::withoutGlobalScope(OwnerScope::class)
            ->where('is_service', true)
            ->where('public_profile_id', $profile->id)
            ->where('activo', true)
            ->with(['providers' => function ($query) {
                $query->select('users.id', 'users.name', 'users.profile_photo_path');
            }])
            ->get();

        $paymentConfig = PaymentConfig::resolveForOwner($profile->user_id);

        return Inertia::render('appointments/Booking', [
            'profile' => $profile,
            'services' => $services,
            'paymentConfig' => $paymentConfig ? [
                'webpay_active' => (bool) ($paymentConfig->is_active && $paymentConfig->webpay_active),
                'paypal_active' => (bool) ($paymentConfig->is_active && $paymentConfig->paypal_active),
                'mercadopago_active' => (bool) ($paymentConfig->is_active && $paymentConfig->mercadopago_active),
            ] : [
                'webpay_active' => false,
                'paypal_active' => false,
                'mercadopago_active' => false,
            ],
        ]);
    }

    public function store(Request $request, $slug)
    {
        $profile = PublicProfile::withoutGlobalScopes()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'service_id' => 'required|exists:productos,id',
            'start_time' => 'required|date',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'payment_method' => 'required|in:message,webpay,paypal,mercadopago',
        ]);

        $service = Producto::withoutGlobalScope(OwnerScope::class)->findOrFail($validated['service_id']);

        // Tenant validation: ensure the service belongs to this business
        if ($service->owner_id !== $profile->user_id) {
            abort(403, 'Este servicio no pertenece a este negocio.');
        }

        $client = User::firstOrCreate(
            ['email' => $validated['client_email']],
            ['name' => $validated['client_name'], 'password' => bcrypt(str()->random(16))]
        );

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = $startTime->copy()->addMinutes((int) $service->duracion);

        $appointmentService = app(AppointmentService::class);

        $appointment = DB::transaction(function () use (
            $profile, $client, $service, $startTime, $endTime, $appointmentService
        ) {
            if ($appointmentService->hasConflictLocked(
                $profile->user_id,
                $startTime,
                $endTime
            )) {
                throw new SlotUnavailableException(
                    'El profesional ya tiene una cita agendada en ese horario.'
                );
            }

            return Appointment::create([
                'client_id' => $client->id,
                'provider_id' => $profile->user_id,
                'producto_id' => $service->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'pendiente',
                'payment_status' => 'pendiente',
                'amount_paid' => 0,
            ]);
        });

        if ($validated['payment_method'] === 'message') {
            $pedido = $this->createPedido($profile, $client, $service, $appointment, 'message');

            $conversacion = Conversacion::create([
                'public_profile_id' => $profile->id,
                'comprador_id' => $client->id,
                'vendedor_id' => $profile->user_id,
                'pedido_id' => $pedido->id,
                'titulo' => "Solicitud de servicio: {$service->nombre}",
            ]);

            $appointment->update(['notes' => "conversacion_id:{$conversacion->id}"]);

            $vendedor = User::find($profile->user_id);
            if ($vendedor) {
                NotificationHelper::send($vendedor, new NuevoPedidoNotification($pedido));
            }

            return redirect()->to("/tienda/{$profile->slug}")
                ->with('success', 'Tu solicitud fue enviada. El vendedor te contactará pronto.');
        }

        $pedido = $this->createPedido($profile, $client, $service, $appointment, $validated['payment_method']);

        $appointment->update(['notes' => "pedido_id:{$pedido->id}"]);

        if ($validated['payment_method'] === 'paypal') {
            return redirect()->route('paypal.pay', ['pedidoId' => $pedido->id]);
        }

        if ($validated['payment_method'] === 'mercadopago') {
            return redirect()->route('mercadopago.pay', ['pedidoId' => $pedido->id]);
        }

        if ($validated['payment_method'] === 'webpay') {
            return redirect()->route('booking.webpay-pay', ['slug' => $slug, 'pedido' => $pedido->id]);
        }

        return back()->with('success', 'Tu reserva se ha procesado correctamente.');
    }

    public function webpayPay($slug, Pedido $pedido, WebpayService $webpayService)
    {
        $profile = PublicProfile::withoutGlobalScopes()->where('slug', $slug)->firstOrFail();

        $buyOrder = 'BOOK-'.time().'-'.rand(100, 999);
        $sessionId = session()->getId();
        $returnUrl = route('webpay.callback');

        $response = $webpayService->createTransaction(
            $buyOrder,
            $sessionId,
            (float) $pedido->total,
            $returnUrl,
            $profile->user_id
        );

        $token = $response->getToken();

        WebpayTransaction::create([
            'owner_id' => $profile->user_id,
            'token' => $token,
            'amount' => $pedido->total,
            'status' => 'pending',
            'buy_order' => $buyOrder,
        ]);

        PaymentSession::create([
            'token' => $token,
            'buy_order' => $buyOrder,
            'business_id' => $profile->owner_id,
            'status' => 'pending',
            'gateway' => 'webpay',
            'amount' => $pedido->total,
            'metadata' => [
                'pedido_id' => $pedido->id,
            ],
            'expires_at' => now()->addHours(2),
        ]);

        return view('webpay.redirect', [
            'url' => $response->getUrl(),
            'token' => $token,
        ]);
    }

    private function createPedido($profile, $client, $service, $appointment, $metodoPago): Pedido
    {
        $pedido = Pedido::create([
            'user_id' => $profile->user_id,
            'owner_id' => $profile->owner_id,
            'public_profile_id' => $profile->id,
            'cliente_id' => $client->id,
            'numero_pedido' => Pedido::generarNumeroPedido(),
            'estado' => 'pendiente',
            'nombre_cliente' => $client->name,
            'metodo_pago' => $metodoPago,
            'subtotal' => $service->precio_venta,
            'impuesto' => 0,
            'total' => $service->precio_venta,
            'payment_data' => ['appointment_id' => $appointment->id],
        ]);

        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $service->id,
            'nombre_producto' => $service->nombre,
            'precio_unitario' => $service->precio_venta,
            'cantidad' => 1,
            'subtotal' => $service->precio_venta,
        ]);

        return $pedido;
    }
}

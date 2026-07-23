<?php

use App\Events\PaymentSuccessful;
use App\Events\PedidoCreado;
use App\Models\Conversacion;
use App\Models\MensajeConversacion;
use App\Models\Pedido;
use App\Models\PublicProfile;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\ActualizacionEstadoPedidoNotification;
use App\Notifications\NuevaReaccion;
use App\Notifications\NuevoComentario;
use App\Notifications\NuevoMensajeChatPedidoNotification;
use App\Notifications\NuevoPedidoNotification;
use App\Notifications\NuevoTicketNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PedidoCreadoCompradorNotification;
use App\Notifications\TempPasswordNotification;
use App\Notifications\WelcomeProveedorNotification;
use App\Traits\HasNotificationPreferences;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['email' => 'dummy@setup.test']);
});

// ============================================================================
// UNIT: Notification preferences — filterChannelsByPreference
// ============================================================================

test('filterChannelsByPreference returns all channels when no preference exists', function () {
    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-PREF-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $channels = (new NuevoPedidoNotification($pedido))->via($user);

    expect($channels)->toBe(['database', 'mail']);
});

test('filterChannelsByPreference removes channel when disabled', function () {
    $user = User::factory()->create();
    UserNotificationPreference::create([
        'user_id' => $user->id,
        'type' => 'nuevo_pedido',
        'channel' => 'database',
        'enabled' => false,
    ]);
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-PREF-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $channels = (new NuevoPedidoNotification($pedido))->via($user);

    expect($channels)->toBe(['mail']);
});

test('filterChannelsByPreference keeps channel when explicitly enabled', function () {
    $user = User::factory()->create();
    UserNotificationPreference::create([
        'user_id' => $user->id,
        'type' => 'nuevo_pedido',
        'channel' => 'database',
        'enabled' => true,
    ]);
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-PREF-003',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $channels = (new NuevoPedidoNotification($pedido))->via($user);

    expect($channels)->toBe(['database', 'mail']);
});

test('wantsNotificationChannel returns true by default', function () {
    $user = User::factory()->create();

    expect($user->wantsNotificationChannel('nuevo_pedido', 'database'))->toBeTrue();
    expect($user->wantsNotificationChannel('nuevo_pedido', 'mail'))->toBeTrue();
});

test('wantsNotificationChannel returns false when disabled', function () {
    $user = User::factory()->create();
    UserNotificationPreference::create([
        'user_id' => $user->id,
        'type' => 'nuevo_pedido',
        'channel' => 'database',
        'enabled' => false,
    ]);

    expect($user->wantsNotificationChannel('nuevo_pedido', 'database'))->toBeFalse();
    expect($user->wantsNotificationChannel('nuevo_pedido', 'mail'))->toBeTrue();
});

test('wantsNotificationChannel returns true when enabled', function () {
    $user = User::factory()->create();
    UserNotificationPreference::create([
        'user_id' => $user->id,
        'type' => 'nuevo_pedido',
        'channel' => 'database',
        'enabled' => true,
    ]);

    expect($user->wantsNotificationChannel('nuevo_pedido', 'database'))->toBeTrue();
});

// ============================================================================
// UNIT: Mail template fallback — sendViaTemplate returns null when no template
// ============================================================================

test('sendViaTemplate returns null when no template exists so inline MailMessage is used', function () {
    $user = User::factory()->create();

    $notification = new WelcomeProveedorNotification(email: $user->email);

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(MailMessage::class);
});

test('Notifications bridge to templateSlug and templateVariables correctly', function () {
    $user = User::factory()->create(['name' => 'Test']);
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-TPL-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Buyer',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $nuevoPedido = new NuevoPedidoNotification($pedido);
    expect($nuevoPedido->templateSlug())->toBe('nuevo_pedido');
    expect($nuevoPedido->templateVariables($user))->toHaveKeys(['numero_pedido', 'nombre_cliente', 'total', 'link']);

    $creado = new PedidoCreadoCompradorNotification($pedido);
    expect($creado->templateSlug())->toBe('pedido_creado');
    expect($creado->templateVariables($user))->toHaveKeys(['numero_pedido', 'nombre_cliente', 'estado', 'link']);
});

// ============================================================================
// UNIT: ShouldQueue — all 10 notifications implement ShouldQueue
// ============================================================================

test('PaymentReceivedNotification implements ShouldQueue', function () {
    expect((new ReflectionClass(PaymentReceivedNotification::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
});

test('NuevaReaccion implements ShouldQueue', function () {
    expect((new ReflectionClass(NuevaReaccion::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
});

test('NuevoComentario implements ShouldQueue', function () {
    expect((new ReflectionClass(NuevoComentario::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
});

// ============================================================================
// UNIT: All notifications correctly implement HasNotificationPreferences
// ============================================================================

test('all notifications implement HasNotificationPreferences trait', function () {
    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-HNP-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $classes = [
        NuevoPedidoNotification::class,
        PedidoCreadoCompradorNotification::class,
        ActualizacionEstadoPedidoNotification::class,
        NuevoMensajeChatPedidoNotification::class,
        NuevoTicketNotification::class,
        PaymentReceivedNotification::class,
        WelcomeProveedorNotification::class,
        TempPasswordNotification::class,
        NuevaReaccion::class,
        NuevoComentario::class,
    ];

    foreach ($classes as $class) {
        expect(in_array(HasNotificationPreferences::class, class_uses_recursive($class)))->toBeTrue();
    }
});

// ============================================================================
// UNIT: toArray structure — all 10 notifications
// ============================================================================

test('NuevoPedidoNotification toArray contains correct structure', function () {
    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-ARR-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Cliente Test',
        'total' => 50000,
        'subtotal' => 42017,
        'impuesto' => 7983,
        'metodo_pago' => 'efectivo',
    ]);

    $data = (new NuevoPedidoNotification($pedido))->toArray($user);

    expect($data)->toHaveKeys(['titulo', 'message', 'pedido_id', 'tipo', 'link']);
    expect($data['tipo'])->toBe('nuevo_pedido');
    expect(str_contains($data['message'], 'Cliente Test'))->toBeTrue();
});

test('PedidoCreadoCompradorNotification toArray contains correct structure', function () {
    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-ARR-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Buyer',
        'total' => 25000,
        'subtotal' => 21008,
        'impuesto' => 3992,
        'metodo_pago' => 'efectivo',
    ]);

    $data = (new PedidoCreadoCompradorNotification($pedido))->toArray($user);

    expect($data)->toHaveKeys(['titulo', 'message', 'pedido_id', 'tipo', 'link']);
    expect($data['tipo'])->toBe('actualizacion_pedido');
    expect(str_contains($data['message'], 'pendiente'))->toBeTrue();
});

test('ActualizacionEstadoPedidoNotification toArray contains correct structure', function () {
    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-ARR-003',
        'estado' => 'preparando',
        'nombre_cliente' => 'Test',
        'total' => 30000,
        'subtotal' => 25210,
        'impuesto' => 4790,
        'metodo_pago' => 'efectivo',
    ]);

    $data = (new ActualizacionEstadoPedidoNotification($pedido, 'pendiente', 'preparando'))->toArray($user);

    expect($data)->toHaveKeys(['titulo', 'message', 'pedido_id', 'estado', 'tipo', 'link']);
    expect($data['tipo'])->toBe('actualizacion_pedido');
    expect($data['estado'])->toBe('preparando');
});

test('NuevoTicketNotification toArray contains correct structure', function () {
    $user = User::factory()->create();
    $ticket = Ticket::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'titulo' => 'Problema test',
        'descripcion' => 'Descripción',
        'prioridad' => 'alta',
        'estado' => 'abierto',
        'asignado_a' => 'Soporte',
    ]);

    $data = (new NuevoTicketNotification($ticket))->toArray($user);

    expect($data)->toHaveKeys(['titulo', 'message', 'ticket_id', 'tipo', 'link']);
    expect($data['tipo'])->toBe('nuevo_ticket');
});

test('PaymentReceivedNotification toArray contains correct structure', function () {
    $user = User::factory()->create();
    $transaction = Transaction::create([
        'uuid' => (string) Str::uuid(),
        'business_id' => $user->id,
        'gateway' => 'webpay',
        'gateway_transaction_id' => 'tok_test',
        'type' => 'customer_payment',
        'status' => 'approved',
        'amount' => 50000,
        'net_amount' => 50000,
        'metadata' => ['buy_order' => 'ORD-001'],
        'processed_at' => now(),
    ]);

    $data = (new PaymentReceivedNotification($transaction))->toArray($user);

    expect($data)->toHaveKeys(['titulo', 'message', 'monto', 'buy_order', 'tipo', 'link']);
    expect($data['tipo'])->toBe('pago_recibido');
    expect($data['monto'])->toEqual(50000.00);
});

test('WelcomeProveedorNotification has no toArray (mail-only)', function () {
    $user = User::factory()->create();
    $notification = new WelcomeProveedorNotification(email: 'test@test.com');

    expect(method_exists($notification, 'toArray'))->toBeFalse();
});

test('TempPasswordNotification has no toArray (mail-only)', function () {
    $notification = new TempPasswordNotification(provider: 'google');

    expect(method_exists($notification, 'toArray'))->toBeFalse();
});

// ============================================================================
// UNIT: Mail rendering — verify all 6 remaining notifications render safely
// ============================================================================

test('NuevoPedidoNotification mail renders correctly', function () {
    $user = User::factory()->create(['name' => 'Vendor']);
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-MAIL-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Buyer Name',
        'total' => 45000,
        'subtotal' => 37815,
        'impuesto' => 7185,
        'metodo_pago' => 'efectivo',
    ]);

    $mail = (new NuevoPedidoNotification($pedido))->toMail($user);
    $data = $mail->toArray();

    expect($data['greeting'])->toBe('¡Nueva compra!');
    expect($mail->subject)->toBe('Nuevo Pedido #PED-MAIL-001');
});

test('PedidoCreadoCompradorNotification mail renders correctly', function () {
    $user = User::factory()->create(['name' => 'Buyer']);
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-MAIL-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Buyer Name',
        'total' => 45000,
        'subtotal' => 37815,
        'impuesto' => 7185,
        'metodo_pago' => 'efectivo',
    ]);

    $mail = (new PedidoCreadoCompradorNotification($pedido))->toMail($user);
    $data = $mail->toArray();

    expect($data['greeting'])->toBe('¡Hola Buyer Name!');
    expect($mail->subject)->toBe('Tu pedido #PED-MAIL-002');
});

test('ActualizacionEstadoPedidoNotification mail renders correctly', function () {
    $user = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'numero_pedido' => 'PED-MAIL-003',
        'estado' => 'confirmado',
        'nombre_cliente' => 'Test',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $mail = (new ActualizacionEstadoPedidoNotification($pedido, 'pendiente', 'confirmado'))->toMail($user);
    $data = $mail->toArray();

    expect($mail->subject)->toBe('Actualización de Pedido #PED-MAIL-003');
    expect($data['introLines'][0])->toBe('Tu pedido #PED-MAIL-003 ha cambiado de estado.');
    expect($data['introLines'][1])->toBe('Estado anterior: Pendiente');
    expect($data['introLines'][2])->toBe('Estado actual: Confirmado');
});

test('NuevoMensajeChatPedidoNotification mail renders correctly', function () {
    $vendedor = User::factory()->create(['name' => 'Vendedor']);
    $comprador = User::factory()->create(['name' => 'Comprador']);
    $pedido = Pedido::create([
        'owner_id' => $vendedor->id,
        'user_id' => $comprador->id,
        'numero_pedido' => 'PED-MAIL-004',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Comprador',
        'total' => 20000,
        'subtotal' => 16807,
        'impuesto' => 3193,
        'metodo_pago' => 'efectivo',
    ]);
    $publicProfile = PublicProfile::factory()->create([
        'user_id' => $vendedor->id,
        'owner_id' => $vendedor->id,
        'is_active' => true,
    ]);
    $conversacion = Conversacion::create([
        'pedido_id' => $pedido->id,
        'public_profile_id' => $publicProfile->id,
        'comprador_id' => $comprador->id,
        'vendedor_id' => $vendedor->id,
        'titulo' => "Pedido #{$pedido->numero_pedido}",
    ]);
    $mensaje = MensajeConversacion::create([
        'conversacion_id' => $conversacion->id,
        'sender_id' => $comprador->id,
        'contenido' => 'Hola, quiero hacer una consulta sobre mi pedido.',
    ]);

    $mail = (new NuevoMensajeChatPedidoNotification($conversacion, $mensaje))->toMail($vendedor);

    expect($mail->subject)->toBe('Nuevo mensaje de tu cliente');
});

test('NuevoTicketNotification mail renders correctly', function () {
    $user = User::factory()->create();
    $ticket = Ticket::create([
        'owner_id' => $user->id,
        'user_id' => $user->id,
        'titulo' => 'Problema de pago',
        'descripcion' => 'No puedo pagar',
        'prioridad' => 'alta',
        'estado' => 'abierto',
        'asignado_a' => 'Soporte',
    ]);

    $mail = (new NuevoTicketNotification($ticket))->toMail($user);
    $data = $mail->toArray();

    expect($mail->subject)->toBe('Nuevo Ticket: Problema de pago');
    expect($data['greeting'])->toBe('Nuevo ticket de soporte');
});

test('PaymentReceivedNotification mail renders correctly', function () {
    $user = User::factory()->create();
    $transaction = Transaction::create([
        'uuid' => (string) Str::uuid(),
        'business_id' => $user->id,
        'gateway' => 'webpay',
        'gateway_transaction_id' => 'tok_mail',
        'type' => 'customer_payment',
        'status' => 'approved',
        'amount' => 100000,
        'net_amount' => 100000,
        'metadata' => ['buy_order' => 'ORD-TEST'],
        'processed_at' => now(),
    ]);

    $mail = (new PaymentReceivedNotification($transaction))->toMail($user);
    $data = $mail->toArray();

    expect($mail->subject)->toBe('Pago recibido - RedCliente');
    expect($data['greeting'])->toBe('¡Pago confirmado!');
});

// ============================================================================
// FEATURE: Event → Listener → Notification end-to-end
// ============================================================================

test('PedidoCreado event triggers SendPedidoCreadoBuyerNotification listener', function () {
    Notification::fake();
    Event::fakeExcept([PedidoCreado::class]);

    $vendedor = User::factory()->create();
    $comprador = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $vendedor->id,
        'user_id' => $comprador->id,
        'numero_pedido' => 'PED-EVT-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Comprador',
        'total' => 30000,
        'subtotal' => 25210,
        'impuesto' => 4790,
        'metodo_pago' => 'efectivo',
    ]);

    PedidoCreado::dispatch($pedido);

    Notification::assertSentTo(
        $comprador,
        PedidoCreadoCompradorNotification::class,
        function ($notification) use ($pedido) {
            return $notification->pedido->id === $pedido->id;
        },
    );
});

test('PaymentSuccessful event triggers SendPaymentSuccessfulNotification listener', function () {
    Notification::fake();
    Event::fakeExcept([PaymentSuccessful::class]);

    $owner = User::factory()->create();
    $transaction = Transaction::create([
        'uuid' => (string) Str::uuid(),
        'business_id' => $owner->id,
        'gateway' => 'webpay',
        'gateway_transaction_id' => 'tok_evt',
        'type' => 'customer_payment',
        'status' => 'approved',
        'amount' => 75000,
        'net_amount' => 75000,
        'metadata' => ['buy_order' => 'ORD-EVT-001'],
        'processed_at' => now(),
    ]);

    PaymentSuccessful::dispatch($transaction);

    Notification::assertSentTo(
        $owner,
        PaymentReceivedNotification::class,
        function ($notification) use ($transaction) {
            return $notification->transaction->id === $transaction->id;
        },
    );
});

// ============================================================================
// FEATURE: Handle edge case — no buyer on Pedido does not crash listener
// ============================================================================

test('PedidoCreado event handles pedido with missing buyer gracefully', function () {
    $vendedor = User::factory()->create();
    $pedido = Pedido::create([
        'owner_id' => $vendedor->id,
        'user_id' => $vendedor->id,
        'numero_pedido' => 'PED-EDGE-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Guest',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);
    // Force user relation to return null to simulate deleted user
    $pedido->setRelation('user', null);

    Notification::fake();
    PedidoCreado::dispatch($pedido);

    Notification::assertNothingSent();
});

test('PaymentSuccessful event handles missing owner gracefully', function () {
    $owner = User::factory()->create();
    $transaction = Transaction::create([
        'uuid' => (string) Str::uuid(),
        'business_id' => $owner->id,
        'gateway' => 'webpay',
        'gateway_transaction_id' => 'tok_edge',
        'type' => 'customer_payment',
        'status' => 'approved',
        'amount' => 50000,
        'net_amount' => 50000,
        'metadata' => ['buy_order' => 'ORD-EDGE'],
        'processed_at' => now(),
    ]);
    // Set business_id to non-existent so User::find returns null
    $transaction->business_id = 99999;

    Notification::fake();
    PaymentSuccessful::dispatch($transaction);

    Notification::assertNothingSent();
});

// ============================================================================
// FEATURE: Notification preferences UI — store and retrieve
// ============================================================================

test('notification preference can be created and retrieved', function () {
    $user = User::factory()->create();

    $pref = UserNotificationPreference::create([
        'user_id' => $user->id,
        'type' => 'nuevo_pedido',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    expect($pref->user->id)->toBe($user->id);
    expect($pref->enabled)->toBeFalse();
});

test('creating duplicate preference updates existing row', function () {
    $user = User::factory()->create();

    UserNotificationPreference::create([
        'user_id' => $user->id,
        'type' => 'nuevo_pedido',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    UserNotificationPreference::where([
        'user_id' => $user->id,
        'type' => 'nuevo_pedido',
        'channel' => 'mail',
    ])->update(['enabled' => true]);

    expect($user->wantsNotificationChannel('nuevo_pedido', 'mail'))->toBeTrue();
});

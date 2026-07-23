<?php

use App\Models\Conversacion;
use App\Models\Conversation;
use App\Models\MensajeConversacion;
use App\Models\Message;
use App\Models\Pedido;
use App\Models\PublicProfile;
use App\Models\User;
use App\Notifications\NuevoMensajeChatPedidoNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('comercial.oportunidades.viewAny', 'web');
    $this->dummy = User::factory()->create(['email' => 'dummy@setup.test']);
    $this->dummy->givePermissionTo('comercial.oportunidades.viewAny');
});

// ============================================================================
// ConversacionPedidoController - Chat de Pedidos (Comprador ↔ Vendedor)
// ============================================================================

test('comprador puede ver mensajes de su conversacion de pedido', function () {
    $comprador = User::factory()->create();
    $comprador->givePermissionTo('comercial.oportunidades.viewAny');
    $vendedor = User::factory()->create();
    $vendedor->givePermissionTo('comercial.oportunidades.viewAny');
    $profile = PublicProfile::factory()->create(['user_id' => $vendedor->id, 'owner_id' => $vendedor->id]);

    $pedido = Pedido::create([
        'owner_id' => $vendedor->id,
        'user_id' => $vendedor->id,
        'cliente_id' => $comprador->id,
        'numero_pedido' => 'TEST-VER-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Comprador',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $conversacion = Conversacion::create([
        'pedido_id' => $pedido->id,
        'public_profile_id' => $profile->id,
        'comprador_id' => $comprador->id,
        'vendedor_id' => $vendedor->id,
        'titulo' => "Pedido #{$pedido->numero_pedido}",
    ]);

    MensajeConversacion::create([
        'conversacion_id' => $conversacion->id,
        'sender_id' => $vendedor->id,
        'receiver_id' => $comprador->id,
        'contenido' => 'Gracias por tu compra!',
    ]);

    $response = $this->actingAs($comprador)
        ->getJson(route('conversaciones-pedidos.mensajes', $conversacion));

    $response->assertOk();
    $response->assertJsonStructure(['mensajes']);
    expect($response->json('mensajes'))->toHaveCount(1);
    expect($response->json('mensajes.0.contenido'))->toBe('Gracias por tu compra!');
    expect($response->json('mensajes.0.sender_id'))->toBe($vendedor->id);
});

test('vendedor puede ver mensajes de su conversacion de pedido', function () {
    $comprador = User::factory()->create();
    $comprador->givePermissionTo('comercial.oportunidades.viewAny');
    $vendedor = User::factory()->create();
    $vendedor->givePermissionTo('comercial.oportunidades.viewAny');
    $profile = PublicProfile::factory()->create(['user_id' => $vendedor->id, 'owner_id' => $vendedor->id]);

    $pedido = Pedido::create([
        'owner_id' => $vendedor->id,
        'user_id' => $vendedor->id,
        'cliente_id' => $comprador->id,
        'numero_pedido' => 'TEST-VER-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Comprador',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $conversacion = Conversacion::create([
        'pedido_id' => $pedido->id,
        'public_profile_id' => $profile->id,
        'comprador_id' => $comprador->id,
        'vendedor_id' => $vendedor->id,
        'titulo' => "Pedido #{$pedido->numero_pedido}",
    ]);

    MensajeConversacion::create([
        'conversacion_id' => $conversacion->id,
        'sender_id' => $comprador->id,
        'receiver_id' => $vendedor->id,
        'contenido' => 'Consulta sobre mi pedido',
    ]);

    $response = $this->actingAs($vendedor)
        ->getJson(route('conversaciones-pedidos.mensajes', $conversacion));

    $response->assertOk();
    expect($response->json('mensajes'))->toHaveCount(1);
    expect($response->json('mensajes.0.contenido'))->toBe('Consulta sobre mi pedido');
});

test('usuario no participante recibe 403 al consultar mensajes de pedido', function () {
    $comprador = User::factory()->create();
    $comprador->givePermissionTo('comercial.oportunidades.viewAny');
    $vendedor = User::factory()->create();
    $vendedor->givePermissionTo('comercial.oportunidades.viewAny');
    $intruso = User::factory()->create();
    $intruso->givePermissionTo('comercial.oportunidades.viewAny');
    $profile = PublicProfile::factory()->create(['user_id' => $vendedor->id, 'owner_id' => $vendedor->id]);

    $pedido = Pedido::create([
        'owner_id' => $vendedor->id,
        'user_id' => $vendedor->id,
        'cliente_id' => $comprador->id,
        'numero_pedido' => 'TEST-NO-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Comprador',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $conversacion = Conversacion::create([
        'pedido_id' => $pedido->id,
        'public_profile_id' => $profile->id,
        'comprador_id' => $comprador->id,
        'vendedor_id' => $vendedor->id,
        'titulo' => "Pedido #{$pedido->numero_pedido}",
    ]);

    $response = $this->actingAs($intruso)
        ->getJson(route('conversaciones-pedidos.mensajes', $conversacion));

    $response->assertForbidden();
});

test('comprador puede enviar mensaje en conversacion de pedido', function () {
    Notification::fake();
    $comprador = User::factory()->create();
    $comprador->givePermissionTo('comercial.oportunidades.viewAny');
    $vendedor = User::factory()->create();
    $vendedor->givePermissionTo('comercial.oportunidades.viewAny');
    $profile = PublicProfile::factory()->create(['user_id' => $vendedor->id, 'owner_id' => $vendedor->id]);

    $pedido = Pedido::create([
        'owner_id' => $vendedor->id,
        'user_id' => $vendedor->id,
        'cliente_id' => $comprador->id,
        'numero_pedido' => 'TEST-ENV-001',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Comprador',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $conversacion = Conversacion::create([
        'pedido_id' => $pedido->id,
        'public_profile_id' => $profile->id,
        'comprador_id' => $comprador->id,
        'vendedor_id' => $vendedor->id,
        'titulo' => "Pedido #{$pedido->numero_pedido}",
    ]);

    $response = $this->actingAs($comprador)
        ->postJson(route('conversaciones-pedidos.enviar', $conversacion), [
            'contenido' => 'Hola vendedor!',
        ]);

    $response->assertCreated();
    $response->assertJsonStructure(['mensaje' => ['id', 'sender_id', 'contenido', 'sender']]);
    expect($response->json('mensaje.contenido'))->toBe('Hola vendedor!');
    expect($response->json('mensaje.sender_id'))->toBe($comprador->id);

    $this->assertDatabaseHas('mensajes_conversacion', [
        'conversacion_id' => $conversacion->id,
        'sender_id' => $comprador->id,
        'receiver_id' => $vendedor->id,
        'contenido' => 'Hola vendedor!',
    ]);

    Notification::assertSentTo(
        [$vendedor],
        NuevoMensajeChatPedidoNotification::class
    );
});

test('vendedor puede enviar mensaje en conversacion de pedido', function () {
    Notification::fake();
    $comprador = User::factory()->create();
    $comprador->givePermissionTo('comercial.oportunidades.viewAny');
    $vendedor = User::factory()->create();
    $vendedor->givePermissionTo('comercial.oportunidades.viewAny');
    $profile = PublicProfile::factory()->create(['user_id' => $vendedor->id, 'owner_id' => $vendedor->id]);

    $pedido = Pedido::create([
        'owner_id' => $vendedor->id,
        'user_id' => $vendedor->id,
        'cliente_id' => $comprador->id,
        'numero_pedido' => 'TEST-ENV-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Comprador',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $conversacion = Conversacion::create([
        'pedido_id' => $pedido->id,
        'public_profile_id' => $profile->id,
        'comprador_id' => $comprador->id,
        'vendedor_id' => $vendedor->id,
        'titulo' => "Pedido #{$pedido->numero_pedido}",
    ]);

    $response = $this->actingAs($vendedor)
        ->postJson(route('conversaciones-pedidos.enviar', $conversacion), [
            'contenido' => 'Gracias por tu compra!',
        ]);

    $response->assertCreated();
    expect($response->json('mensaje.contenido'))->toBe('Gracias por tu compra!');
    expect($response->json('mensaje.sender_id'))->toBe($vendedor->id);

    $this->assertDatabaseHas('mensajes_conversacion', [
        'conversacion_id' => $conversacion->id,
        'sender_id' => $vendedor->id,
        'receiver_id' => $comprador->id,
        'contenido' => 'Gracias por tu compra!',
    ]);

    Notification::assertSentTo(
        [$comprador],
        NuevoMensajeChatPedidoNotification::class
    );
});

test('usuario no participante recibe 403 al enviar mensaje en pedido', function () {
    $comprador = User::factory()->create();
    $comprador->givePermissionTo('comercial.oportunidades.viewAny');
    $vendedor = User::factory()->create();
    $vendedor->givePermissionTo('comercial.oportunidades.viewAny');
    $intruso = User::factory()->create();
    $intruso->givePermissionTo('comercial.oportunidades.viewAny');
    $profile = PublicProfile::factory()->create(['user_id' => $vendedor->id, 'owner_id' => $vendedor->id]);

    $pedido = Pedido::create([
        'owner_id' => $vendedor->id,
        'user_id' => $vendedor->id,
        'cliente_id' => $comprador->id,
        'numero_pedido' => 'TEST-NO-002',
        'estado' => 'pendiente',
        'nombre_cliente' => 'Comprador',
        'total' => 10000,
        'subtotal' => 8403,
        'impuesto' => 1597,
        'metodo_pago' => 'efectivo',
    ]);

    $conversacion = Conversacion::create([
        'pedido_id' => $pedido->id,
        'public_profile_id' => $profile->id,
        'comprador_id' => $comprador->id,
        'vendedor_id' => $vendedor->id,
        'titulo' => "Pedido #{$pedido->numero_pedido}",
    ]);

    $response = $this->actingAs($intruso)
        ->postJson(route('conversaciones-pedidos.enviar', $conversacion), [
            'contenido' => 'Mensaje intruso',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('mensajes_conversacion', [
        'conversacion_id' => $conversacion->id,
        'contenido' => 'Mensaje intruso',
    ]);
});

test('invitados no pueden consultar mensajes de pedido', function () {
    $response = $this->getJson(route('conversaciones-pedidos.mensajes', 1));
    $response->assertUnauthorized();
});

test('invitados no pueden enviar mensaje en pedido', function () {
    $response = $this->postJson(route('conversaciones-pedidos.enviar', 1), [
        'contenido' => 'test',
    ]);
    $response->assertUnauthorized();
});

// ============================================================================
// ChatController - Chat General del Marketplace
// ============================================================================

test('comprador puede ver su conversacion general de marketplace', function () {
    $comprador = User::factory()->create();
    $comprador->givePermissionTo('comercial.oportunidades.viewAny');
    $vendedor = User::factory()->create();
    $vendedor->givePermissionTo('comercial.oportunidades.viewAny');
    $profile = PublicProfile::factory()->create(['user_id' => $vendedor->id, 'owner_id' => $vendedor->id]);

    $conversation = Conversation::create([
        'buyer_id' => $comprador->id,
        'store_profile_id' => $profile->id,
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $vendedor->id,
        'body' => 'Bienvenido a la tienda!',
    ]);

    $response = $this->actingAs($comprador)
        ->getJson(route('chat.index'));

    $response->assertOk();
});

test('comprador puede enviar mensaje en chat general', function () {
    $comprador = User::factory()->create();
    $comprador->givePermissionTo('comercial.oportunidades.viewAny');
    $vendedor = User::factory()->create();
    $vendedor->givePermissionTo('comercial.oportunidades.viewAny');
    $profile = PublicProfile::factory()->create(['user_id' => $vendedor->id, 'owner_id' => $vendedor->id]);

    $conversation = Conversation::create([
        'buyer_id' => $comprador->id,
        'store_profile_id' => $profile->id,
    ]);

    $response = $this->actingAs($comprador)
        ->post(route('chat.send', $conversation), [
            'body' => 'Hola, tengo una consulta',
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $comprador->id,
        'body' => 'Hola, tengo una consulta',
    ]);
});

test('usuario no participante recibe 403 al enviar mensaje en chat general', function () {
    $comprador = User::factory()->create();
    $comprador->givePermissionTo('comercial.oportunidades.viewAny');
    $vendedor = User::factory()->create();
    $vendedor->givePermissionTo('comercial.oportunidades.viewAny');
    $intruso = User::factory()->create();
    $intruso->givePermissionTo('comercial.oportunidades.viewAny');
    $profile = PublicProfile::factory()->create(['user_id' => $vendedor->id, 'owner_id' => $vendedor->id]);

    $conversation = Conversation::create([
        'buyer_id' => $comprador->id,
        'store_profile_id' => $profile->id,
    ]);

    $response = $this->actingAs($intruso)
        ->postJson(route('chat.send', $conversation), [
            'body' => 'Mensaje intruso',
        ]);

    $response->assertForbidden();
});

test('invitados no pueden acceder al chat general', function () {
    $response = $this->getJson(route('chat.index'));
    $response->assertUnauthorized();
});

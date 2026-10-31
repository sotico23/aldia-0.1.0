<?php

use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Permission::findOrCreate('general.comunidad.edit', 'web');
});

/**
 * Crea el usuario raíz (owner) de un tenant con acceso a mensajería.
 */
function internalMessageOwner(): User
{
    $owner = User::factory()->create();
    $owner->syncRoles(['Administrador']);
    $owner->givePermissionTo('general.comunidad.edit');

    return $owner;
}

/**
 * Crea un usuario dentro del tenant del owner indicado.
 */
function internalMessageTenantUser(User $owner): User
{
    $user = User::factory()->create(['creator_id' => $owner->id]);
    $user->syncRoles(['Administrador']);
    $user->givePermissionTo('general.comunidad.edit');

    return $user;
}

test('el modelo Mensaje usa las columnas reales y resuelve sus relaciones', function () {
    $sender = internalMessageOwner();
    $receiver = internalMessageTenantUser($sender);

    $this->actingAs($sender);

    $mensaje = Mensaje::create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'contenido' => 'Contenido de prueba',
    ]);

    expect($mensaje->owner_id)->toBe($sender->getOwnerId())
        ->and($mensaje->sender->is($sender))->toBeTrue()
        ->and($mensaje->receiver->is($receiver))->toBeTrue()
        ->and($mensaje->leido)->toBeFalse()
        ->and($mensaje->getCommonFields()['data']['contenido'])->toBe('Contenido de prueba');
});

test('un usuario puede enviar un mensaje interno a otro usuario de su empresa', function () {
    $sender = internalMessageOwner();
    $receiver = internalMessageTenantUser($sender);

    $response = $this->actingAs($sender)->postJson(route('mensajes.enviar'), [
        'receiver_id' => $receiver->id,
        'contenido' => 'Hola, ¿cómo va todo?',
    ]);

    $response->assertCreated()
        ->assertJsonPath('mensaje.sender_id', $sender->id)
        ->assertJsonPath('mensaje.receiver_id', $receiver->id)
        ->assertJsonPath('mensaje.contenido', 'Hola, ¿cómo va todo?');

    $this->assertDatabaseHas('mensajes', [
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'contenido' => 'Hola, ¿cómo va todo?',
        'owner_id' => $sender->getOwnerId(),
        'leido' => false,
    ]);

    // El observador replica el mensaje en el chat unificado.
    $this->assertDatabaseHas('communication_messages', [
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'content' => 'Hola, ¿cómo va todo?',
    ]);
});

test('el receptor puede listar la conversacion y el mensaje queda como leido', function () {
    $sender = internalMessageOwner();
    $receiver = internalMessageTenantUser($sender);

    $mensaje = Mensaje::create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'contenido' => 'Mensaje pendiente',
    ]);

    $response = $this->actingAs($receiver)
        ->getJson(route('mensajes.conversacion', $sender->id));

    $response->assertOk()
        ->assertJsonPath('mensajes.0.id', $mensaje->id)
        ->assertJsonPath('mensajes.0.contenido', 'Mensaje pendiente')
        ->assertJsonPath('mensajes.0.sender.name', $sender->name)
        ->assertJsonPath('mensajes.0.receiver.name', $receiver->name)
        ->assertJsonPath('otro_usuario.id', $sender->id);

    expect($mensaje->fresh()->leido)->toBeTrue();
});

test('el listado de conversaciones agrupa por interlocutor y cuenta no leidos', function () {
    $sender = internalMessageOwner();
    $receiver = internalMessageTenantUser($sender);

    Mensaje::create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'contenido' => 'Primer mensaje',
    ]);

    $this->travel(1)->seconds();

    Mensaje::create([
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'contenido' => 'Segundo mensaje',
    ]);

    $response = $this->actingAs($sender)->getJson(route('mensajes.index'));

    $response->assertOk()
        ->assertJsonPath('conversaciones.0.usuario_id', $receiver->id)
        ->assertJsonPath('conversaciones.0.ultimo_mensaje', 'Segundo mensaje')
        ->assertJsonPath('conversaciones.0.sin_leer', 0)
        ->assertJsonPath('total_sin_leer', 0);
});

test('un usuario no puede ver la conversacion de otra empresa', function () {
    $ownerA = internalMessageOwner();
    $ownerB = internalMessageOwner();
    $other = internalMessageTenantUser($ownerB);

    $this->actingAs($ownerA)
        ->getJson(route('mensajes.conversacion', $other->id))
        ->assertNotFound();
});

test('un usuario no puede enviar mensajes a otra empresa', function () {
    $ownerA = internalMessageOwner();
    $ownerB = internalMessageOwner();
    $other = internalMessageTenantUser($ownerB);

    $this->actingAs($ownerA)->postJson(route('mensajes.enviar'), [
        'receiver_id' => $other->id,
        'contenido' => 'Intento cross-tenant',
    ])->assertNotFound();

    $this->assertDatabaseMissing('mensajes', [
        'sender_id' => $ownerA->id,
        'receiver_id' => $other->id,
    ]);
});

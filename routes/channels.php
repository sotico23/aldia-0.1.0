<?php

use App\Models\Conversacion;
use App\Models\Conversation;
use App\Models\PublicProfile;
use App\Scopes\OwnerScope;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversacion.{id}', function ($user, $id) {
    $conversacion = Conversacion::find($id);
    if (! $conversacion) {
        return false;
    }

    return (int) $user->id === (int) $conversacion->comprador_id
        || (int) $user->id === (int) $conversacion->vendedor_id;
});

Broadcast::channel('conversation.{id}', function ($user, $id) {
    $conversation = Conversation::find($id);
    if (! $conversation) {
        return false;
    }

    $profile = PublicProfile::withoutGlobalScope(OwnerScope::class)->find($conversation->store_profile_id);

    return (int) $user->id === (int) $conversation->buyer_id
        || ($profile && (int) $user->id === (int) $profile->user_id);
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('communication.internal.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('presence-conversation.{id}', function ($user, $id) {
    $conversacion = Conversacion::find($id);
    if ($conversacion) {
        $allowed = (int) $user->id === (int) $conversacion->comprador_id
            || (int) $user->id === (int) $conversacion->vendedor_id;
        if ($allowed) {
            return ['id' => $user->id, 'name' => $user->name];
        }
    }

    $conversation = Conversation::find($id);
    if ($conversation) {
        $profile = PublicProfile::withoutGlobalScope(OwnerScope::class)->find($conversation->store_profile_id);
        $allowed = (int) $user->id === (int) $conversation->buyer_id
            || ($profile && (int) $user->id === (int) $profile->user_id);
        if ($allowed) {
            return ['id' => $user->id, 'name' => $user->name];
        }
    }

    return false;
});

<?php

namespace App\Policies;

use App\Models\Conversacion;
use App\Models\User;

class ConversacionPolicy
{
    public function view(User $user, Conversacion $conversacion): bool
    {
        return $conversacion->comprador_id === $user->id || $conversacion->vendedor_id === $user->id;
    }

    public function sendMessage(User $user, Conversacion $conversacion): bool
    {
        return $this->view($user, $conversacion);
    }
}

<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\PublicProfile;
use App\Models\User;
use App\Scopes\OwnerScope;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        $profile = PublicProfile::withoutGlobalScope(OwnerScope::class)->find($conversation->store_profile_id);

        return $conversation->buyer_id === $user->id || ($profile && $profile->user_id === $user->id);
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}

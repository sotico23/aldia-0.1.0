<?php

use App\Models\Conversation;
use App\Models\User;
use App\Policies\ConversationPolicy;

it('allows buyer to view their conversation', function () {
    $buyer = new User;
    $buyer->id = 1;

    $conversation = new Conversation;
    $conversation->buyer_id = 1;
    $conversation->store_profile_id = 999; // no profile exists

    $policy = new ConversationPolicy;
    expect($policy->view($buyer, $conversation))->toBeTrue();
});

it('denies unrelated user to view conversation', function () {
    $unrelatedUser = new User;
    $unrelatedUser->id = 2;

    $conversation = new Conversation;
    $conversation->buyer_id = 1;
    $conversation->store_profile_id = 999;

    $policy = new ConversationPolicy;
    expect($policy->view($unrelatedUser, $conversation))->toBeFalse();
});

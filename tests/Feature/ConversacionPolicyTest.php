<?php

use App\Models\Conversacion;
use App\Models\User;
use App\Policies\ConversacionPolicy;

it('allows buyer to view their conversacion', function () {
    $buyer = new User;
    $buyer->id = 1;

    $conversacion = new Conversacion;
    $conversacion->comprador_id = 1;
    $conversacion->vendedor_id = 2;

    $policy = new ConversacionPolicy;
    expect($policy->view($buyer, $conversacion))->toBeTrue();
});

it('allows seller to view their conversacion', function () {
    $seller = new User;
    $seller->id = 2;

    $conversacion = new Conversacion;
    $conversacion->comprador_id = 1;
    $conversacion->vendedor_id = 2;

    $policy = new ConversacionPolicy;
    expect($policy->view($seller, $conversacion))->toBeTrue();
});

it('denies unrelated user to view conversacion', function () {
    $unrelatedUser = new User;
    $unrelatedUser->id = 3;

    $conversacion = new Conversacion;
    $conversacion->comprador_id = 1;
    $conversacion->vendedor_id = 2;

    $policy = new ConversacionPolicy;
    expect($policy->view($unrelatedUser, $conversacion))->toBeFalse();
});

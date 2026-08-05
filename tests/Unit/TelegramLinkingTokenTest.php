<?php

use App\Models\TelegramLinkingToken;

test('generateToken returns a sanitized token', function () {
    $token = TelegramLinkingToken::generateToken();

    expect($token)->toMatch('/^[A-Za-z0-9_]{1,64}$/');
});

test('generateToken returns unique tokens', function () {
    $tokens = collect(range(1, 10))
        ->map(fn () => TelegramLinkingToken::generateToken())
        ->unique();

    expect($tokens)->toHaveCount(10);
});

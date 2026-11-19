<?php

use Symfony\Component\Process\Process;

test('dashboard links authorized tenants to their personal wallet outside customizable widgets', function () {
    $process = new Process(['node', __DIR__.'/dashboard-wallet.mjs'], dirname(__DIR__));
    $process->setTimeout(60);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
});

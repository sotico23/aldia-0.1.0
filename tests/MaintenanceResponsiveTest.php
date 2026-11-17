<?php

use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\Process\Process;

uses(TestCase::class);

test('maintenance and every confirmation dialog fit mobile tablet desktop and landscape', function () {
    $chrome = getenv('CHROME_BINARY') ?: collect([
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome',
    ])->first(fn (string $path): bool => is_file($path));
    if (! $chrome || ! is_file(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Requires Chrome/Chromium and npm run build.');
    }

    $commands = collect(config('maintenance.commands'))->map(fn (array $command, string $key): array => [...$command, 'key' => $key])->values()->all();
    $commands[] = ['key' => 'long:confirmation', 'label' => str_repeat('Título largo ', 15), 'description' => str_repeat('Descripción extensa y sin espacios: '.str_repeat('x', 150).' ', 30), 'group' => 'Prueba de contenido largo', 'confirm' => true];
    $process = new Process(['node', base_path('tests/maintenance-responsive.mjs')], base_path(), ['CHROME_BINARY' => $chrome]);
    $process->setInput(json_encode(['commands' => $commands, 'status' => ['routes_cached' => false, 'config_cached' => false, 'road_alerts_available' => true]], JSON_THROW_ON_ERROR));
    $process->setTimeout(120);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    $results = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($results)->toHaveCount(5);
    foreach ($results as $result) {
        expect($result['failures'])->toBeEmpty($result['width'].'x'.$result['height'].': '.implode(', ', $result['failures']));
        expect($result['dialogs'])->toBe(6);
    }
});

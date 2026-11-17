<?php

use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\Process\Process;

uses(TestCase::class);

test('catalog details remain complete and accessible with read only permissions across screen sizes', function () {
    $chrome = getenv('CHROME_BINARY') ?: collect([
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome',
    ])->first(fn (string $path): bool => is_file($path));
    if (! $chrome || ! is_file(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Requires Chrome/Chromium and npm run build.');
    }
    $process = new Process(['node', base_path('tests/catalog-details-browser.mjs')], base_path(), ['CHROME_BINARY' => $chrome]);
    $process->setTimeout(150);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    $results = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($results)->toHaveCount(15);
    foreach ($results as $result) {
        expect($result['failures'])->toBeEmpty(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
});

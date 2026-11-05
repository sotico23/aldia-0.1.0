<?php

use Illuminate\Support\Facades\File;

test('publica un manifiesto instalable y los recursos de su shell offline', function () {
    $manifestPath = public_path('manifest.webmanifest');
    $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('RedCliente')
        ->and($manifest['short_name'])->toBe('RedCliente')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['icons'])->toHaveCount(2);

    foreach ($manifest['icons'] as $icon) {
        expect(File::exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue();
    }

    expect(File::exists(public_path('icons/apple-touch-icon.png')))->toBeTrue()
        ->and(File::exists(public_path('sw.js')))->toBeTrue()
        ->and(File::exists(public_path('offline.html')))->toBeTrue();

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('<meta name="theme-color" content="#b91c1c">', false)
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee("navigator.serviceWorker.register('/sw.js'", false);
});

<?php

use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$response = Http::timeout(10)->connectTimeout(5)->get('https://api.telegram.org/bot123456789:ABCdef/getMe');
echo 'Status: '.$response->status().PHP_EOL;
echo 'Body: '.$response->body().PHP_EOL;

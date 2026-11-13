<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'RedCliente' }}</title>
        <script src="{{ asset('vendor/livewire/livewire.js') }}" defer></script>
        @livewireStyles
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    </head>
    <body class="bg-[#f8fafc] text-slate-900 antialiased">
        {{ $slot }}
        @livewireScripts
    </body>
</html>

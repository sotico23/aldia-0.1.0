<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
    @vite(['resources/js/app.tsx'])
</head>
<body class="font-sans antialiased bg-gray-50">
    {{ $slot }}

    @livewireScripts
</body>
</html>
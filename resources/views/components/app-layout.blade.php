<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'RedCliente' }}</title>
        <script src="{{ asset('vendor/livewire/livewire.js') }}" defer></script>
        @livewireStyles
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-[#f8fafc] text-slate-900 antialiased">
        {{-- Breadcrumbs --}}
        @if (isset($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs) > 0)
            <nav class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-2">
                <ol class="flex items-center space-x-1 text-xs text-slate-500">
                    @foreach ($breadcrumbs as $index => $breadcrumb)
                        @if ($index < count($breadcrumbs) - 1)
                            <li class="flex items-center">
                                <a href="{{ $breadcrumb['href'] }}" class="text-primary hover:underline">{{ $breadcrumb['title'] }}</a>
                                <svg class="mx-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </li>
                        @else
                            <li class="text-slate-700 font-medium">{{ $breadcrumb['title'] }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        {{ $slot }}
        @livewireScripts
    </body>
</html>

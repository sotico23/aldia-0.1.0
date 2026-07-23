@php
    $cspNonce = $cspNonce ?? Str::random(40);
    $appearance = $appearance ?? 'system';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ $cspNonce }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }

                window.appName = "{{ \App\Models\WebSetting::getSettings()->app_name ?: config('app.name', 'Laravel') }}";
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style nonce="{{ $cspNonce }}">
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @php
            $webSettings = \App\Models\WebSetting::getSettings();
            $faviconUrl  = $webSettings->app_favicon ?: '/favicon.ico';
            $faviconSvg  = $webSettings->app_favicon ?: '/favicon.svg';
        @endphp

        <title inertia>{{ $webSettings->app_title ?: config('app.name', 'Laravel') }}</title>

        <meta name="description" content="{{ $webSettings->app_description }}">
        <meta name="keywords" content="{{ $webSettings->app_keywords }}">
        <meta name="author" content="{{ $webSettings->app_author }}">
        <meta name="application-name" content="{{ $webSettings->app_name }}">

        <link rel="icon" href="{{ $faviconUrl }}" sizes="any">
        <link rel="icon" href="{{ $faviconSvg }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ $webSettings->app_logo ?: '/apple-touch-icon.png' }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

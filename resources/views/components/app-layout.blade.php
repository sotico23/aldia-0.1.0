@php
    $settings = \App\Models\WebSetting::getSettings();
    $theme = $settings->themeColors();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="{{ $theme['primary'] }}">
        <title>{{ $title ?? $settings->app_name ?? config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
        @vite(['resources/css/app.css'])
        @livewireStyles
        <style nonce="{{ $cspNonce ?? '' }}">
            :root { --brand-primary: {{ $theme['primary'] }}; --brand-secondary: {{ $theme['secondary'] }}; --brand-accent: {{ $theme['accent'] }}; }
            [x-cloak] { display: none !important; }
        </style>
        <script nonce="{{ $cspNonce ?? '' }}">
            document.addEventListener('alpine:init', () => {
                Alpine.data('appSidebars', () => ({
                    leftSidebarOpen: false,
                    rightSidebarOpen: false,
                    closeSidebars() {
                        this.leftSidebarOpen = false;
                        this.rightSidebarOpen = false;
                    },
                    toggleLeftSidebar() {
                        this.leftSidebarOpen = !this.leftSidebarOpen;
                        this.rightSidebarOpen = false;
                    },
                    toggleRightSidebar() {
                        this.rightSidebarOpen = !this.rightSidebarOpen;
                        this.leftSidebarOpen = false;
                    },
                    syncSidebarWidths() {
                        if (window.innerWidth >= 1024) this.leftSidebarOpen = false;
                        if (window.innerWidth >= 1280) this.rightSidebarOpen = false;
                    },
                }));
            });
        </script>
    </head>
    <body class="min-h-screen bg-sidebar font-sans text-foreground antialiased" x-data="appSidebars" @keydown.escape.window="closeSidebars" @resize.window.debounce.150ms="syncSidebarWidths">
        <button x-cloak x-show="leftSidebarOpen || rightSidebarOpen" @click="closeSidebars" type="button" class="fixed inset-0 z-40 bg-black/40" aria-label="Cerrar paneles laterales"></button>
        @livewire('app-sidebar')
        <div class="min-h-screen lg:pl-64 xl:pr-64">
            <div class="min-h-screen overflow-x-hidden bg-background lg:m-2 lg:min-h-[calc(100vh-1rem)] lg:rounded-xl lg:border lg:border-border">
                <header class="sticky top-0 z-30 flex min-h-16 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur">
                    <button type="button" @click="toggleLeftSidebar" :aria-expanded="leftSidebarOpen" aria-controls="app-left-sidebar" class="flex size-11 shrink-0 items-center justify-center rounded-md hover:bg-accent lg:hidden" aria-label="Abrir navegación"><x-sidebar-icon name="PanelLeft" /></button>
                    <nav aria-label="Migas de pan" class="min-w-0 flex-1">
                        <ol class="flex flex-wrap items-center gap-2 text-xs">
                            @foreach ($breadcrumbs ?? [] as $breadcrumb)
                                <li class="min-w-0 break-words">
                                    @if (! $loop->last)
                                        <a href="{{ $breadcrumb['href'] }}" class="text-muted-foreground hover:text-primary">{{ $breadcrumb['title'] }}</a>
                                        <span class="ml-2 text-muted-foreground">/</span>
                                    @else
                                        <span aria-current="page">{{ $breadcrumb['title'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                    <button type="button" @click="toggleRightSidebar" :aria-expanded="rightSidebarOpen" aria-controls="app-right-sidebar" class="flex size-11 shrink-0 items-center justify-center rounded-md hover:bg-accent xl:hidden" aria-label="Abrir mi cuenta"><x-sidebar-icon name="User" /></button>
                </header>
                <main class="min-w-0">{{ $slot }}</main>
            </div>
        </div>
        @livewire('app-right-sidebar')
        @livewireScripts
    </body>
</html>

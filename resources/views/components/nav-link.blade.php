@props(['href' => '#', 'active' => false])

@php
$classes = $active
    ? 'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-bold text-sidebar-primary bg-sidebar-accent/60 transition-colors'
    : 'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent/40 hover:text-sidebar-foreground';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

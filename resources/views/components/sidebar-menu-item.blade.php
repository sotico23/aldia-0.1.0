@props(['item'])
@if (! empty($item['items']))
    <details class="group/menu" @if (collect($item['items'])->contains(fn ($child) => request()->is(ltrim($child['href'], '/')))) open @endif>
        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-md px-2 py-2 text-sm hover:bg-sidebar-accent focus-visible:outline-2 focus-visible:outline-primary">
            @isset($item['icon']) <x-sidebar-icon :name="$item['icon']" /> @endisset
            <span class="flex-1">{{ $item['title'] }}</span>
            <x-sidebar-icon name="ChevronRight" class="size-3 transition-transform group-open/menu:rotate-90" />
        </summary>
        <div class="ml-3 border-l border-sidebar-border pl-2">
            @foreach ($item['items'] as $child)
                <x-sidebar-menu-item :item="$child" />
            @endforeach
        </div>
    </details>
@else
    <a href="{{ $item['href'] }}" @if (request()->is(ltrim($item['href'], '/'))) aria-current="page" @endif class="flex items-center gap-2 rounded-md px-2 py-2 text-sm hover:bg-sidebar-accent aria-[current=page]:bg-sidebar-accent aria-[current=page]:font-semibold aria-[current=page]:text-primary">
        @isset($item['icon']) <x-sidebar-icon :name="$item['icon']" /> @endisset
        <span>{{ $item['title'] }}</span>
    </a>
@endif

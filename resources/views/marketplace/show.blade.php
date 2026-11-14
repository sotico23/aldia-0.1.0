<x-app-layout
    :title="$store->title"
    :breadcrumbs="[
        ['title' => 'Marketplace', 'href' => '/tienda'],
        ['title' => $store->title, 'href' => '/tienda/' . $store->slug],
    ]"
>
    @livewire('marketplace-store', ['slug' => $store->slug])
</x-app-layout>

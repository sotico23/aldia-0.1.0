<x-app-layout
    :title="$store->title"
    :breadcrumbs="[
        ['title' => 'Tiendas', 'href' => route('marketplace.index')],
        ['title' => $store->title, 'href' => '/tienda/' . $store->slug],
    ]"
>
    @livewire('marketplace-store', ['slug' => $store->slug])
</x-app-layout>

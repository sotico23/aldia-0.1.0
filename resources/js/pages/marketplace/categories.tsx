import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, Store } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { category as marketplaceCategory, show as marketplaceShow } from '@/routes/marketplace';

interface Category {
    id: number;
    nombre: string;
    descripcion: string | null;
    imagen: string | null;
}

interface StoreProfile {
    title: string;
    slug: string;
}

interface Props {
    store: StoreProfile;
    categorias: Category[];
}

export default function MarketplaceCategories({ store, categorias }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Marketplace', href: '/tienda' },
                {
                    title: store.title,
                    href: marketplaceShow(store.slug).url,
                },
                { title: 'Categorías', href: '#' },
            ]}
        >
            <Head title={`Categorías de ${store.title}`} />

            <main className="mx-auto w-full max-w-6xl space-y-6 px-4 py-8">
                <div className="flex items-center gap-4">
                    <Link
                        href={marketplaceShow(store.slug)}
                        aria-label="Volver a la tienda"
                        className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        <ChevronLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            Todas las categorías
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Explora las categorías de {store.title}.
                        </p>
                    </div>
                </div>

                {categorias.length > 0 ? (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {categorias.map((categoria) => (
                            <Link
                                key={categoria.id}
                                href={marketplaceCategory({
                                    slug: store.slug,
                                    categoria: categoria.id,
                                })}
                                className="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div className="aspect-square overflow-hidden bg-slate-100 dark:bg-slate-800">
                                    {categoria.imagen ? (
                                        <img
                                            src={`/storage/${categoria.imagen}`}
                                            alt={categoria.nombre}
                                            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        />
                                    ) : (
                                        <div className="flex h-full items-center justify-center">
                                            <Store className="h-12 w-12 text-slate-300" />
                                        </div>
                                    )}
                                </div>
                                <div className="space-y-1 p-4">
                                    <h2 className="font-semibold text-slate-900 dark:text-white">
                                        {categoria.nombre}
                                    </h2>
                                    {categoria.descripcion && (
                                        <p className="line-clamp-2 text-sm text-slate-500 dark:text-slate-400">
                                            {categoria.descripcion}
                                        </p>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-12 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900">
                        Esta tienda aún no tiene categorías disponibles.
                    </div>
                )}
            </main>
        </AppLayout>
    );
}

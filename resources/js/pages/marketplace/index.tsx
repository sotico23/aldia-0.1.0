import { Head, Link, usePage } from '@inertiajs/react';
import { Store, MapPin, User, ChevronRight, Search } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import type { User as UserType } from '@/types';

interface LocalUserType extends UserType {
    ciudad?: string | null;
    region?: string | null;
    cover_photo_url?: string;
}

interface StoreProfile {
    id: number;
    title: string;
    description: string | null;
    slug: string;
    user: LocalUserType;
}

interface Props {
    profiles: {
        data: StoreProfile[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
}

export default function MarketplaceIndex({ profiles }: Props) {
    const { web_settings } = usePage<{ web_settings: any }>().props;

    return (
        <AppLayout breadcrumbs={[{ title: 'Marketplace', href: '/tienda' }]}>
            <Head title={`Marketplace | ${web_settings?.app_name || 'Directorio de Tiendas'}`}>
                <meta name="description" content={web_settings?.app_description} />
                <meta name="keywords" content={web_settings?.app_keywords} />
            </Head>
            
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {/* Header Section */}
                <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <h1 className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                            Marketplace General
                        </h1>
                        <p className="mt-2 flex items-center gap-2 text-lg text-slate-500 dark:text-slate-400">
                            Descubre empresas y tiendas disponibles en nuestra red.
                        </p>
                    </div>
                    <div className="relative max-w-sm sm:w-80">
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Search className="h-5 w-5 text-slate-400" />
                        </div>
                        <input
                            type="text"
                            placeholder="Buscar tiendas..."
                            className="block w-full rounded-full border-0 py-2.5 pl-10 pr-4 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm sm:leading-6 dark:bg-slate-800 dark:text-white dark:ring-slate-700"
                        />
                    </div>
                </div>

                {/* Profiles Grid */}
                {profiles.data.length > 0 ? (
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {profiles.data.map((store) => (
                            <Link
                                key={store.id}
                                href={`/tienda/${store.slug}`}
                                className="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition-all hover:shadow-md hover:ring-primary dark:bg-slate-900 dark:ring-slate-800 dark:hover:ring-primary"
                            >
                                <div className="relative h-24 overflow-hidden bg-gradient-to-br from-slate-100 to-slate-200 group-hover:from-indigo-50 group-hover:to-purple-50 dark:from-slate-800 dark:to-slate-800/80 dark:group-hover:from-indigo-950/50 dark:group-hover:to-purple-900/50">
                                    {store.user.cover_photo_url ? (
                                        <img 
                                            src={store.user.cover_photo_url} 
                                            alt="Portada" 
                                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" 
                                        />
                                    ) : null}
                                </div>
                                
                                <div className="relative px-5 pb-5 pt-0">
                                    <div className="-mt-10 mb-3 flex justify-between">
                                        <div className="flex h-20 w-20 items-center justify-center rounded-xl border-4 border-white bg-white shadow-sm dark:border-slate-900 dark:bg-slate-800">
                                            {store.user.profile_photo_url ? (
                                                <img 
                                                    src={store.user.profile_photo_url} 
                                                    alt={store.title} 
                                                    className="h-full w-full rounded-lg object-cover" 
                                                />
                                            ) : (
                                                <Store className="h-8 w-8 text-slate-400" />
                                            )}
                                        </div>
                                        <div className="mt-12 flex h-8 w-8 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition-colors group-hover:bg-primary/10 group-hover:text-primary dark:bg-slate-800">
                                            <ChevronRight className="h-4 w-4" />
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <h3 className="line-clamp-1 text-base font-semibold text-slate-900 dark:text-white">
                                            {store.title}
                                        </h3>
                                        <div className="mt-1 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                            <User className="h-3 w-3" />
                                            <span className="truncate">{store.user.name}</span>
                                        </div>
                                    </div>

                                    <p className="mt-3 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">
                                        {store.description || 'Sin descripción disponible.'}
                                    </p>
                                    
                                    {(store.user.ciudad || store.user.region) && (
                                        <div className="mt-4 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                            <MapPin className="h-3.5 w-3.5" />
                                            <span className="truncate">
                                                {store.user.ciudad}
                                                {store.user.ciudad && store.user.region ? ', ' : ''}
                                                {store.user.region}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <div className="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-24 text-center dark:border-slate-800 dark:bg-slate-900/50">
                        <Store className="mx-auto h-12 w-12 text-slate-400" />
                        <h3 className="mt-4 text-sm font-semibold text-slate-900 dark:text-white">
                            No hay tiendas disponibles
                        </h3>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Todavía no hay ninguna empresa o tienda registrada en el marketplace público.
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

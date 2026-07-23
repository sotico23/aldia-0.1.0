import { Head, Link } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Store,
    User,
    ChevronRight,
    Clock,
    Package,
    ShoppingBag,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import { useCountry } from '@/hooks/use-country';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';

interface Conversation {
    id: number;
    buyer: { id: number; name: string };
    store: { id: number; title: string; slug: string };
    latest_message: { body: string; created_at: string }[];
    updated_at: string;
}

interface Pedido {
    id: number;
    numero_pedido: string;
    estado: string;
    total: number;
    created_at: string;
    publicProfile: { id: number; title: string; slug: string };
    conversacion?: {
        id: number;
        mensajes?: { contenido: string; created_at: string }[];
    };
}

interface Props {
    misPedidos: Pedido[];
    misVentas: Pedido[];
    misConsultas: Conversation[];
    ventasYConsultas: Conversation[];
}

export default function ChatInbox({
    misPedidos,
    misVentas,
    misConsultas,
    ventasYConsultas,
}: Props) {
    const { code: countryCode, currency } = useCountry();
    const [searchQuery, setSearchQuery] = useState('');

    const filteredMisPedidos = misPedidos.filter(
        (p) =>
            p.numero_pedido
                ?.toLowerCase()
                .includes(searchQuery.toLowerCase()) ||
            p.publicProfile?.title
                ?.toLowerCase()
                .includes(searchQuery.toLowerCase()),
    );

    const filteredMisVentas = misVentas.filter((p) =>
        p.numero_pedido?.toLowerCase().includes(searchQuery.toLowerCase()),
    );

    const filteredMisConsultas = misConsultas.filter((c) =>
        c.store?.title?.toLowerCase().includes(searchQuery.toLowerCase()),
    );

    const filteredVentasYConsultas = ventasYConsultas.filter((c) =>
        c.buyer?.name?.toLowerCase().includes(searchQuery.toLowerCase()),
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Marketplace', href: '/tienda' },
                { title: 'Mensajes', href: '/chat' },
            ]}
        >
            <Head title="Bandeja de Entrada | Chat Marketplace" />

            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                        Centro de Mensajes
                    </h1>
                    <p className="mt-2 text-lg text-slate-500">
                        Gestiona tus conversaciones con tiendas y clientes.
                    </p>
                </div>

                <div className="mb-4">
                    <div className="relative max-w-md">
                        <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <Input
                            placeholder="Buscar conversaciones..."
                            className="h-10 w-full rounded-full border-slate-200 pl-10"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                        />
                    </div>
                </div>

                <div className="space-y-6">
                    <Card className="overflow-hidden border-slate-200 shadow-sm dark:border-slate-800">
                        <CardHeader className="bg-blue-50/50 dark:bg-blue-900/20">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/50">
                                    <ShoppingBag className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle className="text-xl font-bold">
                                        Mis Compras
                                    </CardTitle>
                                    <CardDescription>
                                        Pedidos que has realizado y consultas a
                                        tiendas.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {filteredMisPedidos.length > 0 ||
                            filteredMisConsultas.length > 0 ? (
                                <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {filteredMisPedidos.map((pedido) => {
                                        // eslint-disable-next-line @typescript-eslint/no-unused-vars
                                        const latest =
                                            pedido.conversacion?.mensajes?.[0];
                                        return (
                                            <Link
                                                key={`pedido-${pedido.id}`}
                                                href={
                                                    pedido.conversacion?.id
                                                        ? `/conversaciones-pedidos/${pedido.conversacion.id}/chat`
                                                        : `/pedidos/${pedido.id}`
                                                }
                                                className="group block transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                            >
                                                <div className="flex items-center gap-4 p-4">
                                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/50">
                                                        <Package className="h-6 w-6" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="mb-1 flex items-center justify-between">
                                                            <h4 className="truncate font-semibold text-slate-900 dark:text-white">
                                                                {
                                                                    pedido.numero_pedido
                                                                }
                                                            </h4>
                                                            <span className="flex items-center gap-1 text-xs text-slate-500">
                                                                <Clock className="h-3 w-3" />
                                                                {formatDistanceToNow(
                                                                    new Date(
                                                                        pedido.created_at,
                                                                    ),
                                                                    {
                                                                        addSuffix: true,
                                                                        locale: es,
                                                                    },
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="line-clamp-1 text-sm text-slate-500 dark:text-slate-400">
                                                            {
                                                                pedido
                                                                    .publicProfile
                                                                    ?.title
                                                            }{' '}
                                                            -
                                                            <span className="font-medium text-blue-600">
                                                                $
                                                                {Number(
                                                                    pedido.total,
                                                                ).toLocaleString(
                                                                    currency.locale,
                                                                )}
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <ChevronRight className="h-5 w-5 text-slate-300 transition-colors group-hover:text-primary" />
                                                </div>
                                            </Link>
                                        );
                                    })}
                                    {filteredMisConsultas.map((conv) => {
                                        const latest = conv.latest_message?.[0];
                                        return (
                                            <Link
                                                key={`consulta-${conv.id}`}
                                                href={`/chat/${conv.id}`}
                                                className="group block transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                            >
                                                <div className="flex items-center gap-4 p-4">
                                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800">
                                                        <Store className="h-6 w-6" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="mb-1 flex items-center justify-between">
                                                            <h4 className="truncate font-semibold text-slate-900 dark:text-white">
                                                                {
                                                                    conv.store
                                                                        ?.title
                                                                }
                                                            </h4>
                                                            <span className="flex items-center gap-1 text-xs text-slate-500">
                                                                <Clock className="h-3 w-3" />
                                                                {formatDistanceToNow(
                                                                    new Date(
                                                                        conv.updated_at,
                                                                    ),
                                                                    {
                                                                        addSuffix: true,
                                                                        locale: es,
                                                                    },
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="line-clamp-1 text-sm text-slate-500 italic dark:text-slate-400">
                                                            {latest
                                                                ? latest.body
                                                                : 'Sin mensajes aún.'}
                                                        </p>
                                                    </div>
                                                    <ChevronRight className="h-5 w-5 text-slate-300 transition-colors group-hover:text-primary" />
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <ShoppingBag className="mb-3 h-12 w-12 text-slate-300" />
                                    <p className="font-medium text-slate-500">
                                        No hay compras aún.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden border-slate-200 shadow-sm dark:border-slate-800">
                        <CardHeader className="bg-green-50/50 dark:bg-green-900/20">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 text-green-600 dark:bg-green-900/50">
                                    <Store className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle className="text-xl font-bold">
                                        Ventas y Consultas
                                    </CardTitle>
                                    <CardDescription>
                                        Pedidos de tus clientes y mensajes de
                                        potenciales clientes.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {filteredMisVentas.length > 0 ||
                            filteredVentasYConsultas.length > 0 ? (
                                <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {filteredMisVentas.map((pedido) => {
                                        // eslint-disable-next-line @typescript-eslint/no-unused-vars
                                        const latest =
                                            pedido.conversacion?.mensajes?.[0];
                                        return (
                                            <Link
                                                key={`venta-${pedido.id}`}
                                                href={
                                                    pedido.conversacion?.id
                                                        ? `/conversaciones-pedidos/${pedido.conversacion.id}/chat`
                                                        : `/pedidos-recibidos/${pedido.id}`
                                                }
                                                className="group block transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                            >
                                                <div className="flex items-center gap-4 p-4">
                                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/50">
                                                        <Package className="h-6 w-6" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="mb-1 flex items-center justify-between">
                                                            <h4 className="truncate font-semibold text-slate-900 dark:text-white">
                                                                {
                                                                    pedido.numero_pedido
                                                                }
                                                            </h4>
                                                            <span className="flex items-center gap-1 text-xs text-slate-500">
                                                                <Clock className="h-3 w-3" />
                                                                {formatDistanceToNow(
                                                                    new Date(
                                                                        pedido.created_at,
                                                                    ),
                                                                    {
                                                                        addSuffix: true,
                                                                        locale: es,
                                                                    },
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="line-clamp-1 text-sm text-slate-500 dark:text-slate-400">
                                                            <span className="font-medium text-green-600">
                                                                $
                                                                {Number(
                                                                    pedido.total,
                                                                ).toLocaleString(
                                                                    currency.locale,
                                                                )}
                                                            </span>{' '}
                                                            - {pedido.estado}
                                                        </p>
                                                    </div>
                                                    <ChevronRight className="h-5 w-5 text-slate-300 transition-colors group-hover:text-primary" />
                                                </div>
                                            </Link>
                                        );
                                    })}
                                    {filteredVentasYConsultas.map((conv) => {
                                        const latest = conv.latest_message?.[0];
                                        return (
                                            <Link
                                                key={`venta-conv-${conv.id}`}
                                                href={`/chat/${conv.id}`}
                                                className="group block transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                            >
                                                <div className="flex items-center gap-4 p-4">
                                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800">
                                                        <User className="h-6 w-6" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="mb-1 flex items-center justify-between">
                                                            <h4 className="truncate font-semibold text-slate-900 dark:text-white">
                                                                {
                                                                    conv.buyer
                                                                        ?.name
                                                                }
                                                            </h4>
                                                            <span className="flex items-center gap-1 text-xs text-slate-500">
                                                                <Clock className="h-3 w-3" />
                                                                {formatDistanceToNow(
                                                                    new Date(
                                                                        conv.updated_at,
                                                                    ),
                                                                    {
                                                                        addSuffix: true,
                                                                        locale: es,
                                                                    },
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="line-clamp-1 text-sm text-slate-500 italic dark:text-slate-400">
                                                            {latest
                                                                ? latest.body
                                                                : 'Sin mensajes aún.'}
                                                        </p>
                                                    </div>
                                                    <ChevronRight className="h-5 w-5 text-slate-300 transition-colors group-hover:text-primary" />
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <Store className="mb-3 h-12 w-12 text-slate-300" />
                                    <p className="font-medium text-slate-500">
                                        No hay ventas ni consultas aún.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

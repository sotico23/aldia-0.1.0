import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    Package,
    Truck,
    Clock,
    ArrowLeft,
    MessageCircle,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';

interface PedidoItem {
    id: number;
    nombre_producto: string;
    precio_unitario: number;
    cantidad: number;
    subtotal: number;
}

interface Pedido {
    id: number;
    numero_pedido: string;
    estado: string;
    total: number;
    subtotal: number;
    impuesto: number;
    nombre_cliente: string;
    created_at: string;
    items: PedidoItem[];
}

interface Tienda {
    id: number;
    title: string;
    slug: string;
}

interface Vendedor {
    name: string;
    telefono?: string;
}

interface Props {
    pedido: Pedido;
    tienda: Tienda;
    conversacion?: Conversacion;
    vendedor?: Vendedor;
}

interface Conversacion {
    id: number;
    titulo: string;
}

interface Props {
    pedido: Pedido;
    tienda: Tienda;
    conversacion?: Conversacion;
}

const estadoSteps = [
    { key: 'pendiente', label: 'Pedido recibido', icon: Clock },
    { key: 'confirmado', label: 'Confirmado', icon: CheckCircle2 },
    { key: 'preparando', label: 'En preparación', icon: Package },
    { key: 'enviado', label: 'Enviado', icon: Truck },
    { key: 'entregado', label: 'Entregado', icon: CheckCircle2 },
];

export default function Confirmacion({
    pedido,
    tienda,
    conversacion,
    vendedor,
}: Props) {
    const currentStepIndex = estadoSteps.findIndex(
        (s) => s.key === pedido.estado,
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Marketplace', href: '/tienda' },
                { title: tienda.title, href: `/tienda/${tienda.slug}` },
                { title: 'Confirmación', href: '#' },
            ]}
        >
            <Head title={`Pedido ${pedido.numero_pedido} | Confirmación`} />

            <div className="mx-auto max-w-3xl px-4 sm:px-6 py-8">
                <div className="rounded-2xl bg-white p-6 sm:p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                    <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
                        <CheckCircle2 className="h-10 w-10 text-green-600" />
                    </div>
                    <h1 className="mt-6 text-2xl font-bold text-slate-900 dark:text-white">
                        ¡Pedido realizado con éxito!
                    </h1>
                    <p className="mt-2 text-slate-600 dark:text-slate-400">
                        Tu pedido ha sido enviado al vendedor. Te contactarán
                        pronto para confirmar los detalles.
                    </p>
                    <p className="mt-1 text-sm font-medium text-primary">
                        Número de pedido: {pedido.numero_pedido}
                    </p>
                </div>

                <div className="mt-6 rounded-2xl bg-white p-4 sm:p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                    <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                        Estado de tu pedido
                    </h2>
                    <div className="overflow-x-auto">
                        <div className="mt-4 flex items-center justify-between gap-2">
                        {estadoSteps.map((step, index) => {
                            const Icon = step.icon;
                            const isCompleted = index <= currentStepIndex;
                            const isCurrent = index === currentStepIndex;

                            return (
                                <div
                                    key={step.key}
                                    className="flex flex-col items-center"
                                >
                                    <div
                                        className={`flex h-10 w-10 items-center justify-center rounded-full ${
                                            isCompleted
                                                ? 'bg-primary text-white'
                                                : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-600'
                                        }`}
                                    >
                                        <Icon className="h-5 w-5" />
                                    </div>
                                    <span
                                        className={`mt-2 text-center text-xs ${isCurrent ? 'font-medium text-primary' : 'text-slate-500'}`}
                                    >
                                        {step.label}
                                    </span>
                            </div>
                        );
                    })}
                    </div>
                    </div>
                </div>

                <div className="mt-6 rounded-2xl bg-white p-4 sm:p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                    <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                        Resumen del pedido
                    </h2>
                    <div className="mt-4 space-y-3">
                        {pedido.items.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between border-b border-slate-100 pb-3 last:border-0 dark:border-slate-800"
                            >
                                <div>
                                    <p className="font-medium text-slate-900 dark:text-white">
                                        {item.nombre_producto}
                                    </p>
                                    <p className="text-sm text-slate-500">
                                        {item.cantidad} x $
                                        {Number(item.precio_unitario).toFixed(
                                            2,
                                        )}
                                    </p>
                                </div>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    ${Number(item.subtotal).toFixed(2)}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 border-t pt-4 dark:border-slate-800">
                        <div className="flex justify-between text-sm">
                            <span className="text-slate-600 dark:text-slate-400">
                                Subtotal
                            </span>
                            <span>${Number(pedido.subtotal).toFixed(2)}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-slate-600 dark:text-slate-400">
                                Impuesto
                            </span>
                            <span>${Number(pedido.impuesto).toFixed(2)}</span>
                        </div>
                        <div className="mt-2 flex justify-between text-lg font-bold">
                            <span>Total</span>
                            <span className="text-primary">
                                ${Number(pedido.total).toFixed(2)}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex flex-wrap gap-4">
                    <Link
                        href={`/tienda/${tienda.slug}`}
                        className="flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver a la tienda
                    </Link>
                    <Link
                        href="/mis-pedidos"
                        className="flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Ver mis pedidos
                    </Link>
                    {conversacion && (
                        <Link
                            href={`/conversaciones-pedidos/${conversacion.id}/chat`}
                            className="flex items-center gap-2 rounded-xl bg-green-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600"
                        >
                            <MessageCircle className="h-4 w-4" />
                            Chat
                        </Link>
                    )}
                    {vendedor?.telefono && (
                        <a
                            href={`https://wa.me/${vendedor.telefono.replace(/[^0-9]/g, '')}?text=Hola%20${encodeURIComponent(vendedor.name)},%20mi%20pedido%20%23${pedido.numero_pedido}%20está%20en%20estado:%20${pedido.estado}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                        >
                            <MessageCircle className="h-3 w-3" />
                        </a>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Store,
    ShoppingBag,
    ChevronLeft,
    Plus,
    Minus,
    ShoppingCart,
    Trash2,
    CreditCard,
    CheckCircle2,
    ArrowRight,
    Clock,
    Wallet
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

interface LocalUserType extends User {
    ciudad?: string | null;
    region?: string | null;
    cover_photo_url?: string;
}

interface Categoria {
    id: number;
    nombre: string;
    descripcion: string | null;
    imagen: string | null;
}

interface Producto {
    id: number;
    nombre: string;
    descripcion: string | null;
    precio_venta: number;
    imagen: string | null;
    unidad_medida: string;
    categoria_id: number;
    is_service: boolean;
    duracion: number | null;
    requires_appointment: boolean;
}

interface PaginationData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

interface StoreProfile {
    id: number;
    title: string;
    description: string | null;
    phone: string | null;
    email: string | null;
    slug: string;
    user: LocalUserType;
    created_at: string;
}

interface Props {
    store: StoreProfile;
    categoria: Categoria;
    productos: PaginationData<Producto>;
    randomProductos: Producto[];
    paymentConfig?: {
        is_active: boolean;
        paypal_active: boolean;
        mercadopago_active: boolean;
    };
}

export default function MarketplaceCategory({ store, categoria, productos, randomProductos, paymentConfig }: Props) {
    const { auth, web_settings } = usePage<{ 
        auth: { user?: User },
        web_settings: any 
    }>().props;
    // Carrito persistente en localStorage
    const carritoKey = `carrito_${store.slug}`;
    const [carrito, setCarritoState] = useState<{ [key: number]: number }>(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem(carritoKey);
            return saved ? JSON.parse(saved) : {};
        }
        return {};
    });
    const setCarrito = (val: { [key: number]: number } | ((prev: { [key: number]: number }) => { [key: number]: number })) => {
        setCarritoState((prev) => {
            const next = typeof val === 'function' ? val(prev) : val;
            localStorage.setItem(carritoKey, JSON.stringify(next));
            return next;
        });
    };
    const [cantidadesAgregar, setCantidadesAgregar] = useState<{ [key: number]: number }>({});
    const modoGramosKey = `modoGramos_${store.slug}`;
    const [modoGramosProducto, setModoGramosState] = useState<{ [key: number]: boolean }>(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem(modoGramosKey);
            return saved ? JSON.parse(saved) : {};
        }
        return {};
    });
    const setModoGramosProducto = (val: { [key: number]: boolean } | ((prev: { [key: number]: boolean }) => { [key: number]: boolean })) => {
        setModoGramosState((prev) => {
            const next = typeof val === 'function' ? val(prev) : val;
            localStorage.setItem(modoGramosKey, JSON.stringify(next));
            return next;
        });
    };
    const [productoAgregado, setProductoAgregado] = useState<number | null>(null);

    const esGrameable = (producto: Producto) =>
        producto.unidad_medida === 'kg' || producto.unidad_medida === 'lt';
    const precioProporcional = (producto: Producto, cantidadKg: number) => {
        if (!esGrameable(producto)) return null;
        return producto.precio_venta * cantidadKg;
    };
    const [carritoAbierto, setCarritoAbierto] = useState(false);
    const [checkoutAbierto, setCheckoutAbierto] = useState(false);
    const [datosCheckout, setDatosCheckout] = useState({
        nombre_cliente: '',
        telefono_cliente: '',
        direccion_cliente: '',
        metodo_pago: 'efectivo',
        notas: '',
    });
    const [procesando, setProcesando] = useState(false);

    const agregarAlCarrito = (productoId: number, abrirCarrito = false) => {
        const cantidadAEnviar = cantidadesAgregar[productoId] || 1;
        setCarrito((prev) => ({
            ...prev,
            [productoId]: (prev[productoId] || 0) + cantidadAEnviar,
        }));
        const resetVal = modoGramosProducto[productoId] ? 0.1 : 1;
        setCantidadesAgregar((prev) => ({ ...prev, [productoId]: resetVal }));
        setProductoAgregado(productoId);
        if (abrirCarrito) {
            setCarritoAbierto(true);
        }
        setTimeout(() => setProductoAgregado(null), 2000);
    };

    const comprarAhora = (productoId: number) => {
        const cantidadAEnviar = cantidadesAgregar[productoId] || 1;
        setCarrito({ [productoId]: cantidadAEnviar });
        setCheckoutAbierto(true);
    };

    const enviarCheckout = () => {
        if (!auth.user) {
            router.visit('/login');
            return;
        }

        setProcesando(true);

        const items = Object.entries(carrito)
            .filter(([, cantidad]) => cantidad > 0)
            .map(([productoId, cantidad]) => ({
                producto_id: parseInt(productoId),
                cantidad,
            }));

        router.post(
            `/tienda/${store.slug}/checkout`,
            {
                public_profile_id: store.id,
                items,
                ...datosCheckout,
            },
            {
                onSuccess: () => {
                    setProcesando(false);
                    setCheckoutAbierto(false);
                    setCarritoState({});
                    localStorage.removeItem(carritoKey);
                    localStorage.removeItem(modoGramosKey);
                    setCarritoAbierto(false);
                },
                onError: () => {
                    setProcesando(false);
                },
            },
        );
    };

    const eliminarDelCarrito = (productoId: number) => {
        setCarrito((prev) => {
            const nuevoCarrito = { ...prev };
            delete nuevoCarrito[productoId];
            return nuevoCarrito;
        });
    };

    const cambiarCantidad = (productoId: number, delta: number) => {
        setCarrito((prev) => {
            const esGramos = modoGramosProducto[productoId];
            const actual = prev[productoId] || 0;
            let nuevaCantidad: number;
            if (esGramos) {
                nuevaCantidad = Math.round((actual + delta * 0.001) * 1000) / 1000;
            } else {
                nuevaCantidad = actual + delta;
            }
            if (nuevaCantidad <= 0) {
                const nuevoCarrito = { ...prev };
                delete nuevoCarrito[productoId];
                return nuevoCarrito;
            }
            return { ...prev, [productoId]: nuevaCantidad };
        });
    };

    const setCantidadManual = (productoId: number, cantidad: number) => {
        if (cantidad <= 0) {
            eliminarDelCarrito(productoId);
            return;
        }
        setCarrito((prev) => ({
            ...prev,
            [productoId]: cantidad,
        }));
    };

    const cambiarCantidadLocal = (productoId: number, delta: number) => {
        setCantidadesAgregar((prev) => {
            const current = prev[productoId] || 1;
            const esGramos = modoGramosProducto[productoId];
            if (esGramos) {
                const nuevosGramos = Math.max(0.001, current + delta);
                return { ...prev, [productoId]: Math.round(nuevosGramos * 1000) / 1000 };
            }
            const next = Math.max(1, current + delta);
            return { ...prev, [productoId]: next };
        });
    };

    const setCantidadManualLocal = (productoId: number, cantidad: number) => {
        const minVal = modoGramosProducto[productoId] ? 0.001 : 1;
        if (cantidad < minVal) return;
        setCantidadesAgregar((prev) => ({
            ...prev,
            [productoId]: cantidad,
        }));
    };

    // Combinar todos los productos para el renderizado del carrito (de la página actual + randoms)
    const todosLosProductosSet = new Map<number, Producto>();
    productos.data.forEach(p => todosLosProductosSet.set(p.id, p));
    randomProductos.forEach(p => todosLosProductosSet.set(p.id, p));
    
    const productosEnCarrito = Array.from(todosLosProductosSet.values()).filter(
        (p) => carrito[p.id] && carrito[p.id] > 0,
    );

    const totalCarrito = productosEnCarrito.reduce(
        (sum, p) => sum + Number(p.precio_venta) * (carrito[p.id] || 0),
        0,
    );

    const totalItems = Object.values(carrito).reduce((a, b) => a + b, 0);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Marketplace', href: '/tienda' },
                { title: store.title, href: `/tienda/${store.slug}` },
                { title: categoria.nombre, href: `/tienda/${store.slug}/categoria/${categoria.id}` },
            ]}
        >
            <Head title={`${categoria.nombre} | ${store.title} | ${web_settings?.app_name || 'Tienda'}`}>
                <meta name="description" content={categoria.descripcion || web_settings?.app_description} />
                <meta name="keywords" content={web_settings?.app_keywords} />
            </Head>

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex items-center gap-4 mb-8">
                    <Link
                        href={`/tienda/${store.slug}`}
                        className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700"
                    >
                        <ChevronLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                            {categoria.nombre}
                        </h1>
                        <p className="mt-1 text-slate-500 dark:text-slate-400">
                            Explora los productos de {store.title} en esta categoría.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-4">
                    {/* Main Products Grid */}
                    <div className="lg:col-span-3">
                        {productos.data.length > 0 ? (
                            <>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {productos.data.map((producto) => (
                                        <div
                                            key={producto.id}
                                            className="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition-all hover:shadow-md dark:bg-slate-900 dark:ring-slate-800"
                                        >
                                            <div className="relative aspect-square overflow-hidden bg-slate-100 dark:bg-slate-800">
                                                {producto.imagen ? (
                                                    <img
                                                        src={`/storage/${producto.imagen}`}
                                                        alt={producto.nombre}
                                                        className="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105"
                                                    />
                                                ) : (
                                                    <div className="flex h-full w-full items-center justify-center">
                                                        <ShoppingBag className="h-12 w-12 text-slate-300" />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex flex-1 flex-col p-5">
                                                <h3 className="text-lg font-semibold text-slate-900 line-clamp-1 dark:text-white">
                                                    {producto.nombre}
                                                </h3>
                                                <p className="mt-2 text-sm text-slate-500 line-clamp-2 dark:text-slate-400">
                                                    {producto.descripcion || 'Sin descripción disponible.'}
                                                </p>
                                                    <div className="mt-auto pt-4">
                                                        <div className="flex items-baseline gap-1">
                                                            <span className="text-2xl font-bold text-primary">
                                                                ${Number(producto.precio_venta).toFixed(0)}
                                                            </span>
                                                            <span className="text-sm text-slate-500">
                                                                / {producto.is_service ? `${producto.duracion} min` : producto.unidad_medida}
                                                            </span>
                                                        </div>
                                                        <div className="mt-4 flex flex-col gap-2">
                                                            {producto.is_service && producto.requires_appointment ? (
                                                                <Link
                                                                    href={`/booking/${store.slug}?service_id=${producto.id}`}
                                                                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg py-2 px-3 text-sm font-medium transition-all bg-primary text-primary-foreground hover:bg-primary/90"
                                                                >
                                                                    <Clock className="h-4 w-4" />
                                                                    Agendar Cita
                                                                </Link>
                                                            ) : (
                                                                <>
                                                                    <div className="flex items-center justify-between rounded-lg border border-slate-200 p-1 dark:border-slate-700">
                                                                        <button
                                                                            onClick={() => cambiarCantidadLocal(producto.id, -1)}
                                                                            className="flex h-8 w-8 items-center justify-center rounded-md bg-slate-50 text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                                                        >
                                                                            <Minus className="h-4 w-4" />
                                                                        </button>
                                                                        <div className="flex items-center gap-1">
                                                                            <input
                                                                                type="number"
                                                                                min={modoGramosProducto[producto.id] ? '0.001' : '1'}
                                                                                step={modoGramosProducto[producto.id] ? '1' : '0.001'}
                                                                                value={
                                                                                    modoGramosProducto[producto.id]
                                                                                        ? Math.round((cantidadesAgregar[producto.id] || 0) * 1000)
                                                                                        : (cantidadesAgregar[producto.id] || 1)
                                                                                }
                                                                                onChange={(e) => {
                                                                                    const raw = parseFloat(e.target.value) || 0;
                                                                                    const val = modoGramosProducto[producto.id]
                                                                                        ? Math.round(raw) / 1000
                                                                                        : raw;
                                                                                    setCantidadManualLocal(producto.id, val);
                                                                                }}
                                                                                className="h-8 w-16 border-none bg-transparent p-0 text-center text-sm font-semibold focus:ring-0 dark:text-white"
                                                                            />
                                                                            {esGrameable(producto) && (
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => setModoGramosProducto((prev) => ({
                                                                                        ...prev,
                                                                                        [producto.id]: !prev[producto.id],
                                                                                    }))}
                                                                                    className="h-6 px-1 rounded text-[9px] font-bold bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors"
                                                                                    title={modoGramosProducto[producto.id] ? 'Cambiar a unidad completa' : 'Cambiar a gramos/ml'}
                                                                                >
                                                                                    {modoGramosProducto[producto.id]
                                                                                        ? (producto.unidad_medida === 'lt' ? 'L' : 'kg')
                                                                                        : (producto.unidad_medida === 'lt' ? 'ml' : 'g')}
                                                                                </button>
                                                                            )}
                                                                        </div>
                                                                        <button
                                                                            onClick={() => cambiarCantidadLocal(producto.id, 1)}
                                                                            className="flex h-8 w-8 items-center justify-center rounded-md bg-slate-50 text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                                                        >
                                                                            <Plus className="h-4 w-4" />
                                                                        </button>
                                                                    </div>
                                                                    {modoGramosProducto[producto.id] && (
                                                                        <p className="text-[10px] text-emerald-600 font-bold text-right">
                                                                            = ${Math.round(
                                                                                precioProporcional(
                                                                                    producto,
                                                                                    cantidadesAgregar[producto.id] || 0,
                                                                                ) ?? 0,
                                                                            ).toLocaleString()}
                                                                        </p>
                                                                    )}
                                                                    <Button
                                                                        onClick={() => comprarAhora(producto.id)}
                                                                        className="w-full"
                                                                        variant="default"
                                                                    >
                                                                        <CreditCard className="mr-2 h-4 w-4" />
                                                                        Comprar ahora
                                                                    </Button>
                                                                    <Button
                                                                        onClick={() => agregarAlCarrito(producto.id)}
                                                                        variant="outline"
                                                                        className="w-full"
                                                                        disabled={productoAgregado === producto.id}
                                                                    >
                                                                        {productoAgregado === producto.id ? (
                                                                            <>
                                                                                <CheckCircle2 className="mr-2 h-4 w-4 text-green-500" />
                                                                                Añadido
                                                                            </>
                                                                        ) : (
                                                                            <>
                                                                                <ShoppingCart className="mr-2 h-4 w-4" />
                                                                                Añadir al carrito
                                                                            </>
                                                                        )}
                                                                    </Button>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Pagination */}
                                {productos.last_page > 1 && (
                                    <div className="mt-12 flex justify-center gap-2">
                                        {productos.prev_page_url && (
                                            <Link
                                                href={productos.prev_page_url}
                                                className="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition-colors hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700"
                                            >
                                                Anterior
                                            </Link>
                                        )}
                                        {productos.next_page_url && (
                                            <Link
                                                href={productos.next_page_url}
                                                className="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition-colors hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700"
                                            >
                                                Siguiente
                                            </Link>
                                        )}
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-24 text-center dark:border-slate-800 dark:bg-slate-900/50">
                                <ShoppingBag className="mx-auto h-12 w-12 text-slate-400" />
                                <h3 className="mt-4 text-sm font-semibold text-slate-900 dark:text-white">
                                    No hay productos en esta categoría
                                </h3>
                                <Link
                                    href={`/tienda/${store.slug}`}
                                    className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                                >
                                    Volver a la tienda
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* Sidebar: Random Products & Cart Summary */}
                    <div className="space-y-8">
                        {/* Cart Summary Card */}
                        <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                            <h3 className="flex items-center gap-2 font-semibold text-slate-900 dark:text-white">
                                <ShoppingCart className="h-5 w-5 text-primary" />
                                Tu Carrito
                            </h3>
                            <div className="mt-4 flex flex-col items-center justify-center py-4 border-t border-slate-100 dark:border-slate-800">
                                <span className="text-3xl font-bold text-slate-900 dark:text-white">
                                    {totalItems}
                                </span>
                                <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">
                                    Items en total
                                </span>
                            </div>
                            <Sheet open={carritoAbierto} onOpenChange={setCarritoAbierto}>
                                <SheetTrigger asChild>
                                    <Button className="w-full mt-2" variant="default" disabled={totalItems === 0}>
                                        Ver Carrito
                                    </Button>
                                </SheetTrigger>
                                <SheetContent className="flex w-full flex-col sm:max-w-lg">
                                    <SheetHeader>
                                        <SheetTitle className="flex items-center gap-2">
                                            <ShoppingCart className="h-5 w-5" />
                                            Tu Carrito de Compras
                                        </SheetTitle>
                                    </SheetHeader>
                                    <div className="flex-1 overflow-y-auto py-4">
                                        {productosEnCarrito.length === 0 ? (
                                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                                <ShoppingBag className="h-16 w-16 text-slate-300" />
                                                <p className="mt-4 text-lg font-medium text-slate-900 dark:text-white">
                                                    Tu carrito está vacío
                                                </p>
                                            </div>
                                        ) : (
                                            <div className="space-y-4">
                                                {productosEnCarrito.map((producto) => (
                                                    <div
                                                        key={producto.id}
                                                        className="flex gap-4 rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                                                    >
                                                        <div className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                                                            {producto.imagen ? (
                                                                <img
                                                                    src={`/storage/${producto.imagen}`}
                                                                    alt={producto.nombre}
                                                                    className="h-full w-full object-cover"
                                                                />
                                                            ) : (
                                                                <div className="flex h-full w-full items-center justify-center">
                                                                    <Store className="h-6 w-6 text-slate-300" />
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="flex-1">
                                                            <h4 className="text-sm font-medium text-slate-900 dark:text-white line-clamp-1">
                                                                {producto.nombre}
                                                            </h4>
                                                            <div className="mt-1 flex items-center justify-between">
                                                                <span className="text-sm font-semibold text-primary">
                                                                    ${Number(producto.precio_venta).toFixed(0)}
                                                                </span>
                                                                <div className="flex items-center gap-2">
                                                                    <button
                                                                        onClick={() => cambiarCantidad(producto.id, -1)}
                                                                        className="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                                    >
                                                                        <Minus className="h-3 w-3" />
                                                                    </button>
                                                                    <div className="flex items-center gap-1">
                                                                        <input
                                                                            type="number"
                                                                            min="0"
                                                                            step={modoGramosProducto[producto.id] ? '1' : '0.001'}
                                                                            value={
                                                                                modoGramosProducto[producto.id]
                                                                                    ? Math.round((carrito[producto.id] || 0) * 1000)
                                                                                    : (carrito[producto.id] || 0)
                                                                            }
                                                                            onChange={(e) => {
                                                                                const raw = parseFloat(e.target.value) || 0;
                                                                                const val = modoGramosProducto[producto.id]
                                                                                    ? Math.round(raw) / 1000
                                                                                    : raw;
                                                                                setCantidadManual(producto.id, val);
                                                                            }}
                                                                            className="h-7 w-14 rounded-md border border-slate-200 bg-white px-1 text-center text-sm font-medium text-slate-900 focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                                        />
                                                                        {esGrameable(producto) && (
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => setModoGramosProducto((prev) => ({
                                                                                    ...prev,
                                                                                    [producto.id]: !prev[producto.id],
                                                                                }))}
                                                                                className="h-5 px-1 rounded text-[8px] font-bold bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors"
                                                                                title={modoGramosProducto[producto.id] ? 'Cambiar a unidad completa' : 'Cambiar a gramos/ml'}
                                                                            >
                                                                                {modoGramosProducto[producto.id]
                                                                                    ? (producto.unidad_medida === 'lt' ? 'L' : 'kg')
                                                                                    : (producto.unidad_medida === 'lt' ? 'ml' : 'g')}
                                                                            </button>
                                                                        )}
                                                                    </div>
                                                                    <button
                                                                        onClick={() => cambiarCantidad(producto.id, 1)}
                                                                        className="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                                    >
                                                                        <Plus className="h-3 w-3" />
                                                                    </button>
                                                                    <button
                                                                        onClick={() => eliminarDelCarrito(producto.id)}
                                                                        className="ml-2 text-slate-400 hover:text-red-500"
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            {modoGramosProducto[producto.id] && (
                                                                <p className="mt-1 text-xs text-emerald-600 font-bold">
                                                                    Subtotal: ${Math.round(producto.precio_venta * (carrito[producto.id] || 0)).toLocaleString()}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                    {productosEnCarrito.length > 0 && (
                                        <div className="border-t pt-4 space-y-4">
                                            <div className="flex justify-between items-center">
                                                <span className="font-semibold text-slate-900 dark:text-white">Total</span>
                                                <span className="text-xl font-bold text-primary">${totalCarrito.toFixed(0)}</span>
                                            </div>
                                            <Button onClick={() => setCheckoutAbierto(true)} className="w-full">
                                                Proceder al Pago
                                            </Button>
                                        </div>
                                    )}
                                </SheetContent>
                            </Sheet>
                        </div>

                        {/* Random Products Section */}
                        {randomProductos.length > 0 && (
                            <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                                <h3 className="mb-4 font-semibold text-slate-900 dark:text-white">
                                    Otros productos de {store.title}
                                </h3>
                                <div className="space-y-4">
                                    {randomProductos.map((producto) => (
                                        <Link
                                            key={producto.id}
                                            href={`/tienda/${store.slug}`} // Or to its category if we knew it
                                            className="group flex gap-4 transition-all"
                                        >
                                            <div className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                                                {producto.imagen ? (
                                                    <img
                                                        src={`/storage/${producto.imagen}`}
                                                        alt={producto.nombre}
                                                        className="h-full w-full object-cover transition-transform group-hover:scale-110"
                                                    />
                                                ) : (
                                                    <div className="flex h-full w-full items-center justify-center">
                                                        <ShoppingBag className="h-6 w-6 text-slate-300" />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex flex-1 flex-col justify-center">
                                                <h4 className="text-sm font-medium text-slate-900 group-hover:text-primary transition-colors dark:text-white line-clamp-1">
                                                    {producto.nombre}
                                                </h4>
                                                <p className="text-sm font-bold text-primary">
                                                    ${Number(producto.precio_venta).toFixed(0)}
                                                </p>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Checkout Dialog Reused */}
            <Dialog open={checkoutAbierto} onOpenChange={setCheckoutAbierto}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Finalizar Pedido</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="nombre">Nombre completo *</Label>
                            <Input
                                id="nombre"
                                value={datosCheckout.nombre_cliente}
                                onChange={(e) => setDatosCheckout({ ...datosCheckout, nombre_cliente: e.target.value })}
                                placeholder="Tu nombre"
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="telefono">Teléfono</Label>
                            <Input
                                id="telefono"
                                value={datosCheckout.telefono_cliente}
                                onChange={(e) => setDatosCheckout({ ...datosCheckout, telefono_cliente: e.target.value })}
                                placeholder="Tu número de teléfono"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="direccion">Dirección de entrega</Label>
                            <Input
                                id="direccion"
                                value={datosCheckout.direccion_cliente}
                                onChange={(e) => setDatosCheckout({ ...datosCheckout, direccion_cliente: e.target.value })}
                                placeholder="Dirección donde recibirás el pedido"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="notas">Notas adicionales</Label>
                            <Input
                                id="notas"
                                value={datosCheckout.notas}
                                onChange={(e) => setDatosCheckout({ ...datosCheckout, notas: e.target.value })}
                                placeholder="Alguna instrucción especial"
                            />
                        </div>
                        <div className="grid gap-2 mt-2">
                            <Label>Método de Pago</Label>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <button
                                    type="button"
                                    onClick={() => setDatosCheckout({...datosCheckout, metodo_pago: 'efectivo'})}
                                    className={`flex flex-col items-center justify-center p-3 rounded-xl border transition-all ${datosCheckout.metodo_pago === 'efectivo' ? 'border-primary bg-primary/5 text-primary shadow-sm' : 'border-slate-100 hover:border-slate-200 hover:bg-slate-50 text-slate-400'}`}
                                >
                                    <Wallet className="h-4 w-4 mb-1" />
                                    <span className="text-[10px] font-bold uppercase">Local</span>
                                </button>
                                
                                {paymentConfig?.is_active && (
                                    <button
                                        type="button"
                                        onClick={() => setDatosCheckout({...datosCheckout, metodo_pago: 'webpay'})}
                                        className={`flex flex-col items-center justify-center p-3 rounded-xl border transition-all ${datosCheckout.metodo_pago === 'webpay' ? 'border-indigo-500 bg-indigo-50 text-indigo-600 shadow-sm' : 'border-slate-100 hover:border-slate-200 hover:bg-slate-50 text-slate-400'}`}
                                    >
                                        <CreditCard className="h-4 w-4 mb-1" />
                                        <span className="text-[10px] font-bold uppercase">Webpay</span>
                                    </button>
                                )}

                                {paymentConfig?.paypal_active && (
                                    <button
                                        type="button"
                                        onClick={() => setDatosCheckout({...datosCheckout, metodo_pago: 'paypal'})}
                                        className={`flex flex-col items-center justify-center p-3 rounded-xl border transition-all ${datosCheckout.metodo_pago === 'paypal' ? 'border-blue-500 bg-blue-50 text-blue-600 shadow-sm' : 'border-slate-100 hover:border-slate-200 hover:bg-slate-50 text-slate-400'}`}
                                    >
                                        <CreditCard className="h-4 w-4 mb-1" />
                                        <span className="text-[10px] font-bold uppercase">PayPal</span>
                                    </button>
                                )}

                                {paymentConfig?.mercadopago_active && (
                                    <button
                                        type="button"
                                        onClick={() => setDatosCheckout({...datosCheckout, metodo_pago: 'mercadopago'})}
                                        className={`flex flex-col items-center justify-center p-3 rounded-xl border transition-all ${datosCheckout.metodo_pago === 'mercadopago' ? 'border-sky-500 bg-sky-50 text-sky-600 shadow-sm' : 'border-slate-100 hover:border-slate-200 hover:bg-slate-50 text-slate-400'}`}
                                    >
                                        <CreditCard className="h-4 w-4 mb-1" />
                                        <span className="text-[10px] font-bold uppercase">M. Pago</span>
                                    </button>
                                )}
                            </div>
                        </div>
                        <div className="rounded-lg bg-slate-50 p-3 dark:bg-slate-800 mt-2">
                            <div className="flex justify-between text-sm">
                                <span>Subtotal:</span>
                                <span>${totalCarrito.toFixed(0)}</span>
                            </div>
                            <div className="mt-2 flex justify-between border-t border-slate-200 pt-2 font-bold dark:border-slate-700">
                                <span>Total:</span>
                                <span className="text-primary">${totalCarrito.toFixed(0)}</span>
                            </div>
                        </div>
                        <Button
                            onClick={enviarCheckout}
                            disabled={procesando || !datosCheckout.nombre_cliente}
                            className={cn(
                                "w-full transition-all",
                                datosCheckout.metodo_pago === 'webpay' && "bg-indigo-600 hover:bg-indigo-700",
                                datosCheckout.metodo_pago === 'paypal' && "bg-blue-600 hover:bg-blue-700",
                                datosCheckout.metodo_pago === 'mercadopago' && "bg-sky-600 hover:bg-sky-700"
                            )}
                        >
                            {procesando ? 'Procesando...' : (
                                datosCheckout.metodo_pago === 'efectivo' ? 'Confirmar Pedido' : 
                                `Pagar con ${datosCheckout.metodo_pago.charAt(0).toUpperCase() + datosCheckout.metodo_pago.slice(1)}`
                            )}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

import { Head, useForm, usePage, Link, router } from '@inertiajs/react';
import {
    format,
    addMonths,
    subMonths,
    startOfMonth,
    endOfMonth,
    eachDayOfInterval,
    isSameDay,
    isToday,
    isBefore,
    startOfDay,
    getDay,
} from 'date-fns';
import { es } from 'date-fns/locale';
import {
    ShoppingCart,
    ShoppingBag,
    Trash2,
    Calendar,
    Phone,
    MessageCircle,
    Mail,
    Plus,
    Minus,
    Check,
    Truck,
    Clock,
    XCircle,
    CheckCircle2,
    AlertCircle,
    Building,
    LogOut,
    User as UserIcon,
    ChevronDown,
    DollarSign,
    CalendarCheck,
    ShieldAlert,
    LifeBuoy,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import React, { useState, useEffect } from 'react';
import { Toaster, toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCountry } from '@/hooks/use-country';
import cliente from '@/routes/cliente';

interface Product {
    id: number;
    nombre: string;
    codigo: string;
    descripcion: string | null;
    precio_venta: number;
    imagen: string | null;
    categoria_id: number | null;
    categoria: string | null;
    stock: number;
    owner_id: number;
    requires_appointment: boolean;
    duracion: number | null;
    is_service: boolean;
    course_id: number | null;
    course_slug: string | null;
    booking_slug: string | null;
    providers: { id: number; name: string; photo: string }[];
}

interface Category {
    id: number;
    nombre: string;
    activo: boolean;
}

interface OrderItem {
    id: number;
    producto_id: number;
    nombre_producto: string;
    precio_unitario: number;
    cantidad: number;
    subtotal: number;
}

interface Order {
    id: number;
    numero_pedido: string;
    estado:
        | 'pendiente'
        | 'confirmado'
        | 'preparando'
        | 'enviado'
        | 'entregado'
        | 'cancelado';
    total: number;
    subtotal: number;
    impuesto: number;
    notas: string | null;
    nombre_cliente: string;
    telefono_cliente: string;
    direccion_cliente: string;
    metodo_pago: string;
    created_at: string;
    items: OrderItem[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
interface PaginationProps {
    links: PaginationLink[];
}

interface TicketItem {
    id: number;
    titulo: string;
    descripcion: string | null;
    prioridad: string;
    estado: string;
    created_at: string;
    asignado_a: string | null;
}

interface DashboardProps {
    productos: Product[];
    categorias: Category[];
    pedidos: {
        data: Order[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
    citasCliente: Cita[];
    tickets: TicketItem[];
    appointments: {
        start_time: string;
        end_time: string;
        producto_id: number;
        provider_id: number;
    }[];
    filters: {
        search?: string;
        categoria_id?: string;
    };
    business: {
        name: string;
        logo: string | null;
        cover: string | null;
        primary_color: string;
        secondary_color: string;
        phone: string | null;
        email: string | null;
        owner_id: number;
    };
    store: {
        id: number;
        slug: string;
        name: string;
        logo: string | null;
    } | null;
}

interface Cita {
    id: number;
    start_time: string;
    end_time: string;
    status: string;
    payment_status: string;
    notes: string | null;
    producto: Product | null;
    created_at: string;
}

interface CartItem {
    product: Product;
    quantity: number;
}

export default function ClientDashboard({
    productos,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    categorias,
    pedidos,
    citasCliente,
    tickets,
    appointments,
    filters,
    business,
    store,
}: DashboardProps) {
    const { auth } = usePage().props as any;
    const { currency } = useCountry();
    const currentUser = auth.user;

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState<number | 'all'>(
        filters.categoria_id ? parseInt(filters.categoria_id) : 'all',
    );
    const cartKey = `cliente_cart_${business.owner_id}`;
    const [cart, setCart] = useState<CartItem[]>(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem(cartKey);
            if (saved) {
                try {
                    return JSON.parse(saved);
                } catch {
                    /* ignore */
                }
            }
        }
        return [];
    });
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [activeTab, setActiveTab] = useState<
        'catalog' | 'orders' | 'support'
    >('catalog');
    const [expandedOrder, setExpandedOrder] = useState<number | null>(null);
    const [bookingProduct, setBookingProduct] = useState<Product | null>(null);
    const [bookingMonth, setBookingMonth] = useState(new Date());
    const [selectedDay, setSelectedDay] = useState<Date | null>(null);
    const [selectedTime, setSelectedTime] = useState<string>('');
    const [selectedProvider, setSelectedProvider] = useState<number | null>(
        null,
    );
    const [bookingSuccess, setBookingSuccess] = useState<{
        servicio: string;
        fecha: string;
        hora: string;
    } | null>(null);
    const [ticketData, setTicketData] = useState({
        titulo: '',
        descripcion: '',
        prioridad: 'media',
    });
    const [ticketLoading, setTicketLoading] = useState(false);

    // Initialize checkout form with client defaults
    const { data, setData, processing, reset } = useForm({
        items: [] as { producto_id: number; cantidad: number }[],
        nombre_cliente: currentUser.name || '',
        telefono_cliente: currentUser.telefono || '',
        direccion_cliente: currentUser.direccion || '',
        metodo_pago: 'efectivo',
        notas: '',
    });

    useEffect(() => {
        localStorage.setItem(cartKey, JSON.stringify(cart));
    }, [cart, cartKey]);

    const primaryColor = business.primary_color || '#4f46e5';
    const secondaryColor = business.secondary_color || '#06b6d4';

    // Handle search and category filters
    const applyFilters = (searchVal: string, catId: number | 'all') => {
        router.get(
            cliente.dashboard().url,
            {
                search: searchVal || undefined,
                categoria_id: catId !== 'all' ? catId : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters(searchTerm, selectedCategory);
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const handleCategoryClick = (catId: number | 'all') => {
        setSelectedCategory(catId);
        applyFilters(searchTerm, catId);
    };

    // Cart Management
    const addToCart = (product: Product) => {
        const existingItem = cart.find(
            (item) => item.product.id === product.id,
        );

        if (existingItem) {
            setCart(
                cart.map((item) =>
                    item.product.id === product.id
                        ? { ...item, quantity: item.quantity + 1 }
                        : item,
                ),
            );
        } else {
            setCart([...cart, { product, quantity: 1 }]);
        }
        toast.success(`${product.nombre} añadido al carrito.`);
    };

    const updateQuantity = (productId: number, delta: number) => {
        const item = cart.find((i) => i.product.id === productId);
        if (!item) return;

        const newQty = item.quantity + delta;
        if (newQty <= 0) {
            setCart(cart.filter((i) => i.product.id !== productId));
            toast.info(`${item.product.nombre} quitado del carrito.`);
            return;
        }

        setCart(
            cart.map((i) =>
                i.product.id === productId ? { ...i, quantity: newQty } : i,
            ),
        );
    };

    const handleQuantityInput = (productId: number, value: string) => {
        const parsed = parseInt(value, 10);
        if (isNaN(parsed) || parsed <= 0) {
            setCart(cart.filter((i) => i.product.id !== productId));
            return;
        }
        setCart(
            cart.map((i) =>
                i.product.id === productId ? { ...i, quantity: parsed } : i,
            ),
        );
    };

    const removeFromCart = (productId: number) => {
        setCart(cart.filter((i) => i.product.id !== productId));
        toast.info('Producto quitado del carrito.');
    };

    // Calculations
    const cartSubtotal = cart.reduce(
        (sum, item) => sum + item.product.precio_venta * item.quantity,
        0,
    );
    const cartTax = cartSubtotal * 0.19;
    const cartTotal = cartSubtotal + cartTax;

    // Checkout submission
    const handleCheckout = (e: React.FormEvent) => {
        e.preventDefault();
        if (cart.length === 0) {
            toast.error('Tu carrito de compras está vacío.');
            return;
        }

        const itemsPayload = cart.map((item) => ({
            producto_id: item.product.id,
            cantidad: item.quantity,
        }));

        router.post(
            cliente.pedidos.store().url,
            {
                ...data,
                items: itemsPayload,
            },
            {
                onSuccess: () => {
                    setCart([]);
                    setIsCartOpen(false);
                    setActiveTab('orders');
                    toast.success('¡Pedido realizado con éxito!');
                    reset();
                },
                onError: (errs) => {
                    if (errs.error) {
                        toast.error(errs.error);
                    } else {
                        toast.error(
                            'Por favor, revise los campos del despacho.',
                        );
                    }
                },
            },
        );
    };

    // Cancellation
    const handleCancelOrder = (orderId: number) => {
        if (
            confirm(
                '¿Está seguro de que desea cancelar este pedido? Se repondrá el inventario.',
            )
        ) {
            router.post(
                cliente.pedidos.cancelar(orderId).url,
                {},
                {
                    onSuccess: () => {
                        toast.success('Pedido cancelado con éxito.');
                    },
                    onError: (errs) => {
                        toast.error(
                            errs.error || 'No se pudo cancelar el pedido.',
                        );
                    },
                },
            );
        }
    };

    // Helper formatting
    const formatPrice = (value: number) => {
        return (
            '$' +
            new Intl.NumberFormat(currency.locale).format(Math.round(value))
        );
    };

    const getStatusBadge = (status: Order['estado']) => {
        const styles: Record<Order['estado'], string> = {
            pendiente:
                'bg-amber-500/20 text-amber-600 border border-amber-500/30',
            confirmado:
                'bg-blue-500/20 text-blue-600 border border-blue-500/30',
            preparando:
                'bg-purple-500/20 text-purple-600 border border-purple-500/30',
            enviado:
                'bg-indigo-500/20 text-indigo-600 border border-indigo-500/30',
            entregado:
                'bg-emerald-500/20 text-emerald-600 border border-emerald-500/30',
            cancelado: 'bg-rose-500/20 text-rose-600 border border-rose-500/30',
        };

        const labels: Record<Order['estado'], string> = {
            pendiente: 'Pendiente',
            confirmado: 'Confirmado',
            preparando: 'Preparando',
            enviado: 'Despachado',
            entregado: 'Entregado',
            cancelado: 'Cancelado',
        };

        return (
            <Badge
                className={`${styles[status]} rounded-full px-2 py-0.5 font-bold`}
            >
                {labels[status]}
            </Badge>
        );
    };

    const handleSupportTicket = async (e: React.FormEvent) => {
        e.preventDefault();
        setTicketLoading(true);
        router.post(
            cliente.tickets.store().url,
            {
                titulo: ticketData.titulo,
                descripcion: ticketData.descripcion,
                prioridad: ticketData.prioridad,
            },
            {
                onSuccess: () => {
                    setTicketData({
                        titulo: '',
                        descripcion: '',
                        prioridad: 'media',
                    });
                    setTicketLoading(false);
                    setActiveTab('orders');
                },
                onError: () => {
                    setTicketLoading(false);
                },
            },
        );
    };

    return (
        <div
            className="min-h-screen bg-slate-50 pb-12 text-slate-900"
            style={
                {
                    '--brand-primary': primaryColor,
                    '--brand-secondary': secondaryColor,
                } as React.CSSProperties
            }
        >
            <Head title={`Portal de Clientes - ${business.name}`} />

            <Toaster position="top-right" richColors />

            {/* Header Banner */}
            <div className="relative h-48 w-full overflow-hidden bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 md:h-64">
                {business.cover && (
                    <img
                        src={business.cover}
                        alt="Business Cover"
                        className="absolute inset-0 h-full w-full object-cover opacity-40"
                    />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 mx-auto flex max-w-7xl flex-col justify-between gap-4 px-4 py-6 md:flex-row md:items-end md:px-8">
                    <div className="flex items-center gap-4">
                        <div className="h-16 w-16 shrink-0 overflow-hidden rounded-2xl border border-white/20 bg-white p-1.5 shadow-xl md:h-20 md:w-20">
                            {business.logo ? (
                                <img
                                    src={business.logo}
                                    alt={business.name}
                                    className="h-full w-full rounded-xl object-contain"
                                />
                            ) : (
                                <div className="text-brand-primary flex h-full w-full items-center justify-center rounded-xl bg-indigo-50 text-2xl font-bold">
                                    {business.name
                                        .substring(0, 2)
                                        .toUpperCase()}
                                </div>
                            )}
                        </div>
                        <div>
                            <h1 className="text-xl leading-tight font-black text-white md:text-3xl">
                                {business.name}
                            </h1>
                            <p className="mt-1 flex items-center gap-2 text-xs font-semibold text-slate-300 md:text-sm">
                                <Building className="h-4 w-4 text-[var(--brand-secondary)]" />{' '}
                                Portal de Clientes
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 self-start rounded-2xl border border-white/10 bg-white/10 p-2 text-white backdrop-blur-md md:self-end">
                        <UserIcon className="ml-1 h-4 w-4 text-[var(--brand-secondary)]" />
                        <span className="mr-2 text-xs font-bold">
                            {currentUser.name}
                        </span>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="rounded-xl bg-rose-500/80 p-1.5 text-white transition-all hover:bg-rose-600"
                        >
                            <LogOut className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </div>

            {/* Content Container */}
            <main className="mx-auto mt-8 grid max-w-7xl grid-cols-1 gap-8 px-4 md:px-8 lg:grid-cols-12">
                {/* Main section */}
                <div className="space-y-6 lg:col-span-8">
                    {/* Navigation Tabs */}
                    <div className="flex max-w-md rounded-2xl border border-slate-200 bg-slate-200/60 p-1.5">
                        <button
                            onClick={() => setActiveTab('catalog')}
                            className={`flex flex-1 items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-black transition-all ${
                                activeTab === 'catalog'
                                    ? 'bg-white text-slate-900 shadow-md'
                                    : 'text-slate-600 hover:text-slate-950'
                            }`}
                        >
                            <ShoppingBag className="h-4 w-4" />
                            Catálogo
                        </button>
                        <button
                            onClick={() => setActiveTab('orders')}
                            className={`flex flex-1 items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-black transition-all ${activeTab === 'orders' ? 'bg-white text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-950'}`}
                        >
                            <Clock className="h-4 w-4" />
                            Pedidos
                        </button>
                        <button
                            onClick={() => setActiveTab('support')}
                            className={`flex flex-1 items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-black transition-all ${activeTab === 'support' ? 'bg-white text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-950'}`}
                        >
                            <ShieldAlert className="h-4 w-4" />
                            Soporte
                        </button>
                    </div>

                    {activeTab === 'catalog' && (
                        <>
                            <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                {' '}
                                <div>
                                    {' '}
                                    <h2 className="text-2xl font-black text-slate-900">
                                        {' '}
                                        Productos de {business.name}{' '}
                                    </h2>{' '}
                                    <p className="text-sm text-slate-500">
                                        {' '}
                                        Catálogo privado asignado a tu cuenta
                                        cliente.{' '}
                                    </p>{' '}
                                </div>{' '}
                                <div className="flex items-center gap-2">
                                    {' '}
                                    <Badge className="rounded-xl bg-slate-900 px-3 py-1 text-white">
                                        {' '}
                                        {productos.length} productos{' '}
                                    </Badge>{' '}
                                </div>{' '}
                            </div>{' '}
                            {/* Products Grid */}{' '}
                            {productos.length === 0 ? (
                                <div className="space-y-4 rounded-3xl border border-slate-100 bg-white p-12 text-center shadow-sm">
                                    {' '}
                                    <AlertCircle className="mx-auto h-12 w-12 text-slate-400" />{' '}
                                    <h3 className="text-lg font-black">
                                        {' '}
                                        No se encontraron productos{' '}
                                    </h3>{' '}
                                    <p className="mx-auto max-w-sm text-sm text-slate-500">
                                        {' '}
                                        El negocio aún no tiene productos
                                        disponibles para su catálogo.{' '}
                                    </p>{' '}
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3">
                                    {' '}
                                    {productos.map((prod) => {
                                        const cartQty =
                                            cart.find(
                                                (i) => i.product.id === prod.id,
                                            )?.quantity || 0;
                                        return (
                                            <div
                                                key={prod.id}
                                                className="group relative flex flex-col justify-between overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm transition-all hover:scale-[1.01] hover:shadow-md"
                                            >
                                                {' '}
                                                <div>
                                                    {' '}
                                                    {/* IMAGE */}{' '}
                                                    <div className="relative h-44 w-full overflow-hidden bg-slate-100">
                                                        {' '}
                                                        {prod.imagen ? (
                                                            <img
                                                                src={
                                                                    prod.imagen.startsWith(
                                                                        'http',
                                                                    )
                                                                        ? prod.imagen
                                                                        : `/storage/${prod.imagen}`
                                                                }
                                                                alt={
                                                                    prod.nombre
                                                                }
                                                                className="h-full w-full object-cover transition-all group-hover:scale-105"
                                                            />
                                                        ) : (
                                                            <div className="flex h-full w-full items-center justify-center bg-indigo-50/50 text-slate-400">
                                                                {' '}
                                                                <ShoppingBag className="text-brand-primary h-10 w-10 opacity-40" />{' '}
                                                            </div>
                                                        )}{' '}
                                                        {/* BADGES */}{' '}
                                                        <div className="absolute top-3 right-3 flex flex-col items-end gap-1.5">
                                                            {' '}
                                                            <Badge className="rounded-full border-none bg-slate-900/80 px-2 py-0.5 text-[10px] font-bold text-white backdrop-blur-md">
                                                                {' '}
                                                                {prod.categoria ||
                                                                    'General'}{' '}
                                                            </Badge>{' '}
                                                        </div>{' '}
                                                    </div>{' '}
                                                    {/* INFO */}{' '}
                                                    <div className="space-y-2 p-5">
                                                        {' '}
                                                        <h3 className="group-hover:text-brand-primary line-clamp-1 text-base font-black text-slate-900 transition-colors">
                                                            {' '}
                                                            {prod.nombre}{' '}
                                                        </h3>{' '}
                                                        <p className="text-xs font-bold tracking-wider text-slate-400 uppercase">
                                                            {' '}
                                                            Código:{' '}
                                                            {prod.codigo}{' '}
                                                        </p>{' '}
                                                        {/* OWNER */}{' '}
                                                        <p className="text-[11px] font-bold text-indigo-600">
                                                            {' '}
                                                            Distribuido por{' '}
                                                            {business.name}{' '}
                                                        </p>{' '}
                                                        <p className="line-clamp-2 min-h-[2rem] text-xs text-slate-500">
                                                            {' '}
                                                            {prod.descripcion ||
                                                                'Sin descripción disponible.'}{' '}
                                                        </p>{' '}
                                                    </div>{' '}
                                                </div>{' '}
                                                {/* FOOTER */}{' '}
                                                <div className="mt-auto border-t border-slate-50 p-5 pt-0">
                                                    {' '}
                                                    <div className="flex items-center justify-between pt-4">
                                                        {' '}
                                                        {/* PRICE */}{' '}
                                                        <div className="flex flex-col">
                                                            {' '}
                                                            <span className="text-xs font-bold text-slate-400">
                                                                {' '}
                                                                Precio{' '}
                                                            </span>{' '}
                                                            <span className="text-lg font-black text-slate-950">
                                                                {' '}
                                                                {formatPrice(
                                                                    prod.precio_venta,
                                                                )}{' '}
                                                            </span>{' '}
                                                        </div>{' '}
                                                        {/* CART */}{' '}
                                                        {cartQty > 0 ? (
                                                            <div className="flex items-center rounded-xl border border-slate-200 bg-slate-100 p-1">
                                                                {' '}
                                                                <button
                                                                    onClick={() =>
                                                                        updateQuantity(
                                                                            prod.id,
                                                                            -1,
                                                                        )
                                                                    }
                                                                    className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-white"
                                                                >
                                                                    {' '}
                                                                    <Minus className="h-3.5 w-3.5" />{' '}
                                                                </button>{' '}
                                                                <input
                                                                    type="number"
                                                                    min="1"
                                                                    value={
                                                                        cartQty
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleQuantityInput(
                                                                            prod.id,
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    className="w-12 [appearance:textfield] border-none bg-transparent text-center text-sm font-bold text-slate-800 outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                                />{' '}
                                                                <button
                                                                    onClick={() =>
                                                                        updateQuantity(
                                                                            prod.id,
                                                                            1,
                                                                        )
                                                                    }
                                                                    className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-white"
                                                                >
                                                                    {' '}
                                                                    <Plus className="h-3.5 w-3.5" />{' '}
                                                                </button>{' '}
                                                            </div>
                                                        ) : prod.course_id ? (
                                                            <Link
                                                                href={`/cursos/${prod.course_slug}`}
                                                                className="btn-brand-primary inline-flex h-8 shrink-0 items-center justify-center gap-1.5 rounded-xl px-4 text-sm font-bold shadow-sm"
                                                            >
                                                                <ShoppingBag className="h-3.5 w-3.5" />{' '}
                                                                Inscribirse
                                                            </Link>
                                                        ) : prod.requires_appointment ||
                                                          (prod.providers &&
                                                              prod.providers
                                                                  .length >
                                                                  0) ? (
                                                            <button
                                                                onClick={() => {
                                                                    setBookingProduct(
                                                                        prod,
                                                                    );
                                                                    setBookingMonth(
                                                                        new Date(),
                                                                    );
                                                                    setSelectedDay(
                                                                        null,
                                                                    );
                                                                    setSelectedTime(
                                                                        '',
                                                                    );
                                                                    setSelectedProvider(
                                                                        null,
                                                                    );
                                                                }}
                                                                className="btn-brand-primary inline-flex h-8 shrink-0 items-center justify-center gap-1.5 rounded-xl px-4 text-sm font-bold shadow-sm"
                                                            >
                                                                <Calendar className="h-3.5 w-3.5" />{' '}
                                                                Agendar cita
                                                            </button>
                                                        ) : (
                                                            <Button
                                                                onClick={() =>
                                                                    addToCart(
                                                                        prod,
                                                                    )
                                                                }
                                                                size="sm"
                                                                className="btn-brand-primary rounded-xl px-4 font-bold shadow-sm"
                                                            >
                                                                {' '}
                                                                Comprar{' '}
                                                            </Button>
                                                        )}{' '}
                                                    </div>{' '}
                                                </div>{' '}
                                            </div>
                                        );
                                    })}{' '}
                                </div>
                            )}
                        </>
                    )}

                    {/* Orders & Appointments History Tab */}
                    {activeTab === 'orders' && (
                        <div className="space-y-6">
                            {citasCliente.length > 0 && (
                                <div className="space-y-3">
                                    <h3 className="flex items-center gap-2 text-lg font-black text-slate-900">
                                        <CalendarCheck className="text-brand-primary h-5 w-5" />
                                        Citas Agendadas
                                    </h3>
                                    {citasCliente.map((cita) => {
                                        const statusStyles: Record<
                                            string,
                                            string
                                        > = {
                                            pendiente:
                                                'bg-amber-50 text-amber-700 border-amber-200',
                                            confirmada:
                                                'bg-blue-50 text-blue-700 border-blue-200',
                                            completada:
                                                'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            cancelada:
                                                'bg-rose-50 text-rose-700 border-rose-200',
                                        };
                                        const statusLabel: Record<
                                            string,
                                            string
                                        > = {
                                            pendiente: 'Pendiente',
                                            confirmada: 'Confirmada',
                                            completada: 'Completada',
                                            cancelada: 'Cancelada',
                                        };
                                        return (
                                            <div
                                                key={cita.id}
                                                className="overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm"
                                            >
                                                <div className="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center">
                                                    <div className="flex items-center gap-4">
                                                        <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 font-bold text-emerald-600 shadow-sm">
                                                            <Calendar className="h-5 w-5" />
                                                        </div>
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <h3 className="text-sm font-black text-slate-900 md:text-base">
                                                                    {cita
                                                                        .producto
                                                                        ?.nombre ||
                                                                        'Servicio'}
                                                                </h3>
                                                                <span
                                                                    className={`rounded-full border px-2 py-0.5 text-[11px] font-bold ${statusStyles[cita.status] || 'border-slate-200 bg-slate-50 text-slate-600'}`}
                                                                >
                                                                    {statusLabel[
                                                                        cita
                                                                            .status
                                                                    ] ||
                                                                        cita.status}
                                                                </span>
                                                            </div>
                                                            <p className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-slate-400">
                                                                <Calendar className="h-3.5 w-3.5" />
                                                                {new Date(
                                                                    cita.start_time,
                                                                ).toLocaleDateString(
                                                                    currency.locale,
                                                                    {
                                                                        day: '2-digit',
                                                                        month: 'short',
                                                                        year: 'numeric',
                                                                        hour: '2-digit',
                                                                        minute: '2-digit',
                                                                    },
                                                                )}
                                                                {' — '}
                                                                {new Date(
                                                                    cita.end_time,
                                                                ).toLocaleTimeString(
                                                                    currency.locale,
                                                                    {
                                                                        hour: '2-digit',
                                                                        minute: '2-digit',
                                                                    },
                                                                )}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center justify-between gap-6 border-t border-slate-100 pt-3 sm:justify-end sm:border-t-0 sm:pt-0">
                                                        <div className="text-left sm:text-right">
                                                            <span className="block text-xs font-bold tracking-wider text-slate-400 uppercase">
                                                                {cita.payment_status ===
                                                                'pagado'
                                                                    ? 'Pagado'
                                                                    : 'Pendiente pago'}
                                                            </span>
                                                            {cita.notes && (
                                                                <p className="mt-0.5 text-xs text-slate-400">
                                                                    {cita.notes}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            <div className="space-y-3">
                                <h3 className="flex items-center gap-2 text-lg font-black text-slate-900">
                                    <ShoppingBag className="text-brand-primary h-5 w-5" />
                                    Pedidos
                                </h3>
                                {pedidos.data.length === 0 ? (
                                    <div className="space-y-4 rounded-3xl border border-slate-100 bg-white p-12 text-center shadow-sm">
                                        <Clock className="mx-auto h-12 w-12 text-slate-400" />
                                        <h3 className="text-lg font-black">
                                            No tienes pedidos anteriores
                                        </h3>
                                        <p className="mx-auto max-w-sm text-sm text-slate-500">
                                            Todos tus pedidos confirmados o en
                                            tránsito se desplegarán en esta
                                            sección.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {pedidos.data.map((order) => {
                                            const isExpanded =
                                                expandedOrder === order.id;

                                            return (
                                                <div
                                                    key={order.id}
                                                    className="overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm transition-all"
                                                >
                                                    {/* Header summarized bar */}
                                                    <div
                                                        onClick={() =>
                                                            setExpandedOrder(
                                                                isExpanded
                                                                    ? null
                                                                    : order.id,
                                                            )
                                                        }
                                                        className="flex cursor-pointer flex-col justify-between gap-4 p-5 hover:bg-slate-50/50 sm:flex-row sm:items-center"
                                                    >
                                                        <div className="flex items-center gap-4">
                                                            <div className="text-brand-primary flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 font-bold shadow-sm">
                                                                <ShoppingBag className="h-5 w-5" />
                                                            </div>
                                                            <div>
                                                                <div className="flex items-center gap-2">
                                                                    <h3 className="text-sm font-black text-slate-900 md:text-base">
                                                                        {
                                                                            order.numero_pedido
                                                                        }
                                                                    </h3>
                                                                    {getStatusBadge(
                                                                        order.estado,
                                                                    )}
                                                                </div>
                                                                <p className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-slate-400">
                                                                    <Calendar className="h-3.5 w-3.5" />
                                                                    {new Date(
                                                                        order.created_at,
                                                                    ).toLocaleDateString(
                                                                        currency.locale,
                                                                        {
                                                                            day: '2-digit',
                                                                            month: 'short',
                                                                            year: 'numeric',
                                                                            hour: '2-digit',
                                                                            minute: '2-digit',
                                                                        },
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center justify-between gap-6 border-t border-slate-100 pt-3 sm:justify-end sm:border-t-0 sm:pt-0">
                                                            <div className="text-left sm:text-right">
                                                                <span className="block text-xs font-bold tracking-wider text-slate-400 uppercase">
                                                                    Total
                                                                </span>
                                                                <span className="text-base font-black text-slate-950">
                                                                    {formatPrice(
                                                                        order.total,
                                                                    )}
                                                                </span>
                                                            </div>
                                                            <div className="flex items-center gap-3">
                                                                {order.estado ===
                                                                    'pendiente' && (
                                                                    <Button
                                                                        onClick={(
                                                                            e,
                                                                        ) => {
                                                                            e.stopPropagation();
                                                                            handleCancelOrder(
                                                                                order.id,
                                                                            );
                                                                        }}
                                                                        variant="outline"
                                                                        size="sm"
                                                                        className="h-9 rounded-xl border-rose-200 font-bold text-rose-600 hover:bg-rose-50"
                                                                    >
                                                                        Cancelar
                                                                    </Button>
                                                                )}
                                                                <ChevronDown
                                                                    className={`h-5 w-5 text-slate-400 transition-all ${isExpanded ? 'rotate-180' : ''}`}
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Expanded Details */}
                                                    {isExpanded && (
                                                        <div className="space-y-6 border-t border-slate-100 bg-slate-50/50 p-6">
                                                            {/* Items listing */}
                                                            <div>
                                                                <h4 className="mb-3 text-xs font-black tracking-widest text-slate-400 uppercase">
                                                                    Detalle de
                                                                    Compra
                                                                </h4>
                                                                <div className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200/50 bg-white">
                                                                    {order.items.map(
                                                                        (
                                                                            item,
                                                                        ) => (
                                                                            <div
                                                                                key={
                                                                                    item.id
                                                                                }
                                                                                className="flex items-center justify-between p-4 text-sm"
                                                                            >
                                                                                <div>
                                                                                    <p className="font-black text-slate-900">
                                                                                        {
                                                                                            item.nombre_producto
                                                                                        }
                                                                                    </p>
                                                                                    <p className="mt-0.5 text-xs text-slate-400">
                                                                                        {formatPrice(
                                                                                            item.precio_unitario,
                                                                                        )}{' '}
                                                                                        x{' '}
                                                                                        {
                                                                                            item.cantidad
                                                                                        }{' '}
                                                                                        unidades
                                                                                    </p>
                                                                                </div>
                                                                                <span className="font-bold text-slate-900">
                                                                                    {formatPrice(
                                                                                        item.subtotal,
                                                                                    )}
                                                                                </span>
                                                                            </div>
                                                                        ),
                                                                    )}

                                                                    <div className="space-y-1.5 bg-slate-50/50 p-4 text-sm">
                                                                        <div className="flex justify-between font-semibold text-slate-500">
                                                                            <span>
                                                                                Subtotal
                                                                            </span>
                                                                            <span>
                                                                                {formatPrice(
                                                                                    order.subtotal,
                                                                                )}
                                                                            </span>
                                                                        </div>
                                                                        <div className="flex justify-between font-semibold text-slate-500">
                                                                            <span>
                                                                                IVA
                                                                                (19%)
                                                                            </span>
                                                                            <span>
                                                                                {formatPrice(
                                                                                    order.impuesto,
                                                                                )}
                                                                            </span>
                                                                        </div>
                                                                        <div className="flex justify-between border-t border-slate-200 pt-1.5 text-base font-black text-slate-950">
                                                                            <span>
                                                                                Total
                                                                            </span>
                                                                            <span>
                                                                                {formatPrice(
                                                                                    order.total,
                                                                                )}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {/* Despatch and general details */}
                                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                                <div className="space-y-2 rounded-2xl border border-slate-200/50 bg-white p-4 text-sm">
                                                                    <h4 className="flex items-center gap-1.5 text-xs font-black tracking-wider text-slate-400 uppercase">
                                                                        <Truck className="text-brand-primary h-4 w-4" />{' '}
                                                                        Información
                                                                        de
                                                                        Despacho
                                                                    </h4>
                                                                    <p className="pt-2">
                                                                        <strong className="font-semibold text-slate-500">
                                                                            Contacto:
                                                                        </strong>{' '}
                                                                        {
                                                                            order.nombre_cliente
                                                                        }
                                                                    </p>
                                                                    <p>
                                                                        <strong className="font-semibold text-slate-500">
                                                                            Teléfono:
                                                                        </strong>{' '}
                                                                        {
                                                                            order.telefono_cliente
                                                                        }
                                                                    </p>
                                                                    <p>
                                                                        <strong className="font-semibold text-slate-500">
                                                                            Dirección:
                                                                        </strong>{' '}
                                                                        {
                                                                            order.direccion_cliente
                                                                        }
                                                                    </p>
                                                                </div>
                                                                <div className="space-y-2 rounded-2xl border border-slate-200/50 bg-white p-4 text-sm">
                                                                    <h4 className="flex items-center gap-1.5 text-xs font-black tracking-wider text-slate-400 uppercase">
                                                                        <Clock className="text-brand-primary h-4 w-4" />{' '}
                                                                        Datos
                                                                        del
                                                                        Pedido
                                                                    </h4>
                                                                    <p className="pt-2">
                                                                        <strong className="font-semibold text-slate-500">
                                                                            Método
                                                                            de
                                                                            Pago:
                                                                        </strong>{' '}
                                                                        <span className="text-xs font-bold uppercase">
                                                                            {
                                                                                order.metodo_pago
                                                                            }
                                                                        </span>
                                                                    </p>
                                                                    <p>
                                                                        <strong className="font-semibold text-slate-500">
                                                                            Notas:
                                                                        </strong>{' '}
                                                                        {order.notas ||
                                                                            'Sin comentarios.'}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {activeTab === 'support' && (
                        <div className="space-y-6">
                            {/* Mis Tickets */}
                            <div className="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm">
                                <h3 className="mb-4 flex items-center gap-2 text-lg font-black text-slate-900">
                                    <ShieldAlert className="text-brand-primary h-5 w-5" />
                                    Mis Tickets
                                </h3>
                                {tickets.length === 0 ? (
                                    <div className="space-y-2 py-6 text-center">
                                        <ShieldAlert className="mx-auto h-8 w-8 text-slate-300 opacity-60" />
                                        <p className="text-sm font-semibold text-slate-400">
                                            No has enviado tickets aún
                                        </p>
                                        <p className="text-xs text-slate-300">
                                            Usa el formulario de abajo para
                                            reportar un problema.
                                        </p>
                                    </div>
                                ) : (
                                    <>
                                        {/* Summary bar */}
                                        {(() => {
                                            const counts: Record<
                                                string,
                                                number
                                            > = {};
                                            tickets.forEach((t) => {
                                                counts[t.estado] =
                                                    (counts[t.estado] || 0) + 1;
                                            });
                                            const totalAbiertos =
                                                (counts['abierto'] || 0) +
                                                (counts['en_proceso'] || 0) +
                                                (counts['pendiente'] || 0);
                                            const totalResueltos =
                                                (counts['resuelto'] || 0) +
                                                (counts['cerrado'] || 0);
                                            return (
                                                <div className="mb-4 flex items-center gap-4 text-xs">
                                                    <span className="font-semibold text-slate-500">
                                                        {tickets.length} total
                                                    </span>
                                                    <span className="rounded-full bg-blue-50 px-2.5 py-1 font-bold text-blue-600">
                                                        {totalAbiertos} abiertos
                                                    </span>
                                                    <span className="rounded-full bg-green-50 px-2.5 py-1 font-bold text-green-600">
                                                        {totalResueltos}{' '}
                                                        resueltos
                                                    </span>
                                                </div>
                                            );
                                        })()}
                                        <div className="space-y-2">
                                            {tickets
                                                .slice(0, 5)
                                                .map((ticket) => {
                                                    const estadoStyles: Record<
                                                        string,
                                                        string
                                                    > = {
                                                        abierto:
                                                            'bg-blue-100 text-blue-700 border-blue-300',
                                                        en_proceso:
                                                            'bg-amber-100 text-amber-700 border-amber-300',
                                                        pendiente:
                                                            'bg-purple-100 text-purple-700 border-purple-300',
                                                        resuelto:
                                                            'bg-green-100 text-green-700 border-green-300',
                                                        cerrado:
                                                            'bg-gray-100 text-gray-600 border-gray-300',
                                                    };
                                                    const prioridadStyles: Record<
                                                        string,
                                                        string
                                                    > = {
                                                        baja: 'text-slate-500 bg-slate-100',
                                                        media: 'text-amber-600 bg-amber-50',
                                                        alta: 'text-rose-600 bg-rose-50',
                                                        urgente:
                                                            'text-red-600 bg-red-100',
                                                    };
                                                    return (
                                                        <div
                                                            key={ticket.id}
                                                            className="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 transition-all hover:shadow-sm"
                                                        >
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div className="min-w-0 flex-1">
                                                                    <h4 className="text-sm font-bold text-slate-900">
                                                                        {
                                                                            ticket.titulo
                                                                        }
                                                                    </h4>
                                                                    {ticket.descripcion && (
                                                                        <p className="mt-1 line-clamp-2 text-xs text-slate-500">
                                                                            {
                                                                                ticket.descripcion
                                                                            }
                                                                        </p>
                                                                    )}
                                                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                                                        <span
                                                                            className={`rounded-full border px-2 py-0.5 text-[10px] font-bold ${estadoStyles[ticket.estado] || 'bg-slate-100 text-slate-600'}`}
                                                                        >
                                                                            {ticket.estado ===
                                                                            'en_proceso'
                                                                                ? 'En Proceso'
                                                                                : ticket.estado
                                                                                      .charAt(
                                                                                          0,
                                                                                      )
                                                                                      .toUpperCase() +
                                                                                  ticket.estado.slice(
                                                                                      1,
                                                                                  )}
                                                                        </span>
                                                                        <span
                                                                            className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${prioridadStyles[ticket.prioridad] || 'bg-slate-100 text-slate-600'}`}
                                                                        >
                                                                            {ticket.prioridad
                                                                                .charAt(
                                                                                    0,
                                                                                )
                                                                                .toUpperCase() +
                                                                                ticket.prioridad.slice(
                                                                                    1,
                                                                                )}
                                                                        </span>
                                                                        <span className="text-[10px] text-slate-400">
                                                                            {new Date(
                                                                                ticket.created_at,
                                                                            ).toLocaleDateString(
                                                                                currency.locale,
                                                                                {
                                                                                    day: '2-digit',
                                                                                    month: 'short',
                                                                                    year: 'numeric',
                                                                                },
                                                                            )}
                                                                        </span>
                                                                        {ticket.asignado_a && (
                                                                            <span className="text-[10px] text-slate-400">
                                                                                ·{' '}
                                                                                {
                                                                                    ticket.asignado_a
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                        </div>
                                    </>
                                )}
                            </div>
                            <div className="rounded-3xl border border-slate-200/70 bg-white p-6 shadow-sm">
                                <h3 className="mb-1 flex items-center gap-2 text-lg font-black text-slate-900">
                                    <LifeBuoy className="text-brand-primary h-5 w-5" />
                                    Enviar Ticket de Soporte
                                </h3>
                                <p className="mb-4 text-xs text-slate-500">
                                    Reporta un problema o solicita ayuda al
                                    equipo de {business.name}
                                </p>
                                <form
                                    onSubmit={handleSupportTicket}
                                    className="space-y-3"
                                >
                                    <div>
                                        <label className="mb-1 block text-[10px] font-bold tracking-wider text-slate-600 uppercase">
                                            Asunto *
                                        </label>
                                        <Input
                                            value={ticketData.titulo}
                                            onChange={(e) =>
                                                setTicketData((prev) => ({
                                                    ...prev,
                                                    titulo: e.target.value,
                                                }))
                                            }
                                            placeholder="Ej: Problema con mi pedido"
                                            className="h-10 rounded-xl border-slate-200 bg-slate-50 text-sm"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-[10px] font-bold tracking-wider text-slate-600 uppercase">
                                            Descripción
                                        </label>
                                        <Textarea
                                            value={ticketData.descripcion}
                                            onChange={(e) =>
                                                setTicketData((prev) => ({
                                                    ...prev,
                                                    descripcion: e.target.value,
                                                }))
                                            }
                                            placeholder="Describe el detalle de tu consulta..."
                                            className="min-h-[80px] rounded-xl border-slate-200 bg-slate-50 text-sm"
                                            rows={3}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-[10px] font-bold tracking-wider text-slate-600 uppercase">
                                            Prioridad
                                        </label>
                                        <select
                                            value={ticketData.prioridad}
                                            onChange={(e) =>
                                                setTicketData((prev) => ({
                                                    ...prev,
                                                    prioridad: e.target.value,
                                                }))
                                            }
                                            className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm"
                                        >
                                            <option value="baja">Baja</option>
                                            <option value="media">Media</option>
                                            <option value="alta">Alta</option>
                                            <option value="urgente">
                                                Urgente
                                            </option>
                                        </select>
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={ticketLoading}
                                        className="btn-brand-primary h-11 w-full rounded-xl text-sm font-black shadow-lg"
                                    >
                                        <LifeBuoy className="mr-2 h-4 w-4" />
                                        {ticketLoading
                                            ? 'Enviando...'
                                            : 'Enviar Ticket'}
                                    </Button>
                                </form>
                            </div>
                        </div>
                    )}
                </div>

                {/* Sidebar */}
                <div className="space-y-6 lg:col-span-4">
                    {/* Contact Info card */}
                    <Card className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <CardHeader className="flex flex-row items-center gap-3 border-b border-slate-100 bg-slate-50/50">
                            <Phone className="text-brand-primary h-5 w-5" />
                            <CardTitle className="text-base font-black">
                                Contacto Comercial
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3.5 p-5 text-sm font-semibold">
                            {business.phone && (
                                <a
                                    href={`https://wa.me/${business.phone.replace(/[^0-9]/g, '')}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-2.5 text-slate-700 transition-colors hover:text-emerald-600"
                                >
                                    <MessageCircle className="h-4 w-4 shrink-0 text-emerald-500" />
                                    <span>{business.phone}</span>
                                </a>
                            )}
                            <p className="flex items-center gap-2.5 text-slate-700">
                                <Mail className="h-4 w-4 shrink-0 text-slate-400" />
                                <span className="truncate">
                                    {business.email}
                                </span>
                            </p>
                            {store && (
                                <div className="border-t border-slate-100 pt-3">
                                    <a
                                        href={`/tienda/${store.slug}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-brand-primary flex items-center gap-1 text-xs font-bold hover:underline"
                                    >
                                        Visitar tienda pública online &rarr;
                                    </a>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Cart sidebar — desktop only */}
                    <div className="hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:block">
                        <div className="flex items-center justify-between bg-slate-900 p-4 text-white">
                            <h3 className="flex items-center gap-2 text-sm font-black">
                                <ShoppingCart className="h-4 w-4 text-[var(--brand-secondary)]" />{' '}
                                Carrito
                            </h3>
                            <Badge className="bg-[var(--brand-secondary)] px-2 py-0.5 text-xs font-bold text-slate-950">
                                {cart.reduce(
                                    (sum, item) => sum + item.quantity,
                                    0,
                                )}{' '}
                                items
                            </Badge>
                        </div>

                        {cart.length === 0 ? (
                            <div className="space-y-3 p-8 text-center">
                                <ShoppingCart className="mx-auto h-8 w-8 text-slate-300 opacity-70" />
                                <p className="text-sm font-bold text-slate-400">
                                    Carrito vacío
                                </p>
                                <p className="text-xs text-slate-400">
                                    Añada productos del catálogo para comenzar.
                                </p>
                            </div>
                        ) : (
                            <div className="flex flex-col">
                                <div className="max-h-80 space-y-3 overflow-y-auto p-4">
                                    {cart.map((item) => (
                                        <div
                                            key={item.product.id}
                                            className="flex items-center justify-between gap-2 text-xs"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-black text-slate-900">
                                                    {item.product.nombre}
                                                </p>
                                                <p className="mt-0.5 text-[10px] font-semibold text-slate-400">
                                                    {formatPrice(
                                                        item.product
                                                            .precio_venta,
                                                    )}{' '}
                                                    c/u
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-1">
                                                <div className="flex items-center rounded-md border border-slate-200 bg-slate-100 p-0.5">
                                                    <button
                                                        onClick={() =>
                                                            updateQuantity(
                                                                item.product.id,
                                                                -1,
                                                            )
                                                        }
                                                        className="flex h-5 w-5 items-center justify-center rounded text-slate-600 transition-colors hover:bg-white"
                                                    >
                                                        <Minus className="h-2.5 w-2.5" />
                                                    </button>
                                                    <span className="min-w-[16px] px-1.5 text-center text-[10px] font-bold text-slate-800">
                                                        {item.quantity}
                                                    </span>
                                                    <button
                                                        onClick={() =>
                                                            updateQuantity(
                                                                item.product.id,
                                                                1,
                                                            )
                                                        }
                                                        className="flex h-5 w-5 items-center justify-center rounded text-slate-600 transition-colors hover:bg-white"
                                                    >
                                                        <Plus className="h-2.5 w-2.5" />
                                                    </button>
                                                </div>
                                                <button
                                                    onClick={() =>
                                                        removeFromCart(
                                                            item.product.id,
                                                        )
                                                    }
                                                    className="rounded-lg p-1 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                                                >
                                                    <Trash2 className="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <div className="space-y-3 border-t border-slate-200 p-4">
                                    <div className="space-y-1 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                        <div className="flex justify-between">
                                            <span>Subtotal</span>
                                            <span className="text-xs font-black text-slate-800">
                                                {formatPrice(cartSubtotal)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>IVA (19%)</span>
                                            <span className="text-xs font-black text-slate-800">
                                                {formatPrice(cartTax)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between border-t border-slate-200 pt-1.5 text-xs text-slate-900">
                                            <span>Total</span>
                                            <span className="text-brand-primary text-sm font-black">
                                                {formatPrice(cartTotal)}
                                            </span>
                                        </div>
                                    </div>
                                    <form
                                        onSubmit={handleCheckout}
                                        className="space-y-2"
                                    >
                                        <Input
                                            type="text"
                                            required
                                            placeholder="Nombre de contacto"
                                            value={data.nombre_cliente}
                                            onChange={(e) =>
                                                setData(
                                                    'nombre_cliente',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-8 rounded-lg bg-slate-50 text-xs"
                                        />
                                        <Input
                                            type="text"
                                            required
                                            placeholder="Teléfono"
                                            value={data.telefono_cliente}
                                            onChange={(e) =>
                                                setData(
                                                    'telefono_cliente',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-8 rounded-lg bg-slate-50 text-xs"
                                        />
                                        <Input
                                            type="text"
                                            required
                                            placeholder="Dirección de despacho"
                                            value={data.direccion_cliente}
                                            onChange={(e) =>
                                                setData(
                                                    'direccion_cliente',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-8 rounded-lg bg-slate-50 text-xs"
                                        />
                                        <Textarea
                                            placeholder="Notas..."
                                            value={data.notas}
                                            onChange={(e) =>
                                                setData('notas', e.target.value)
                                            }
                                            className="min-h-[48px] rounded-lg bg-slate-50 text-xs"
                                        />
                                        <select
                                            value={data.metodo_pago}
                                            onChange={(e) =>
                                                setData(
                                                    'metodo_pago',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs"
                                        >
                                            <option value="efectivo">
                                                Efectivo contra entrega
                                            </option>
                                            <option value="transferencia">
                                                Transferencia Bancaria
                                            </option>
                                            <option value="tarjeta">
                                                Tarjeta de Crédito/Débito
                                            </option>
                                        </select>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="btn-brand-primary flex h-10 w-full items-center justify-center gap-2 rounded-xl text-xs font-black shadow-lg"
                                        >
                                            <Check className="h-3.5 w-3.5" />
                                            {processing
                                                ? 'Procesando...'
                                                : 'Confirmar Pedido'}
                                        </Button>
                                    </form>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </main>

            {/* Booking Modal */}
            {bookingProduct && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    onClick={() => {
                        setBookingProduct(null);
                        setSelectedDay(null);
                        setSelectedTime('');
                        setSelectedProvider(null);
                        setBookingMonth(new Date());
                    }}
                >
                    <div
                        className="w-full max-w-sm space-y-4 rounded-3xl bg-white p-5 shadow-2xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {/* Header */}
                        <div className="flex items-start justify-between">
                            <div>
                                <h3 className="text-base font-black text-slate-900">
                                    Agendar Cita
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    {bookingProduct.nombre}
                                </p>
                            </div>
                            <button
                                onClick={() => {
                                    setBookingProduct(null);
                                    setSelectedDay(null);
                                    setSelectedTime('');
                                    setSelectedProvider(null);
                                    setBookingMonth(new Date());
                                }}
                                className="rounded-lg p-1 transition-colors hover:bg-slate-100"
                            >
                                <XCircle className="h-4 w-4 text-slate-400" />
                            </button>
                        </div>

                        {/* Info bar */}
                        {bookingProduct.duracion && (
                            <div className="flex items-center gap-1.5 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                <Clock className="text-brand-primary h-3.5 w-3.5" />
                                <strong>{bookingProduct.duracion} min</strong>
                                <span className="mx-1 text-slate-300">|</span>
                                <DollarSign className="text-brand-primary h-3.5 w-3.5" />
                                <strong>
                                    $
                                    {Number(
                                        bookingProduct.precio_venta,
                                    ).toLocaleString()}
                                </strong>
                            </div>
                        )}

                        {/* Calendar */}
                        <div className="select-none">
                            {/* Month/Year header */}
                            <div className="mb-3 flex items-center justify-between">
                                <button
                                    onClick={() =>
                                        setBookingMonth(
                                            subMonths(bookingMonth, 1),
                                        )
                                    }
                                    className="rounded-lg p-1 transition-colors hover:bg-slate-100"
                                >
                                    <ChevronLeft className="h-4 w-4 text-slate-600" />
                                </button>
                                <span className="text-sm font-bold text-slate-800">
                                    {format(bookingMonth, 'MMMM yyyy', {
                                        locale: es,
                                    })}
                                </span>
                                <button
                                    onClick={() =>
                                        setBookingMonth(
                                            addMonths(bookingMonth, 1),
                                        )
                                    }
                                    className="rounded-lg p-1 transition-colors hover:bg-slate-100"
                                >
                                    <ChevronRight className="h-4 w-4 text-slate-600" />
                                </button>
                            </div>
                            {/* Day headers */}
                            <div className="mb-1 grid grid-cols-7">
                                {['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'].map(
                                    (d) => (
                                        <div
                                            key={d}
                                            className="py-1 text-center text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >
                                            {d}
                                        </div>
                                    ),
                                )}
                            </div>
                            {/* Day cells */}
                            <div className="grid grid-cols-7 gap-0.5">
                                {(() => {
                                    const monthStart =
                                        startOfMonth(bookingMonth);
                                    const monthEnd = endOfMonth(bookingMonth);
                                    const allDays = eachDayOfInterval({
                                        start: monthStart,
                                        end: monthEnd,
                                    });
                                    const startPad = getDay(monthStart);
                                    const today = startOfDay(new Date());
                                    const cells: React.ReactNode[] = [];
                                    for (let i = 0; i < startPad; i++) {
                                        cells.push(<div key={`pad-${i}`} />);
                                    }
                                    allDays.forEach((day) => {
                                        const isPast = isBefore(day, today);
                                        const isSelected =
                                            selectedDay &&
                                            isSameDay(day, selectedDay);
                                        const isCurrent = isToday(day);
                                        cells.push(
                                            <button
                                                key={day.toISOString()}
                                                disabled={isPast}
                                                onClick={() => {
                                                    setSelectedDay(day);
                                                    setSelectedTime('');
                                                    setSelectedProvider(null);
                                                }}
                                                className={`rounded-lg py-1.5 text-center text-xs font-semibold transition-all ${isPast ? 'cursor-not-allowed text-slate-300' : 'hover:bg-brand-primary/10 hover:text-brand-primary'} ${isSelected ? 'bg-brand-primary shadow-brand-primary/30 text-white shadow-sm' : ''} ${isCurrent && !isSelected ? 'ring-brand-primary/30 text-brand-primary ring-1' : ''} ${!isPast && !isSelected && !isCurrent ? 'text-slate-700' : ''} `}
                                            >
                                                {format(day, 'd')}
                                            </button>,
                                        );
                                    });
                                    return cells;
                                })()}
                            </div>
                        </div>

                        {/* Time picker */}
                        {selectedDay && (
                            <div>
                                <p className="mb-2 text-xs font-bold text-slate-700">
                                    Horario disponible
                                </p>
                                <div className="grid grid-cols-4 gap-1.5">
                                    {Array.from(
                                        { length: 10 },
                                        (_, i) => i + 8,
                                    ).flatMap((h) =>
                                        [0, 30].map((m) => {
                                            const time = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
                                            const slotStart = new Date(
                                                `${format(selectedDay, 'yyyy-MM-dd')}T${time}:00`,
                                            );
                                            const slotEnd = new Date(
                                                slotStart.getTime() +
                                                    (bookingProduct.duracion ||
                                                        60) *
                                                        60000,
                                            );
                                            const isPastSlot = isBefore(
                                                slotStart,
                                                new Date(),
                                            );
                                            const bookedProviders = appointments
                                                .filter((app) => {
                                                    const appStart = new Date(
                                                        app.start_time,
                                                    );
                                                    const appEnd = new Date(
                                                        app.end_time,
                                                    );
                                                    return (
                                                        slotStart < appEnd &&
                                                        slotEnd > appStart
                                                    );
                                                })
                                                .map((app) => app.provider_id);

                                            const productProviders =
                                                bookingProduct.providers?.map(
                                                    (p) => p.id,
                                                ) || [];
                                            const hasProviders =
                                                productProviders.length > 0;

                                            const isBooked = selectedProvider
                                                ? bookedProviders.includes(
                                                      selectedProvider,
                                                  )
                                                : hasProviders
                                                  ? productProviders.every(
                                                        (pid) =>
                                                            bookedProviders.includes(
                                                                pid,
                                                            ),
                                                    )
                                                  : bookedProviders.length > 0;
                                            const isDisabled =
                                                isPastSlot || isBooked;
                                            const isSelected =
                                                selectedTime === time;
                                            return (
                                                <button
                                                    key={time}
                                                    disabled={isDisabled}
                                                    onClick={() => {
                                                        setSelectedTime(time);
                                                        setSelectedProvider(
                                                            null,
                                                        );
                                                    }}
                                                    className={`rounded-lg py-1.5 text-xs font-semibold transition-all ${isDisabled ? 'cursor-not-allowed bg-slate-50 text-slate-300 line-through' : 'hover:bg-brand-primary/10 hover:text-brand-primary bg-slate-50'} ${isSelected ? 'bg-brand-primary shadow-brand-primary/30 text-white shadow-sm ring-0' : 'text-slate-600'} `}
                                                    title={
                                                        isBooked
                                                            ? 'Horario no disponible'
                                                            : hasProviders &&
                                                                !selectedProvider
                                                              ? `${productProviders.length - bookedProviders.length} profesional(es) disponible(s)`
                                                              : time
                                                    }
                                                >
                                                    {time}
                                                </button>
                                            );
                                        }),
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Provider selector */}
                        {bookingProduct.providers &&
                            bookingProduct.providers.length > 0 &&
                            selectedDay &&
                            selectedTime && (
                                <div>
                                    <p className="mb-2 text-xs font-bold text-slate-700">
                                        Elige un profesional
                                    </p>
                                    <div className="grid grid-cols-2 gap-2">
                                        {bookingProduct.providers.map(
                                            (prov) => {
                                                const provSlotStart = new Date(
                                                    `${format(selectedDay, 'yyyy-MM-dd')}T${selectedTime}:00`,
                                                );
                                                const provSlotEnd = new Date(
                                                    provSlotStart.getTime() +
                                                        (bookingProduct.duracion ||
                                                            60) *
                                                            60000,
                                                );
                                                const isProviderBooked =
                                                    appointments.some(
                                                        (app) =>
                                                            app.provider_id ===
                                                                prov.id &&
                                                            provSlotStart <
                                                                new Date(
                                                                    app.end_time,
                                                                ) &&
                                                            provSlotEnd >
                                                                new Date(
                                                                    app.start_time,
                                                                ),
                                                    );
                                                return (
                                                    <button
                                                        key={prov.id}
                                                        disabled={
                                                            isProviderBooked
                                                        }
                                                        onClick={() =>
                                                            setSelectedProvider(
                                                                prov.id,
                                                            )
                                                        }
                                                        className={`flex items-center gap-2.5 rounded-xl p-3 text-left font-semibold transition-all ${
                                                            selectedProvider ===
                                                            prov.id
                                                                ? 'ring-brand-primary bg-gray-900 text-white shadow-lg ring-2'
                                                                : isProviderBooked
                                                                  ? 'cursor-not-allowed bg-gray-100 text-gray-300 line-through'
                                                                  : 'bg-gray-800 text-white hover:bg-gray-700 hover:shadow-md'
                                                        } `}
                                                    >
                                                        <img
                                                            src={prov.photo}
                                                            alt={prov.name}
                                                            className="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-white/20"
                                                        />
                                                        <div className="min-w-0">
                                                            <span className="block truncate text-sm leading-tight">
                                                                {prov.name}
                                                            </span>
                                                            {isProviderBooked ? (
                                                                <span className="block text-[10px] font-normal text-gray-400">
                                                                    No
                                                                    disponible
                                                                </span>
                                                            ) : selectedProvider ===
                                                              prov.id ? (
                                                                <span className="text-brand-primary block text-[10px] font-normal">
                                                                    Seleccionado
                                                                </span>
                                                            ) : (
                                                                <span className="block text-[10px] font-normal text-gray-400">
                                                                    Disponible
                                                                </span>
                                                            )}
                                                        </div>
                                                    </button>
                                                );
                                            },
                                        )}
                                    </div>
                                </div>
                            )}

                        {/* Actions */}
                        <div className="flex gap-2 pt-1">
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-10 flex-1 text-sm"
                                onClick={() => {
                                    setBookingProduct(null);
                                    setSelectedDay(null);
                                    setSelectedTime('');
                                    setSelectedProvider(null);
                                    setBookingMonth(new Date());
                                }}
                            >
                                Cancelar
                            </Button>
                            <Button
                                size="sm"
                                className="btn-brand-primary h-10 flex-1 text-sm font-bold"
                                disabled={
                                    !selectedDay ||
                                    !selectedTime ||
                                    (bookingProduct.providers &&
                                        bookingProduct.providers.length > 0 &&
                                        !selectedProvider)
                                }
                                onClick={() => {
                                    if (!selectedDay || !selectedTime) return;
                                    const dateTimeStr = `${format(selectedDay, 'yyyy-MM-dd')}T${selectedTime}:00`;
                                    router.post(
                                        cliente.citas.store().url,
                                        {
                                            producto_id: bookingProduct.id,
                                            start_time: dateTimeStr,
                                            provider_id: selectedProvider,
                                        },
                                        {
                                            onSuccess: () => {
                                                setBookingSuccess({
                                                    servicio:
                                                        bookingProduct.nombre,
                                                    fecha: format(
                                                        selectedDay,
                                                        "EEEE d 'de' MMMM",
                                                        { locale: es },
                                                    ),
                                                    hora: selectedTime,
                                                });
                                                setBookingProduct(null);
                                                setSelectedDay(null);
                                                setSelectedTime('');
                                                setSelectedProvider(null);
                                                setBookingMonth(new Date());
                                            },
                                            onError: (errs) => {
                                                toast.error(
                                                    errs.error ||
                                                        'Error al agendar la cita.',
                                                );
                                            },
                                        },
                                    );
                                }}
                            >
                                <Calendar className="h-3.5 w-3.5" />
                                Confirmar Cita
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Booking Success Modal */}
            {bookingSuccess && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm space-y-4 rounded-3xl bg-white p-6 text-center shadow-2xl">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                            <CheckCircle2 className="h-7 w-7 text-emerald-600" />
                        </div>
                        <div>
                            <h3 className="text-lg font-black text-slate-900">
                                ¡Cita agendada!
                            </h3>
                            <p className="mt-1 text-sm text-slate-500">
                                Tu solicitud fue enviada correctamente.
                            </p>
                        </div>
                        <div className="space-y-2 rounded-2xl bg-slate-50 p-4 text-left text-sm">
                            <div className="flex justify-between">
                                <span className="font-semibold text-slate-400">
                                    Servicio
                                </span>
                                <span className="font-bold text-slate-900">
                                    {bookingSuccess.servicio}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="font-semibold text-slate-400">
                                    Fecha
                                </span>
                                <span className="font-bold text-slate-900">
                                    {bookingSuccess.fecha}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="font-semibold text-slate-400">
                                    Hora
                                </span>
                                <span className="font-bold text-slate-900">
                                    {bookingSuccess.hora} hrs
                                </span>
                            </div>
                        </div>
                        <p className="text-xs text-slate-400">
                            El vendedor confirmará tu cita y te contactará si es
                            necesario.
                        </p>
                        <Button
                            className="btn-brand-primary h-11 w-full font-bold"
                            onClick={() => setBookingSuccess(null)}
                        >
                            Entendido
                        </Button>
                    </div>
                </div>
            )}

            {/* Mobile cart button */}
            {cart.length > 0 && (
                <button
                    onClick={() => setIsCartOpen(true)}
                    className="fixed right-0 bottom-0 left-0 z-40 flex items-center justify-between bg-slate-900 px-5 py-3.5 text-white shadow-2xl shadow-slate-900/30 lg:hidden"
                >
                    <div className="flex items-center gap-2">
                        <ShoppingCart className="h-5 w-5 text-[var(--brand-secondary)]" />
                        <span className="text-sm font-black">
                            {cart.reduce((sum, item) => sum + item.quantity, 0)}{' '}
                            items
                        </span>
                    </div>
                    <span className="text-sm font-black">
                        {formatPrice(cartTotal)}
                    </span>
                </button>
            )}

            {/* Mobile cart overlay */}
            {isCartOpen && (
                <div className="fixed inset-0 z-50 flex flex-col bg-white lg:hidden">
                    <div className="sticky top-0 z-10 flex items-center justify-between bg-slate-900 p-4 text-white">
                        <h2 className="flex items-center gap-2 text-sm font-black">
                            <ShoppingCart className="h-4 w-4 text-[var(--brand-secondary)]" />
                            Carrito de Compras
                            <Badge className="ml-2 bg-[var(--brand-secondary)] px-2 py-0.5 text-xs font-bold text-slate-950">
                                {cart.reduce(
                                    (sum, item) => sum + item.quantity,
                                    0,
                                )}{' '}
                                items
                            </Badge>
                        </h2>
                        <button
                            onClick={() => setIsCartOpen(false)}
                            className="rounded-lg p-1.5 transition-colors hover:bg-white/10"
                        >
                            <XCircle className="h-5 w-5" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-3 overflow-y-auto p-4">
                        {cart.map((item) => (
                            <div
                                key={item.product.id}
                                className="flex items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3 text-sm"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-black text-slate-900">
                                        {item.product.nombre}
                                    </p>
                                    <p className="mt-0.5 text-xs font-semibold text-slate-400">
                                        {formatPrice(item.product.precio_venta)}{' '}
                                        c/u
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <div className="flex items-center rounded-lg border border-slate-200 bg-white p-0.5">
                                        <button
                                            onClick={() =>
                                                updateQuantity(
                                                    item.product.id,
                                                    -1,
                                                )
                                            }
                                            className="flex h-7 w-7 items-center justify-center rounded text-slate-600 transition-colors hover:bg-slate-100"
                                        >
                                            <Minus className="h-3.5 w-3.5" />
                                        </button>
                                        <span className="min-w-[24px] px-3 text-center text-sm font-bold text-slate-800">
                                            {item.quantity}
                                        </span>
                                        <button
                                            onClick={() =>
                                                updateQuantity(
                                                    item.product.id,
                                                    1,
                                                )
                                            }
                                            className="flex h-7 w-7 items-center justify-center rounded text-slate-600 transition-colors hover:bg-slate-100"
                                        >
                                            <Plus className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <button
                                        onClick={() =>
                                            removeFromCart(item.product.id)
                                        }
                                        className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="sticky bottom-0 space-y-3 border-t border-slate-200 bg-white p-4">
                        <div className="space-y-1 text-xs font-bold tracking-wider text-slate-500 uppercase">
                            <div className="flex justify-between">
                                <span>Subtotal</span>
                                <span className="font-black text-slate-800">
                                    {formatPrice(cartSubtotal)}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>IVA (19%)</span>
                                <span className="font-black text-slate-800">
                                    {formatPrice(cartTax)}
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-slate-200 pt-2 text-sm text-slate-900">
                                <span>Total</span>
                                <span className="text-brand-primary text-base font-black">
                                    {formatPrice(cartTotal)}
                                </span>
                            </div>
                        </div>
                        <form onSubmit={handleCheckout} className="space-y-2">
                            <Input
                                type="text"
                                required
                                placeholder="Nombre de contacto"
                                value={data.nombre_cliente}
                                onChange={(e) =>
                                    setData('nombre_cliente', e.target.value)
                                }
                                className="h-9 rounded-lg border-slate-200 bg-white text-xs"
                            />
                            <Input
                                type="text"
                                required
                                placeholder="Teléfono"
                                value={data.telefono_cliente}
                                onChange={(e) =>
                                    setData('telefono_cliente', e.target.value)
                                }
                                className="h-9 rounded-lg border-slate-200 bg-white text-xs"
                            />
                            <Input
                                type="text"
                                required
                                placeholder="Dirección de despacho"
                                value={data.direccion_cliente}
                                onChange={(e) =>
                                    setData('direccion_cliente', e.target.value)
                                }
                                className="h-9 rounded-lg border-slate-200 bg-white text-xs"
                            />
                            <Textarea
                                placeholder="Notas..."
                                value={data.notas}
                                onChange={(e) =>
                                    setData('notas', e.target.value)
                                }
                                className="min-h-[50px] rounded-lg border-slate-200 bg-white text-xs"
                            />
                            <select
                                value={data.metodo_pago}
                                onChange={(e) =>
                                    setData('metodo_pago', e.target.value)
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs"
                            >
                                <option value="efectivo">
                                    Efectivo contra entrega
                                </option>
                                <option value="transferencia">
                                    Transferencia Bancaria
                                </option>
                                <option value="tarjeta">
                                    Tarjeta de Crédito/Débito
                                </option>
                            </select>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="btn-brand-primary flex h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-black shadow-lg"
                            >
                                <Check className="h-4 w-4" />
                                {processing
                                    ? 'Procesando...'
                                    : 'Confirmar Pedido'}
                            </Button>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

import { Head, useForm, usePage, Link, router } from '@inertiajs/react';
import { format, addMonths, subMonths, startOfMonth, endOfMonth, eachDayOfInterval, isSameDay, isToday, isBefore, startOfDay, getDay } from 'date-fns';
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
    ChevronRight
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
    estado: 'pendiente' | 'confirmado' | 'preparando' | 'enviado' | 'entregado' | 'cancelado';
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
    store
}: DashboardProps) {
    const { auth } = usePage().props as any;
    const { code: countryCode, currency } = useCountry();
    const currentUser = auth.user;

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState<number | 'all'>(
        filters.categoria_id ? parseInt(filters.categoria_id) : 'all'
    );
    const cartKey = `cliente_cart_${business.owner_id}`;
    const [cart, setCart] = useState<CartItem[]>(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem(cartKey);
            if (saved) {
                try { return JSON.parse(saved); } catch { /* ignore */ }
            }
        }
        return [];
    });
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [activeTab, setActiveTab] = useState<'catalog' | 'orders' | 'support'>('catalog');
    const [expandedOrder, setExpandedOrder] = useState<number | null>(null);
    const [bookingProduct, setBookingProduct] = useState<Product | null>(null);
    const [bookingMonth, setBookingMonth] = useState(new Date());
    const [selectedDay, setSelectedDay] = useState<Date | null>(null);
    const [selectedTime, setSelectedTime] = useState<string>('');
    const [selectedProvider, setSelectedProvider] = useState<number | null>(null);
    const [bookingSuccess, setBookingSuccess] = useState<{ servicio: string; fecha: string; hora: string } | null>(null);
    const [ticketData, setTicketData] = useState({ titulo: '', descripcion: '', prioridad: 'media' });
    const [ticketLoading, setTicketLoading] = useState(false);

    // Initialize checkout form with client defaults
    const { data, setData, processing, reset } = useForm({
        items: [] as { producto_id: number; cantidad: number }[],
        nombre_cliente: currentUser.name || '',
        telefono_cliente: currentUser.telefono || '',
        direccion_cliente: currentUser.direccion || '',
        metodo_pago: 'efectivo',
        notas: ''
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
                categoria_id: catId !== 'all' ? catId : undefined
            },
            {
                preserveState: true,
                preserveScroll: true
            }
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
        const existingItem = cart.find(item => item.product.id === product.id);

        if (existingItem) {
            setCart(
                cart.map(item =>
                    item.product.id === product.id
                        ? { ...item, quantity: item.quantity + 1 }
                        : item
                )
            );
        } else {
            setCart([...cart, { product, quantity: 1 }]);
        }
        toast.success(`${product.nombre} añadido al carrito.`);
    };

    const updateQuantity = (productId: number, delta: number) => {
        const item = cart.find(i => i.product.id === productId);
        if (!item) return;

        const newQty = item.quantity + delta;
        if (newQty <= 0) {
            setCart(cart.filter(i => i.product.id !== productId));
            toast.info(`${item.product.nombre} quitado del carrito.`);
            return;
        }

        setCart(
            cart.map(i =>
                i.product.id === productId ? { ...i, quantity: newQty } : i
            )
        );
    };

    const handleQuantityInput = (productId: number, value: string) => {
        const parsed = parseInt(value, 10);
        if (isNaN(parsed) || parsed <= 0) {
            setCart(cart.filter(i => i.product.id !== productId));
            return;
        }
        setCart(
            cart.map(i =>
                i.product.id === productId ? { ...i, quantity: parsed } : i
            )
        );
    };

    const removeFromCart = (productId: number) => {
        setCart(cart.filter(i => i.product.id !== productId));
        toast.info('Producto quitado del carrito.');
    };

    // Calculations
    const cartSubtotal = cart.reduce((sum, item) => sum + item.product.precio_venta * item.quantity, 0);
    const cartTax = cartSubtotal * 0.19;
    const cartTotal = cartSubtotal + cartTax;

    // Checkout submission
    const handleCheckout = (e: React.FormEvent) => {
        e.preventDefault();
        if (cart.length === 0) {
            toast.error('Tu carrito de compras está vacío.');
            return;
        }

        const itemsPayload = cart.map(item => ({
            producto_id: item.product.id,
            cantidad: item.quantity
        }));

        router.post(
            cliente.pedidos.store().url,
            {
                ...data,
                items: itemsPayload
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
                        toast.error('Por favor, revise los campos del despacho.');
                    }
                }
            }
        );
    };

    // Cancellation
    const handleCancelOrder = (orderId: number) => {
        if (confirm('¿Está seguro de que desea cancelar este pedido? Se repondrá el inventario.')) {
            router.post(
                cliente.pedidos.cancelar(orderId).url,
                {},
                {
                    onSuccess: () => {
                        toast.success('Pedido cancelado con éxito.');
                    },
                    onError: (errs) => {
                        toast.error(errs.error || 'No se pudo cancelar el pedido.');
                    }
                }
            );
        }
    };

    // Helper formatting
    const formatPrice = (value: number) => {
        return '$' + new Intl.NumberFormat(currency.locale).format(Math.round(value));
    };

    const getStatusBadge = (status: Order['estado']) => {
        const styles: Record<Order['estado'], string> = {
            pendiente: 'bg-amber-500/20 text-amber-600 border border-amber-500/30',
            confirmado: 'bg-blue-500/20 text-blue-600 border border-blue-500/30',
            preparando: 'bg-purple-500/20 text-purple-600 border border-purple-500/30',
            enviado: 'bg-indigo-500/20 text-indigo-600 border border-indigo-500/30',
            entregado: 'bg-emerald-500/20 text-emerald-600 border border-emerald-500/30',
            cancelado: 'bg-rose-500/20 text-rose-600 border border-rose-500/30'
        };

        const labels: Record<Order['estado'], string> = {
            pendiente: 'Pendiente',
            confirmado: 'Confirmado',
            preparando: 'Preparando',
            enviado: 'Despachado',
            entregado: 'Entregado',
            cancelado: 'Cancelado'
        };

        return <Badge className={`${styles[status]} font-bold px-2 py-0.5 rounded-full`}>{labels[status]}</Badge>;
    };

    const handleSupportTicket = async (e: React.FormEvent) => {
        e.preventDefault();
        setTicketLoading(true);
        router.post(cliente.tickets.store().url, {
            titulo: ticketData.titulo,
            descripcion: ticketData.descripcion,
            prioridad: ticketData.prioridad,
        }, {
            onSuccess: () => {
                setTicketData({ titulo: '', descripcion: '', prioridad: 'media' });
                setTicketLoading(false);
                setActiveTab('orders');
            },
            onError: () => {
                setTicketLoading(false);
            },
        });
    };

    return (
        <div
            className="min-h-screen bg-slate-50 text-slate-900 pb-12"
            style={{ '--brand-primary': primaryColor, '--brand-secondary': secondaryColor } as React.CSSProperties}
        >
            <Head title={`Portal de Clientes - ${business.name}`} />

            <Toaster position="top-right" richColors />

            {/* Header Banner */}
            <div className="relative h-48 md:h-64 w-full overflow-hidden bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950">
                {business.cover && (
                    <img
                        src={business.cover}
                        alt="Business Cover"
                        className="absolute inset-0 h-full w-full object-cover opacity-40"
                    />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 max-w-7xl mx-auto px-4 py-6 md:px-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <div className="h-16 w-16 md:h-20 md:w-20 shrink-0 overflow-hidden rounded-2xl bg-white p-1.5 shadow-xl border border-white/20">
                            {business.logo ? (
                                <img src={business.logo} alt={business.name} className="h-full w-full object-contain rounded-xl" />
                            ) : (
                                <div className="h-full w-full flex items-center justify-center bg-indigo-50 text-brand-primary font-bold text-2xl rounded-xl">
                                    {business.name.substring(0, 2).toUpperCase()}
                                </div>
                            )}
                        </div>
                        <div>
                            <h1 className="text-xl md:text-3xl font-black text-white leading-tight">
                                {business.name}
                            </h1>
                            <p className="text-slate-300 text-xs md:text-sm font-semibold flex items-center gap-2 mt-1">
                                <Building className="h-4 w-4 text-[var(--brand-secondary)]" /> Portal de Clientes
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 bg-white/10 backdrop-blur-md rounded-2xl p-2 border border-white/10 text-white self-start md:self-end">
                        <UserIcon className="h-4 w-4 text-[var(--brand-secondary)] ml-1" />
                        <span className="text-xs font-bold mr-2">{currentUser.name}</span>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="bg-rose-500/80 hover:bg-rose-600 rounded-xl p-1.5 transition-all text-white"
                        >
                            <LogOut className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </div>

            {/* Content Container */}
            <main className="max-w-7xl mx-auto px-4 md:px-8 mt-8 grid grid-cols-1 lg:grid-cols-12 gap-8">

                {/* Main section */}
                <div className="lg:col-span-8 space-y-6">
                    {/* Navigation Tabs */}
                    <div className="flex bg-slate-200/60 p-1.5 rounded-2xl max-w-md border border-slate-200">
                        <button
                            onClick={() => setActiveTab('catalog')}
                            className={`flex-1 py-2.5 rounded-xl font-black text-sm transition-all flex items-center justify-center gap-2 ${activeTab === 'catalog'
                                ? 'bg-white text-slate-900 shadow-md'
                                : 'text-slate-600 hover:text-slate-950'
                                }`}
                        >
                            <ShoppingBag className="h-4 w-4" />
                            Catálogo
                        </button>
                        <button
                            onClick={() => setActiveTab('orders')}
                            className={`flex-1 py-2.5 rounded-xl font-black text-sm transition-all flex items-center justify-center gap-2 ${activeTab === 'orders' ? 'bg-white text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-950'}`}
                        >
                            <Clock className="h-4 w-4" />
                            Pedidos
                        </button>
                        <button
                            onClick={() => setActiveTab('support')}
                            className={`flex-1 py-2.5 rounded-xl font-black text-sm transition-all flex items-center justify-center gap-2 ${activeTab === 'support' ? 'bg-white text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-950'}`}
                        >
                            <ShieldAlert className="h-4 w-4" />
                            Soporte
                        </button>
                    </div>

                    {activeTab === 'catalog' && (
                        <>
                            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"> <div> <h2 className="text-2xl font-black text-slate-900"> Productos de {business.name} </h2> <p className="text-sm text-slate-500"> Catálogo privado asignado a tu cuenta cliente. </p> </div> <div className="flex items-center gap-2"> <Badge className="bg-slate-900 text-white px-3 py-1 rounded-xl"> {productos.length} productos </Badge>  </div> </div> {/* Products Grid */} {productos.length === 0 ? (<div className="bg-white rounded-3xl p-12 text-center border border-slate-100 shadow-sm space-y-4"> <AlertCircle className="h-12 w-12 text-slate-400 mx-auto" /> <h3 className="text-lg font-black"> No se encontraron productos </h3> <p className="text-slate-500 text-sm max-w-sm mx-auto"> El negocio aún no tiene productos disponibles para su catálogo. </p> </div>) : (<div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6"> {productos.map(prod => {
                                const cartQty = cart.find(i => i.product.id === prod.id)?.quantity || 0; return (<div key={prod.id} className="group relative overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm transition-all hover:scale-[1.01] hover:shadow-md flex flex-col justify-between" > <div> {/* IMAGE */} <div className="relative h-44 w-full bg-slate-100 overflow-hidden"> {prod.imagen ? (<img src={prod.imagen.startsWith('http') ? prod.imagen : `/storage/${prod.imagen}`} alt={prod.nombre} className="h-full w-full object-cover transition-all group-hover:scale-105" />) : (<div className="h-full w-full flex items-center justify-center bg-indigo-50/50 text-slate-400"> <ShoppingBag className="h-10 w-10 opacity-40 text-brand-primary" /> </div>)} {/* BADGES */} <div className="absolute top-3 right-3 flex flex-col gap-1.5 items-end"> <Badge className="bg-slate-900/80 backdrop-blur-md text-white font-bold text-[10px] px-2 py-0.5 rounded-full border-none"> {prod.categoria || 'General'} </Badge>  </div> </div> {/* INFO */} <div className="p-5 space-y-2"> <h3 className="font-black text-slate-900 group-hover:text-brand-primary transition-colors text-base line-clamp-1"> {prod.nombre} </h3> <p className="text-xs text-slate-400 font-bold uppercase tracking-wider"> Código: {prod.codigo} </p> {/* OWNER */} <p className="text-[11px] text-indigo-600 font-bold"> Distribuido por {business.name} </p> <p className="text-slate-500 text-xs line-clamp-2 min-h-[2rem]"> {prod.descripcion || 'Sin descripción disponible.'} </p> </div> </div> {/* FOOTER */} <div className="p-5 pt-0 border-t border-slate-50 mt-auto"> <div className="flex items-center justify-between pt-4"> {/* PRICE */} <div className="flex flex-col"> <span className="text-xs font-bold text-slate-400"> Precio </span> <span className="text-lg font-black text-slate-950"> {formatPrice(prod.precio_venta)} </span> </div> {/* CART */} {cartQty > 0 ? (<div className="flex items-center bg-slate-100 rounded-xl p-1 border border-slate-200"> <button onClick={() => updateQuantity(prod.id, -1)} className="h-8 w-8 flex items-center justify-center hover:bg-white rounded-lg text-slate-600 transition-colors" > <Minus className="h-3.5 w-3.5" /> </button> <input type="number" min="1" value={cartQty} onChange={e => handleQuantityInput(prod.id, e.target.value)} className="w-12 text-center font-bold text-sm text-slate-800 bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" /> <button onClick={() => updateQuantity(prod.id, 1)} className="h-8 w-8 flex items-center justify-center hover:bg-white rounded-lg text-slate-600 transition-colors" > <Plus className="h-3.5 w-3.5" /> </button> </div>) : (prod.course_id ? (
                                    <Link href={`/cursos/${prod.course_slug}`} className="btn-brand-primary font-bold rounded-xl shadow-sm px-4 h-8 gap-1.5 inline-flex items-center justify-center text-sm shrink-0">
                                        <ShoppingBag className="h-3.5 w-3.5" /> Inscribirse
                                    </Link>
                                ) : ((prod.requires_appointment || (prod.providers && prod.providers.length > 0)) ? (
                                    <button onClick={() => { setBookingProduct(prod); setBookingMonth(new Date()); setSelectedDay(null); setSelectedTime(''); setSelectedProvider(null); }} className="btn-brand-primary font-bold rounded-xl shadow-sm px-4 h-8 gap-1.5 inline-flex items-center justify-center text-sm shrink-0">
                                        <Calendar className="h-3.5 w-3.5" /> Agendar cita
                                    </button>
                                ) : (
                                    <Button onClick={() => addToCart(prod)} size="sm" className="btn-brand-primary font-bold rounded-xl shadow-sm px-4"> Comprar </Button>
                                )))} </div>  </div> </div>);
                            })} </div>)}</>)}

                    {/* Orders & Appointments History Tab */}
                    {activeTab === 'orders' && (
                        <div className="space-y-6">
                            {citasCliente.length > 0 && (
                                <div className="space-y-3">
                                    <h3 className="font-black text-lg text-slate-900 flex items-center gap-2">
                                        <CalendarCheck className="h-5 w-5 text-brand-primary" />
                                        Citas Agendadas
                                    </h3>
                                    {citasCliente.map(cita => {
                                        const statusStyles: Record<string, string> = {
                                            pendiente: 'bg-amber-50 text-amber-700 border-amber-200',
                                            confirmada: 'bg-blue-50 text-blue-700 border-blue-200',
                                            completada: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            cancelada: 'bg-rose-50 text-rose-700 border-rose-200',
                                        };
                                        const statusLabel: Record<string, string> = {
                                            pendiente: 'Pendiente',
                                            confirmada: 'Confirmada',
                                            completada: 'Completada',
                                            cancelada: 'Cancelada',
                                        };
                                        return (
                                            <div key={cita.id} className="bg-white rounded-3xl border border-slate-200/70 overflow-hidden shadow-sm">
                                                <div className="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                                    <div className="flex items-center gap-4">
                                                        <div className="h-11 w-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold shadow-sm">
                                                            <Calendar className="h-5 w-5" />
                                                        </div>
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <h3 className="font-black text-slate-900 text-sm md:text-base">
                                                                    {cita.producto?.nombre || 'Servicio'}
                                                                </h3>
                                                                <span className={`text-[11px] font-bold px-2 py-0.5 rounded-full border ${statusStyles[cita.status] || 'bg-slate-50 text-slate-600 border-slate-200'}`}>
                                                                    {statusLabel[cita.status] || cita.status}
                                                                </span>
                                                            </div>
                                                            <p className="text-xs text-slate-400 mt-1 flex items-center gap-1.5 font-semibold">
                                                                <Calendar className="h-3.5 w-3.5" />
                                                                {new Date(cita.start_time).toLocaleDateString(currency.locale, {
                                                                    day: '2-digit',
                                                                    month: 'short',
                                                                    year: 'numeric',
                                                                    hour: '2-digit',
                                                                    minute: '2-digit'
                                                                })}
                                                                {' — '}
                                                                {new Date(cita.end_time).toLocaleTimeString(currency.locale, {
                                                                    hour: '2-digit',
                                                                    minute: '2-digit'
                                                                })}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center justify-between sm:justify-end gap-6 border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-100">
                                                        <div className="text-left sm:text-right">
                                                            <span className="text-xs text-slate-400 font-bold block uppercase tracking-wider">
                                                                {cita.payment_status === 'pagado' ? 'Pagado' : 'Pendiente pago'}
                                                            </span>
                                                            {cita.notes && (
                                                                <p className="text-xs text-slate-400 mt-0.5">{cita.notes}</p>
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
                                <h3 className="font-black text-lg text-slate-900 flex items-center gap-2">
                                    <ShoppingBag className="h-5 w-5 text-brand-primary" />
                                    Pedidos
                                </h3>
                                {pedidos.data.length === 0 ? (
                                    <div className="bg-white rounded-3xl p-12 text-center border border-slate-100 shadow-sm space-y-4">
                                        <Clock className="h-12 w-12 text-slate-400 mx-auto" />
                                        <h3 className="text-lg font-black">No tienes pedidos anteriores</h3>
                                        <p className="text-slate-500 text-sm max-w-sm mx-auto">
                                            Todos tus pedidos confirmados o en tránsito se desplegarán en esta sección.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {pedidos.data.map(order => {
                                            const isExpanded = expandedOrder === order.id;

                                            return (
                                                <div
                                                    key={order.id}
                                                    className="bg-white rounded-3xl border border-slate-200/70 overflow-hidden shadow-sm transition-all"
                                                >
                                                    {/* Header summarized bar */}
                                                    <div
                                                        onClick={() => setExpandedOrder(isExpanded ? null : order.id)}
                                                        className="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 cursor-pointer hover:bg-slate-50/50"
                                                    >
                                                        <div className="flex items-center gap-4">
                                                            <div className="h-11 w-11 rounded-2xl bg-indigo-50 text-brand-primary flex items-center justify-center font-bold shadow-sm">
                                                                <ShoppingBag className="h-5 w-5" />
                                                            </div>
                                                            <div>
                                                                <div className="flex items-center gap-2">
                                                                    <h3 className="font-black text-slate-900 text-sm md:text-base">
                                                                        {order.numero_pedido}
                                                                    </h3>
                                                                    {getStatusBadge(order.estado)}
                                                                </div>
                                                                <p className="text-xs text-slate-400 mt-1 flex items-center gap-1.5 font-semibold">
                                                                    <Calendar className="h-3.5 w-3.5" />
                                                                    {new Date(order.created_at).toLocaleDateString(currency.locale, {
                                                                        day: '2-digit',
                                                                        month: 'short',
                                                                        year: 'numeric',
                                                                        hour: '2-digit',
                                                                        minute: '2-digit'
                                                                    })}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center justify-between sm:justify-end gap-6 border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-100">
                                                            <div className="text-left sm:text-right">
                                                                <span className="text-xs text-slate-400 font-bold block uppercase tracking-wider">Total</span>
                                                                <span className="text-base font-black text-slate-950">
                                                                    {formatPrice(order.total)}
                                                                </span>
                                                            </div>
                                                            <div className="flex items-center gap-3">
                                                                {order.estado === 'pendiente' && (
                                                                    <Button
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            handleCancelOrder(order.id);
                                                                        }}
                                                                        variant="outline"
                                                                        size="sm"
                                                                        className="border-rose-200 text-rose-600 hover:bg-rose-50 rounded-xl font-bold h-9"
                                                                    >
                                                                        Cancelar
                                                                    </Button>
                                                                )}
                                                                <ChevronDown className={`h-5 w-5 text-slate-400 transition-all ${isExpanded ? 'rotate-180' : ''}`} />
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {/* Expanded Details */}
                                                    {isExpanded && (
                                                        <div className="p-6 border-t border-slate-100 bg-slate-50/50 space-y-6">
                                                            {/* Items listing */}
                                                            <div>
                                                                <h4 className="text-xs font-black text-slate-400 uppercase tracking-widest mb-3">Detalle de Compra</h4>
                                                                <div className="bg-white rounded-2xl border border-slate-200/50 overflow-hidden divide-y divide-slate-100">
                                                                    {order.items.map(item => (
                                                                        <div key={item.id} className="p-4 flex items-center justify-between text-sm">
                                                                            <div>
                                                                                <p className="font-black text-slate-900">{item.nombre_producto}</p>
                                                                                <p className="text-xs text-slate-400 mt-0.5">
                                                                                    {formatPrice(item.precio_unitario)} x {item.cantidad} unidades
                                                                                </p>
                                                                            </div>
                                                                            <span className="font-bold text-slate-900">
                                                                                {formatPrice(item.subtotal)}
                                                                            </span>
                                                                        </div>
                                                                    ))}

                                                                    <div className="p-4 bg-slate-50/50 space-y-1.5 text-sm">
                                                                        <div className="flex justify-between text-slate-500 font-semibold">
                                                                            <span>Subtotal</span>
                                                                            <span>{formatPrice(order.subtotal)}</span>
                                                                        </div>
                                                                        <div className="flex justify-between text-slate-500 font-semibold">
                                                                            <span>IVA (19%)</span>
                                                                            <span>{formatPrice(order.impuesto)}</span>
                                                                        </div>
                                                                        <div className="flex justify-between text-slate-950 font-black text-base pt-1.5 border-t border-slate-200">
                                                                            <span>Total</span>
                                                                            <span>{formatPrice(order.total)}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {/* Despatch and general details */}
                                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                                <div className="bg-white rounded-2xl border border-slate-200/50 p-4 space-y-2 text-sm">
                                                                    <h4 className="text-xs font-black text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                                                        <Truck className="h-4 w-4 text-brand-primary" /> Información de Despacho
                                                                    </h4>
                                                                    <p className="pt-2"><strong className="text-slate-500 font-semibold">Contacto:</strong> {order.nombre_cliente}</p>
                                                                    <p><strong className="text-slate-500 font-semibold">Teléfono:</strong> {order.telefono_cliente}</p>
                                                                    <p><strong className="text-slate-500 font-semibold">Dirección:</strong> {order.direccion_cliente}</p>
                                                                </div>
                                                                <div className="bg-white rounded-2xl border border-slate-200/50 p-4 space-y-2 text-sm">
                                                                    <h4 className="text-xs font-black text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                                                        <Clock className="h-4 w-4 text-brand-primary" /> Datos del Pedido
                                                                    </h4>
                                                                    <p className="pt-2"><strong className="text-slate-500 font-semibold">Método de Pago:</strong> <span className="uppercase text-xs font-bold">{order.metodo_pago}</span></p>
                                                                    <p><strong className="text-slate-500 font-semibold">Notas:</strong> {order.notas || 'Sin comentarios.'}</p>
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
                            <div className="bg-white rounded-3xl border border-slate-200/70 shadow-sm p-5">
                                <h3 className="font-black text-lg text-slate-900 flex items-center gap-2 mb-4">
                                    <ShieldAlert className="h-5 w-5 text-brand-primary" />
                                    Mis Tickets
                                </h3>
                                {tickets.length === 0 ? (
                                    <div className="py-6 text-center space-y-2">
                                        <ShieldAlert className="h-8 w-8 text-slate-300 mx-auto opacity-60" />
                                        <p className="text-sm text-slate-400 font-semibold">No has enviado tickets aún</p>
                                        <p className="text-xs text-slate-300">Usa el formulario de abajo para reportar un problema.</p>
                                    </div>
                                ) : (
                                    <>
                                        {/* Summary bar */}
                                        {(() => {
                                            const counts: Record<string, number> = {};
                                            tickets.forEach(t => { counts[t.estado] = (counts[t.estado] || 0) + 1; });
                                            const totalAbiertos = (counts['abierto'] || 0) + (counts['en_proceso'] || 0) + (counts['pendiente'] || 0);
                                            const totalResueltos = (counts['resuelto'] || 0) + (counts['cerrado'] || 0);
                                            return (
                                                <div className="flex items-center gap-4 mb-4 text-xs">
                                                    <span className="text-slate-500 font-semibold">{tickets.length} total</span>
                                                    <span className="text-blue-600 font-bold bg-blue-50 px-2.5 py-1 rounded-full">{totalAbiertos} abiertos</span>
                                                    <span className="text-green-600 font-bold bg-green-50 px-2.5 py-1 rounded-full">{totalResueltos} resueltos</span>
                                                </div>
                                            );
                                        })()}
                                        <div className="space-y-2">
                                            {tickets.slice(0, 5).map((ticket) => {
                                                const estadoStyles: Record<string, string> = {
                                                    abierto: 'bg-blue-100 text-blue-700 border-blue-300',
                                                    en_proceso: 'bg-amber-100 text-amber-700 border-amber-300',
                                                    pendiente: 'bg-purple-100 text-purple-700 border-purple-300',
                                                    resuelto: 'bg-green-100 text-green-700 border-green-300',
                                                    cerrado: 'bg-gray-100 text-gray-600 border-gray-300',
                                                };
                                                const prioridadStyles: Record<string, string> = {
                                                    baja: 'text-slate-500 bg-slate-100',
                                                    media: 'text-amber-600 bg-amber-50',
                                                    alta: 'text-rose-600 bg-rose-50',
                                                    urgente: 'text-red-600 bg-red-100',
                                                };
                                                return (
                                                    <div key={ticket.id} className="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 transition-all hover:shadow-sm">
                                                        <div className="flex items-start justify-between gap-3">
                                                            <div className="min-w-0 flex-1">
                                                                <h4 className="font-bold text-sm text-slate-900">
                                                                    {ticket.titulo}
                                                                </h4>
                                                                {ticket.descripcion && (
                                                                    <p className="text-xs text-slate-500 mt-1 line-clamp-2">
                                                                        {ticket.descripcion}
                                                                    </p>
                                                                )}
                                                                <div className="flex items-center gap-2 mt-2 flex-wrap">
                                                                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${estadoStyles[ticket.estado] || 'bg-slate-100 text-slate-600'}`}>
                                                                        {ticket.estado === 'en_proceso' ? 'En Proceso' : ticket.estado.charAt(0).toUpperCase() + ticket.estado.slice(1)}
                                                                    </span>
                                                                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${prioridadStyles[ticket.prioridad] || 'bg-slate-100 text-slate-600'}`}>
                                                                        {ticket.prioridad.charAt(0).toUpperCase() + ticket.prioridad.slice(1)}
                                                                    </span>
                                                                    <span className="text-[10px] text-slate-400">
                                                                        {new Date(ticket.created_at).toLocaleDateString(currency.locale, { day: '2-digit', month: 'short', year: 'numeric' })}
                                                                    </span>
                                                                    {ticket.asignado_a && (
                                                                        <span className="text-[10px] text-slate-400">
                                                                            · {ticket.asignado_a}
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
                            <div className="bg-white rounded-3xl border border-slate-200/70 shadow-sm p-6">
                                <h3 className="font-black text-lg text-slate-900 flex items-center gap-2 mb-1">
                                    <LifeBuoy className="h-5 w-5 text-brand-primary" />
                                    Enviar Ticket de Soporte
                                </h3>
                                <p className="text-xs text-slate-500 mb-4">
                                    Reporta un problema o solicita ayuda al equipo de {business.name}
                                </p>
                                <form onSubmit={handleSupportTicket} className="space-y-3">
                                    <div>
                                        <label className="text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1 block">Asunto *</label>
                                        <Input
                                            value={ticketData.titulo}
                                            onChange={e => setTicketData(prev => ({ ...prev, titulo: e.target.value }))}
                                            placeholder="Ej: Problema con mi pedido"
                                            className="bg-slate-50 border-slate-200 text-sm h-10 rounded-xl"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1 block">Descripción</label>
                                        <Textarea
                                            value={ticketData.descripcion}
                                            onChange={e => setTicketData(prev => ({ ...prev, descripcion: e.target.value }))}
                                            placeholder="Describe el detalle de tu consulta..."
                                            className="bg-slate-50 border-slate-200 text-sm min-h-[80px] rounded-xl"
                                            rows={3}
                                        />
                                    </div>
                                    <div>
                                        <label className="text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1 block">Prioridad</label>
                                        <select
                                            value={ticketData.prioridad}
                                            onChange={e => setTicketData(prev => ({ ...prev, prioridad: e.target.value }))}
                                            className="w-full text-sm h-10 rounded-xl border border-slate-200 bg-slate-50 px-3"
                                        >
                                            <option value="baja">Baja</option>
                                            <option value="media">Media</option>
                                            <option value="alta">Alta</option>
                                            <option value="urgente">Urgente</option>
                                        </select>
                                    </div>
                                    <Button type="submit" disabled={ticketLoading} className="w-full btn-brand-primary h-11 rounded-xl font-black text-sm shadow-lg">
                                        <LifeBuoy className="h-4 w-4 mr-2" />
                                        {ticketLoading ? 'Enviando...' : 'Enviar Ticket'}
                                    </Button>
                                </form>
                            </div>
                        </div>
                    )}
                </div>

                {/* Sidebar */}
                <div className="lg:col-span-4 space-y-6">
                    {/* Contact Info card */}
                    <Card className="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <CardHeader className="bg-slate-50/50 border-b border-slate-100 flex flex-row items-center gap-3">
                            <Phone className="h-5 w-5 text-brand-primary" />
                            <CardTitle className="text-base font-black">Contacto Comercial</CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 space-y-3.5 text-sm font-semibold">
                            {business.phone && (
                                <a
                                    href={`https://wa.me/${business.phone.replace(/[^0-9]/g, '')}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-2.5 text-slate-700 hover:text-emerald-600 transition-colors"
                                >
                                    <MessageCircle className="h-4 w-4 text-emerald-500 shrink-0" />
                                    <span>{business.phone}</span>
                                </a>
                            )}
                            <p className="flex items-center gap-2.5 text-slate-700">
                                <Mail className="h-4 w-4 text-slate-400 shrink-0" />
                                <span className="truncate">{business.email}</span>
                            </p>
                            {store && (
                                <div className="pt-3 border-t border-slate-100">
                                    <a
                                        href={`/tienda/${store.slug}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-xs text-brand-primary hover:underline font-bold flex items-center gap-1"
                                    >
                                        Visitar tienda pública online &rarr;
                                    </a>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Cart sidebar — desktop only */}
                    <div className="hidden lg:block bg-white rounded-3xl border border-slate-200 shadow-sm">
                        <div className="p-4 bg-slate-900 text-white flex items-center justify-between">
                            <h3 className="font-black text-sm flex items-center gap-2">
                                <ShoppingCart className="h-4 w-4 text-[var(--brand-secondary)]" /> Carrito
                            </h3>
                            <Badge className="bg-[var(--brand-secondary)] text-slate-950 font-bold px-2 py-0.5 text-xs">
                                {cart.reduce((sum, item) => sum + item.quantity, 0)} items
                            </Badge>
                        </div>

                        {cart.length === 0 ? (
                            <div className="p-8 text-center space-y-3">
                                <ShoppingCart className="h-8 w-8 text-slate-300 mx-auto opacity-70" />
                                <p className="text-slate-400 text-sm font-bold">Carrito vacío</p>
                                <p className="text-slate-400 text-xs">Añada productos del catálogo para comenzar.</p>
                            </div>
                        ) : (
                            <div className="flex flex-col">
                                <div className="p-4 space-y-3 max-h-80 overflow-y-auto">
                                    {cart.map(item => (
                                        <div key={item.product.id} className="flex items-center justify-between gap-2 text-xs">
                                            <div className="flex-1 min-w-0">
                                                <p className="font-black text-slate-900 truncate text-xs">{item.product.nombre}</p>
                                                <p className="text-[10px] text-slate-400 font-semibold mt-0.5">
                                                    {formatPrice(item.product.precio_venta)} c/u
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-1 shrink-0">
                                                <div className="flex items-center bg-slate-100 rounded-md p-0.5 border border-slate-200">
                                                    <button onClick={() => updateQuantity(item.product.id, -1)} className="h-5 w-5 flex items-center justify-center hover:bg-white rounded text-slate-600 transition-colors">
                                                        <Minus className="h-2.5 w-2.5" />
                                                    </button>
                                                    <span className="px-1.5 font-bold text-[10px] text-slate-800 min-w-[16px] text-center">{item.quantity}</span>
                                                    <button onClick={() => updateQuantity(item.product.id, 1)} className="h-5 w-5 flex items-center justify-center hover:bg-white rounded text-slate-600 transition-colors">
                                                        <Plus className="h-2.5 w-2.5" />
                                                    </button>
                                                </div>
                                                <button onClick={() => removeFromCart(item.product.id)} className="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
                                                    <Trash2 className="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <div className="border-t border-slate-200 p-4 space-y-3">
                                    <div className="space-y-1 text-[10px] text-slate-500 font-bold uppercase tracking-wider">
                                        <div className="flex justify-between">
                                            <span>Subtotal</span>
                                            <span className="text-slate-800 font-black text-xs">{formatPrice(cartSubtotal)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>IVA (19%)</span>
                                            <span className="text-slate-800 font-black text-xs">{formatPrice(cartTax)}</span>
                                        </div>
                                        <div className="flex justify-between text-xs pt-1.5 border-t border-slate-200 text-slate-900">
                                            <span>Total</span>
                                            <span className="text-brand-primary font-black text-sm">{formatPrice(cartTotal)}</span>
                                        </div>
                                    </div>
                                    <form onSubmit={handleCheckout} className="space-y-2">
                                        <Input type="text" required placeholder="Nombre de contacto" value={data.nombre_cliente} onChange={e => setData('nombre_cliente', e.target.value)} className="bg-slate-50 text-xs h-8 rounded-lg" />
                                        <Input type="text" required placeholder="Teléfono" value={data.telefono_cliente} onChange={e => setData('telefono_cliente', e.target.value)} className="bg-slate-50 text-xs h-8 rounded-lg" />
                                        <Input type="text" required placeholder="Dirección de despacho" value={data.direccion_cliente} onChange={e => setData('direccion_cliente', e.target.value)} className="bg-slate-50 text-xs h-8 rounded-lg" />
                                        <Textarea placeholder="Notas..." value={data.notas} onChange={e => setData('notas', e.target.value)} className="bg-slate-50 text-xs min-h-[48px] rounded-lg" />
                                        <select value={data.metodo_pago} onChange={e => setData('metodo_pago', e.target.value)} className="w-full text-xs h-8 rounded-lg border border-slate-200 px-2 bg-slate-50">
                                            <option value="efectivo">Efectivo contra entrega</option>
                                            <option value="transferencia">Transferencia Bancaria</option>
                                            <option value="tarjeta">Tarjeta de Crédito/Débito</option>
                                        </select>
                                        <Button type="submit" disabled={processing} className="w-full btn-brand-primary h-10 rounded-xl font-black text-xs flex items-center justify-center gap-2 shadow-lg">
                                            <Check className="h-3.5 w-3.5" />
                                            {processing ? 'Procesando...' : 'Confirmar Pedido'}
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
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onClick={() => { setBookingProduct(null); setSelectedDay(null); setSelectedTime(''); setSelectedProvider(null); setBookingMonth(new Date()); }}>
                    <div className="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-5 space-y-4" onClick={e => e.stopPropagation()}>
                        {/* Header */}
                        <div className="flex items-start justify-between">
                            <div>
                                <h3 className="text-base font-black text-slate-900">Agendar Cita</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{bookingProduct.nombre}</p>
                            </div>
                            <button onClick={() => { setBookingProduct(null); setSelectedDay(null); setSelectedTime(''); setSelectedProvider(null); setBookingMonth(new Date()); }} className="p-1 hover:bg-slate-100 rounded-lg transition-colors">
                                <XCircle className="h-4 w-4 text-slate-400" />
                            </button>
                        </div>

                        {/* Info bar */}
                        {bookingProduct.duracion && (
                            <div className="flex items-center gap-1.5 text-xs text-slate-600 bg-slate-50 rounded-xl px-3 py-2">
                                <Clock className="h-3.5 w-3.5 text-brand-primary" />
                                <strong>{bookingProduct.duracion} min</strong>
                                <span className="text-slate-300 mx-1">|</span>
                                <DollarSign className="h-3.5 w-3.5 text-brand-primary" />
                                <strong>${Number(bookingProduct.precio_venta).toLocaleString()}</strong>
                            </div>
                        )}

                        {/* Calendar */}
                        <div className="select-none">
                            {/* Month/Year header */}
                            <div className="flex items-center justify-between mb-3">
                                <button onClick={() => setBookingMonth(subMonths(bookingMonth, 1))} className="p-1 hover:bg-slate-100 rounded-lg transition-colors">
                                    <ChevronLeft className="h-4 w-4 text-slate-600" />
                                </button>
                                <span className="text-sm font-bold text-slate-800">
                                    {format(bookingMonth, 'MMMM yyyy', { locale: es })}
                                </span>
                                <button onClick={() => setBookingMonth(addMonths(bookingMonth, 1))} className="p-1 hover:bg-slate-100 rounded-lg transition-colors">
                                    <ChevronRight className="h-4 w-4 text-slate-600" />
                                </button>
                            </div>
                            {/* Day headers */}
                            <div className="grid grid-cols-7 mb-1">
                                {['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'].map(d => (
                                    <div key={d} className="text-center text-[10px] font-bold text-slate-400 uppercase tracking-wider py-1">{d}</div>
                                ))}
                            </div>
                            {/* Day cells */}
                            <div className="grid grid-cols-7 gap-0.5">
                                {(() => {
                                    const monthStart = startOfMonth(bookingMonth);
                                    const monthEnd = endOfMonth(bookingMonth);
                                    const allDays = eachDayOfInterval({ start: monthStart, end: monthEnd });
                                    const startPad = getDay(monthStart);
                                    const today = startOfDay(new Date());
                                    const cells: React.ReactNode[] = [];
                                    for (let i = 0; i < startPad; i++) {
                                        cells.push(<div key={`pad-${i}`} />);
                                    }
                                    allDays.forEach(day => {
                                        const isPast = isBefore(day, today);
                                        const isSelected = selectedDay && isSameDay(day, selectedDay);
                                        const isCurrent = isToday(day);
                                        cells.push(
                                            <button
                                                key={day.toISOString()}
                                                disabled={isPast}
                                                onClick={() => { setSelectedDay(day); setSelectedTime(''); setSelectedProvider(null); }}
                                                className={`text-center text-xs py-1.5 rounded-lg font-semibold transition-all
                                                    ${isPast ? 'text-slate-300 cursor-not-allowed' : 'hover:bg-brand-primary/10 hover:text-brand-primary'}
                                                    ${isSelected ? 'bg-brand-primary text-white shadow-sm shadow-brand-primary/30' : ''}
                                                    ${isCurrent && !isSelected ? 'ring-1 ring-brand-primary/30 text-brand-primary' : ''}
                                                    ${!isPast && !isSelected && !isCurrent ? 'text-slate-700' : ''}
                                                `}
                                            >
                                                {format(day, 'd')}
                                            </button>
                                        );
                                    });
                                    return cells;
                                })()}
                            </div>
                        </div>

                        {/* Time picker */}
                        {selectedDay && (
                            <div>
                                <p className="text-xs font-bold text-slate-700 mb-2">Horario disponible</p>
                                <div className="grid grid-cols-4 gap-1.5">
                                    {Array.from({ length: 10 }, (_, i) => i + 8).flatMap(h =>
                                        [0, 30].map(m => {
                                            const time = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
                                            const slotStart = new Date(`${format(selectedDay, 'yyyy-MM-dd')}T${time}:00`);
                                            const slotEnd = new Date(slotStart.getTime() + (bookingProduct.duracion || 60) * 60000);
                                            const isPastSlot = isBefore(slotStart, new Date());
                                            const bookedProviders = appointments
                                                .filter(app => {
                                                    const appStart = new Date(app.start_time);
                                                    const appEnd = new Date(app.end_time);
                                                    return slotStart < appEnd && slotEnd > appStart;
                                                })
                                                .map(app => app.provider_id);

                                            const productProviders = bookingProduct.providers?.map(p => p.id) || [];
                                            const hasProviders = productProviders.length > 0;

                                            const isBooked = selectedProvider
                                                ? bookedProviders.includes(selectedProvider)
                                                : hasProviders
                                                    ? productProviders.every(pid => bookedProviders.includes(pid))
                                                    : bookedProviders.length > 0;
                                            const isDisabled = isPastSlot || isBooked;
                                            const isSelected = selectedTime === time;
                                            return (
                                                <button
                                                    key={time}
                                                    disabled={isDisabled}
                                                    onClick={() => { setSelectedTime(time); setSelectedProvider(null); }}
                                                    className={`text-xs py-1.5 rounded-lg font-semibold transition-all
                                                        ${isDisabled ? 'text-slate-300 cursor-not-allowed bg-slate-50 line-through' : 'hover:bg-brand-primary/10 hover:text-brand-primary bg-slate-50'}
                                                        ${isSelected ? 'bg-brand-primary text-white shadow-sm shadow-brand-primary/30 ring-0' : 'text-slate-600'}
                                                    `}
                                                    title={isBooked
                                                        ? 'Horario no disponible'
                                                        : hasProviders && !selectedProvider
                                                            ? `${productProviders.length - bookedProviders.length} profesional(es) disponible(s)`
                                                            : time
                                                    }
                                                >
                                                    {time}
                                                </button>
                                            );
                                        })
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Provider selector */}
                        {bookingProduct.providers && bookingProduct.providers.length > 0 && selectedDay && selectedTime && (
                            <div>
                                <p className="text-xs font-bold text-slate-700 mb-2">Elige un profesional</p>
                                <div className="grid grid-cols-2 gap-2">
                                    {bookingProduct.providers.map(prov => {
                                        const provSlotStart = new Date(`${format(selectedDay, 'yyyy-MM-dd')}T${selectedTime}:00`);
                                        const provSlotEnd = new Date(provSlotStart.getTime() + (bookingProduct.duracion || 60) * 60000);
                                        const isProviderBooked = appointments.some(app =>
                                            app.provider_id === prov.id &&
                                            provSlotStart < new Date(app.end_time) &&
                                            provSlotEnd > new Date(app.start_time)
                                        );
                                        return (
                                            <button
                                                key={prov.id}
                                                disabled={isProviderBooked}
                                                onClick={() => setSelectedProvider(prov.id)}
                                                className={`flex items-center gap-2.5 p-3 rounded-xl font-semibold transition-all text-left
                                                    ${selectedProvider === prov.id
                                                        ? 'bg-gray-900 text-white ring-2 ring-brand-primary shadow-lg'
                                                        : isProviderBooked
                                                            ? 'bg-gray-100 text-gray-300 cursor-not-allowed line-through'
                                                            : 'bg-gray-800 text-white hover:bg-gray-700 hover:shadow-md'
                                                    }
                                                `}
                                            >
                                                <img
                                                    src={prov.photo}
                                                    alt={prov.name}
                                                    className="h-9 w-9 rounded-full object-cover shrink-0 ring-2 ring-white/20"
                                                />
                                                <div className="min-w-0">
                                                    <span className="block text-sm leading-tight truncate">{prov.name}</span>
                                                    {isProviderBooked
                                                        ? <span className="block text-[10px] font-normal text-gray-400">No disponible</span>
                                                        : selectedProvider === prov.id
                                                            ? <span className="block text-[10px] font-normal text-brand-primary">Seleccionado</span>
                                                            : <span className="block text-[10px] font-normal text-gray-400">Disponible</span>
                                                    }
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex gap-2 pt-1">
                            <Button variant="outline" size="sm" className="flex-1 h-10 text-sm" onClick={() => { setBookingProduct(null); setSelectedDay(null); setSelectedTime(''); setSelectedProvider(null); setBookingMonth(new Date()); }}>
                                Cancelar
                            </Button>
                            <Button
                                size="sm"
                                className="flex-1 h-10 text-sm btn-brand-primary font-bold"
                                disabled={!selectedDay || !selectedTime || (bookingProduct.providers && bookingProduct.providers.length > 0 && !selectedProvider)}
                                onClick={() => {
                                    if (!selectedDay || !selectedTime) return;
                                    const dateTimeStr = `${format(selectedDay, 'yyyy-MM-dd')}T${selectedTime}:00`;
                                    router.post(cliente.citas.store().url, {
                                        producto_id: bookingProduct.id,
                                        start_time: dateTimeStr,
                                        provider_id: selectedProvider,
                                    }, {
                                        onSuccess: () => {
                                            setBookingSuccess({
                                                servicio: bookingProduct.nombre,
                                                fecha: format(selectedDay, 'EEEE d \'de\' MMMM', { locale: es }),
                                                hora: selectedTime,
                                            });
                                            setBookingProduct(null);
                                            setSelectedDay(null);
                                            setSelectedTime('');
                                            setSelectedProvider(null);
                                            setBookingMonth(new Date());
                                        },
                                        onError: (errs) => {
                                            toast.error(errs.error || 'Error al agendar la cita.');
                                        },
                                    });
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
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-6 text-center space-y-4">
                        <div className="mx-auto h-14 w-14 rounded-full bg-emerald-100 flex items-center justify-center">
                            <CheckCircle2 className="h-7 w-7 text-emerald-600" />
                        </div>
                        <div>
                            <h3 className="text-lg font-black text-slate-900">¡Cita agendada!</h3>
                            <p className="text-sm text-slate-500 mt-1">Tu solicitud fue enviada correctamente.</p>
                        </div>
                        <div className="bg-slate-50 rounded-2xl p-4 space-y-2 text-sm text-left">
                            <div className="flex justify-between">
                                <span className="text-slate-400 font-semibold">Servicio</span>
                                <span className="font-bold text-slate-900">{bookingSuccess.servicio}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-400 font-semibold">Fecha</span>
                                <span className="font-bold text-slate-900">{bookingSuccess.fecha}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-400 font-semibold">Hora</span>
                                <span className="font-bold text-slate-900">{bookingSuccess.hora} hrs</span>
                            </div>
                        </div>
                        <p className="text-xs text-slate-400">El vendedor confirmará tu cita y te contactará si es necesario.</p>
                        <Button className="w-full h-11 btn-brand-primary font-bold" onClick={() => setBookingSuccess(null)}>
                            Entendido
                        </Button>
                    </div>
                </div>
            )}

            {/* Mobile cart button */}
            {cart.length > 0 && (
                <button
                    onClick={() => setIsCartOpen(true)}
                    className="fixed bottom-0 left-0 right-0 z-40 lg:hidden flex items-center justify-between bg-slate-900 text-white px-5 py-3.5 shadow-2xl shadow-slate-900/30"
                >
                    <div className="flex items-center gap-2">
                        <ShoppingCart className="h-5 w-5 text-[var(--brand-secondary)]" />
                        <span className="font-black text-sm">{cart.reduce((sum, item) => sum + item.quantity, 0)} items</span>
                    </div>
                    <span className="font-black text-sm">{formatPrice(cartTotal)}</span>
                </button>
            )}

            {/* Mobile cart overlay */}
            {isCartOpen && (
                <div className="fixed inset-0 z-50 lg:hidden flex flex-col bg-white">
                    <div className="sticky top-0 z-10 p-4 bg-slate-900 text-white flex items-center justify-between">
                        <h2 className="font-black text-sm flex items-center gap-2">
                            <ShoppingCart className="h-4 w-4 text-[var(--brand-secondary)]" />
                            Carrito de Compras
                            <Badge className="bg-[var(--brand-secondary)] text-slate-950 font-bold px-2 py-0.5 text-xs ml-2">
                                {cart.reduce((sum, item) => sum + item.quantity, 0)} items
                            </Badge>
                        </h2>
                        <button onClick={() => setIsCartOpen(false)} className="p-1.5 hover:bg-white/10 rounded-lg transition-colors">
                            <XCircle className="h-5 w-5" />
                        </button>
                    </div>

                    <div className="flex-1 overflow-y-auto p-4 space-y-3">
                        {cart.map(item => (
                            <div key={item.product.id} className="flex items-center justify-between gap-3 text-sm p-3 bg-slate-50 rounded-2xl border border-slate-100">
                                <div className="flex-1 min-w-0">
                                    <p className="font-black text-slate-900 truncate">{item.product.nombre}</p>
                                    <p className="text-xs text-slate-400 font-semibold mt-0.5">{formatPrice(item.product.precio_venta)} c/u</p>
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    <div className="flex items-center bg-white rounded-lg p-0.5 border border-slate-200">
                                        <button onClick={() => updateQuantity(item.product.id, -1)} className="h-7 w-7 flex items-center justify-center hover:bg-slate-100 rounded text-slate-600 transition-colors">
                                            <Minus className="h-3.5 w-3.5" />
                                        </button>
                                        <span className="px-3 font-bold text-sm text-slate-800 min-w-[24px] text-center">{item.quantity}</span>
                                        <button onClick={() => updateQuantity(item.product.id, 1)} className="h-7 w-7 flex items-center justify-center hover:bg-slate-100 rounded text-slate-600 transition-colors">
                                            <Plus className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <button onClick={() => removeFromCart(item.product.id)} className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="sticky bottom-0 border-t border-slate-200 p-4 bg-white space-y-3">
                        <div className="space-y-1 text-xs text-slate-500 font-bold uppercase tracking-wider">
                            <div className="flex justify-between">
                                <span>Subtotal</span>
                                <span className="text-slate-800 font-black">{formatPrice(cartSubtotal)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>IVA (19%)</span>
                                <span className="text-slate-800 font-black">{formatPrice(cartTax)}</span>
                            </div>
                            <div className="flex justify-between text-sm pt-2 border-t border-slate-200 text-slate-900">
                                <span>Total</span>
                                <span className="text-brand-primary font-black text-base">{formatPrice(cartTotal)}</span>
                            </div>
                        </div>
                        <form onSubmit={handleCheckout} className="space-y-2">
                            <Input type="text" required placeholder="Nombre de contacto" value={data.nombre_cliente} onChange={e => setData('nombre_cliente', e.target.value)} className="bg-white text-xs h-9 rounded-lg border-slate-200" />
                            <Input type="text" required placeholder="Teléfono" value={data.telefono_cliente} onChange={e => setData('telefono_cliente', e.target.value)} className="bg-white text-xs h-9 rounded-lg border-slate-200" />
                            <Input type="text" required placeholder="Dirección de despacho" value={data.direccion_cliente} onChange={e => setData('direccion_cliente', e.target.value)} className="bg-white text-xs h-9 rounded-lg border-slate-200" />
                            <Textarea placeholder="Notas..." value={data.notas} onChange={e => setData('notas', e.target.value)} className="bg-white text-xs min-h-[50px] rounded-lg border-slate-200" />
                            <select value={data.metodo_pago} onChange={e => setData('metodo_pago', e.target.value)} className="w-full text-xs h-9 rounded-lg border border-slate-200 px-2 bg-white">
                                <option value="efectivo">Efectivo contra entrega</option>
                                <option value="transferencia">Transferencia Bancaria</option>
                                <option value="tarjeta">Tarjeta de Crédito/Débito</option>
                            </select>
                            <Button type="submit" disabled={processing} className="w-full btn-brand-primary h-11 rounded-xl font-black text-sm flex items-center justify-center gap-2 shadow-lg">
                                <Check className="h-4 w-4" />
                                {processing ? 'Procesando...' : 'Confirmar Pedido'}
                            </Button>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

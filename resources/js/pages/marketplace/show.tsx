import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Store,
    MapPin,
    Mail,
    Star,
    CheckCircle2,
    Share2,
    Heart,
    Plus,
    Minus,
    ShoppingCart,
    MessageSquare,
    Check,
    Wallet,
    CreditCard,
    Copy,
    Facebook,
    MessageCircle,
    Phone,
    Calendar,
    Clock,
} from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';
import { toast } from 'sonner';
import { FormInput } from '@/components/form-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import '@/components/whatsapp-button';
import { useCountry } from '@/hooks/use-country';
import AppLayout from '@/layouts/app-layout';
import { cn, formatRut } from '@/lib/utils';
import chat from '@/routes/chat';
import type { User } from '@/types';

interface LocalUserType extends User {
    ciudad?: string | null;
    comuna?: string | null;
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

interface UserReaction {
    like: number;
    rating: number | null;
}

interface StoreReview {
    id: number;
    user_id: number;
    public_profile_id: number;
    comentario: string;
    created_at: string;
    user: {
        id: number;
        name: string;
        profile_photo_url?: string;
    };
}

interface StoreProfile {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    phone: string | null;
    email: string | null;
    is_official: boolean;
    is_verified: boolean;
    user: LocalUserType;
    categorias: Categoria[];
    productos: Producto[];
    created_at: string;
    likes_count: number;
    rating_total: number;
    rating_count: number;
}

export default function MarketplaceShow({
    store,
    userReaction,
    paymentConfig,
    reviews = [],
    hasPurchased = false,
    userReview = null,
}: {
    store: StoreProfile;
    userReaction?: UserReaction;
    paymentConfig?: {
        is_active: boolean;
        paypal_active: boolean;
        mercadopago_active: boolean;
    };
    reviews?: StoreReview[];
    hasPurchased?: boolean;
    userReview?: StoreReview | null;
}) {
    const { currency } = useCountry();
    const { auth } = usePage<{ auth: { user?: User } }>().props;

    // States - Carrito persistente en localStorage
    const carritoKey = `carrito_${store.slug}`;
    const [carrito, setCarritoState] = useState<{ [key: number]: number }>(
        () => {
            if (typeof window !== 'undefined') {
                const saved = localStorage.getItem(carritoKey);
                return saved ? JSON.parse(saved) : {};
            }
            return {};
        },
    );
    const setCarrito = (
        val:
            | { [key: number]: number }
            | ((prev: { [key: number]: number }) => { [key: number]: number }),
    ) => {
        setCarritoState((prev) => {
            const next = typeof val === 'function' ? val(prev) : val;
            localStorage.setItem(carritoKey, JSON.stringify(next));
            return next;
        });
    };
    const [cantidadesAgregar, setCantidadesAgregar] = useState<{
        [key: number]: number;
    }>({});
    const [productoAgregado, setProductoAgregado] = useState<number | null>(
        null,
    );
    const [carritoAbierto, setCarritoAbierto] = useState(false);
    const [checkoutAbierto, setCheckoutAbierto] = useState(false);
    const [modoGramosProducto, setModoGramosProducto] = useState<{
        [key: number]: boolean;
    }>(() => {
        if (typeof window !== 'undefined') {
            const saved: { [key: string]: boolean } = {};
            Object.keys(localStorage).forEach((key) => {
                if (key.startsWith('modoGramos_')) {
                    const id = parseInt(key.replace('modoGramos_', ''));
                    saved[id] = localStorage.getItem(key) === 'true';
                }
            });
            return saved;
        }
        return {};
    });
    const toggleModoGramos = (productoId: number) => {
        const modoKey = `modoGramos_${productoId}`;
        setModoGramosProducto((prev) => {
            const next = { ...prev, [productoId]: !prev[productoId] };
            localStorage.setItem(modoKey, String(next[productoId]));
            return next;
        });
    };
    const [tabActivo, setTabActivo] = useState<'productos' | 'informacion' | 'opiniones'>('productos');
    const [opinionTexto, setOpinionTexto] = useState('');
    const [enviandoOpinion, setEnviandoOpinion] = useState(false);
    const [datosCheckout, setDatosCheckout] = useState({
        nombre_cliente: '',
        rut_cliente: '',
        email_cliente: '',
        telefono_cliente: '',
        region: '',
        comuna: '',
        direccion_cliente: '',
        numero_direccion: '',
        depto_casa: '',
        metodo_pago: 'efectivo',
    });

    // Autocompletar con datos del usuario autenticado
    useEffect(() => {
        if (auth?.user) {
            const user = auth.user;
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setDatosCheckout((prev) => ({
                ...prev,
                nombre_cliente: prev.nombre_cliente || user.name || '',
                email_cliente: prev.email_cliente || user.email || '',
                telefono_cliente:
                    prev.telefono_cliente || (user.telefono as string) || '',
                direccion_cliente:
                    prev.direccion_cliente || (user.direccion as string) || '',
                region: prev.region || (user.region as string) || '',
                comuna: prev.comuna || (user.comuna as string) || '',
                rut_cliente: prev.rut_cliente || (user.rut as string) || '',
            }));
        }
    }, [auth]);

    const [procesando, setProcesando] = useState(false);
    const [localLike, setLocalLike] = useState(userReaction?.like ?? 0);
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [localRating, setLocalRating] = useState(userReaction?.rating ?? 0);

    // Derived
    const averageRating =
        store.rating_count > 0
            ? (store.rating_total / store.rating_count).toFixed(1)
            : '5.0';
    const totalItems = useMemo(
        () => Object.values(carrito).reduce((a, b) => a + b, 0),
        [carrito],
    );
    const productosEnCarrito = useMemo(
        () => store.productos.filter((p) => carrito[p.id] && carrito[p.id] > 0),
        [store.productos, carrito],
    );
    const totalCarrito = useMemo(
        () =>
            productosEnCarrito.reduce(
                (sum, p) => sum + p.precio_venta * (carrito[p.id] || 0),
                0,
            ),
        [productosEnCarrito, carrito],
    );

    const agregarAlCarrito = (productoId: number) => {
        const producto = store.productos.find((p) => p.id === productoId);
        const cantidadRaw = cantidadesAgregar[productoId] || 1;
        const cantidad = producto && modoGramosProducto[productoId]
            ? cantidadRaw / 1000
            : cantidadRaw;
        setCarrito((prev) => ({
            ...prev,
            [productoId]: (prev[productoId] || 0) + cantidad,
        }));
        setCantidadesAgregar((prev) => ({ ...prev, [productoId]: 1 }));
        setProductoAgregado(productoId);
        setTimeout(() => setProductoAgregado(null), 2000);
        toast.success('Añadido al pedido');
    };

    const precioProporcional = (producto: Producto, cantidad: number): number | null => {
        if (!['kg', 'lt'].includes(producto.unidad_medida)) return null;
        return producto.precio_venta * (cantidad / 1000);
    };

    const enviarCheckout = () => {
        if (!datosCheckout.nombre_cliente) {
            toast.error('El nombre es obligatorio');
            return;
        }
        setProcesando(true);
        const items = Object.entries(carrito).map(([id, qty]) => ({
            producto_id: parseInt(id),
            cantidad: qty,
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
                    Object.keys(localStorage).forEach((key) => {
                        if (key.startsWith('modoGramos_')) {
                            localStorage.removeItem(key);
                        }
                    });
                    setModoGramosProducto({});
                    setCarritoAbierto(false);
                    setDatosCheckout({
                        nombre_cliente: '',
                        rut_cliente: '',
                        email_cliente: '',
                        telefono_cliente: '',
                        region: '',
                        comuna: '',
                        direccion_cliente: '',
                        numero_direccion: '',
                        depto_casa: '',
                        metodo_pago: 'efectivo',
                    });
                    toast.success('¡Pedido enviado con éxito!');
                },
                onError: () => setProcesando(false),
            },
        );
    };

    const handleLike = () => {
        if (!auth.user) {
            toast.error('Debes iniciar sesión para dar me gusta');
            return;
        }
        router.post(`/tienda/${store.slug}/react`, { like: localLike ? 0 : 1 }, {
            preserveScroll: true,
            onSuccess: () => {
                setLocalLike(localLike ? 0 : 1);
            }
        });
    };

    const handleSocialShare = (platform: string) => {
        const shareUrl = encodeURIComponent(window.location.href);
        const text = encodeURIComponent(`¡Mira esta tienda: ${store.title}!`);

        switch (platform) {
            case 'whatsapp':
                window.open(`https://api.whatsapp.com/send?text=${text}%20${shareUrl}`, '_blank');
                break;
            case 'facebook':
                window.open(`https://www.facebook.com/sharer/sharer.php?u=${shareUrl}`, '_blank');
                break;
            case 'copy':
                navigator.clipboard.writeText(window.location.href);
                toast.success('¡Enlace de la tienda copiado al portapapeles!');
                break;
        }
    };

    const ProductCard = ({ producto }: { producto: Producto }) => (
        <div className="group relative flex flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm hover:shadow-md transition-shadow">
            <div className="relative aspect-square overflow-hidden rounded-lg bg-slate-100 border border-slate-100">
                {producto.imagen ? (
                    <img
                        src={`/storage/${producto.imagen}`}
                        alt={producto.nombre}
                        className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <Store className="h-10 w-10 text-slate-300" />
                    </div>
                )}
                {producto.is_service && (
                    <div className="absolute top-2 left-2">
                        <span className="inline-flex items-center gap-1 rounded-full bg-violet-600/90 px-2 py-0.5 text-[10px] font-bold text-white backdrop-blur-sm">
                            <Clock className="h-3 w-3" />
                            {producto.duracion} min
                        </span>
                    </div>
                )}
            </div>
            <div className="mt-3 flex-1">
                <h4 className="text-sm font-semibold text-slate-900 line-clamp-2 leading-tight">
                    {producto.nombre}
                </h4>
                <p className="mt-1 text-lg font-bold text-slate-900">
                    ${Number(producto.precio_venta).toLocaleString()}
                </p>
                {['kg', 'lt'].includes(producto.unidad_medida) && modoGramosProducto[producto.id] && (
                    <p className="text-xs text-emerald-600 font-semibold">
                        = ${Math.round(
                            precioProporcional(
                                producto,
                                cantidadesAgregar[producto.id] || 0,
                            ) ?? 0,
                        ).toLocaleString()}
                    </p>
                )}
            </div>
            <div className="mt-3 pt-3 border-t border-slate-100 space-y-2">
                {producto.is_service && producto.requires_appointment ? (
                    <Link
                        href={`/booking/${store.slug}?service_id=${producto.id}`}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg h-9 text-sm font-semibold transition-all bg-primary text-primary-foreground hover:bg-primary/90"
                    >
                        <Calendar className="h-4 w-4" />
                        Agendar Cita
                    </Link>
                ) : (
                    <>
                        {['kg', 'lt'].includes(producto.unidad_medida) && (
                            <div className="flex items-center justify-end">
                                <button
                                    type="button"
                                    onClick={() => toggleModoGramos(producto.id)}
                                    className={`text-[10px] font-bold uppercase tracking-wider rounded-md px-2 py-0.5 transition-colors ${modoGramosProducto[producto.id] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
                                >
                                    {modoGramosProducto[producto.id]
                                        ? producto.unidad_medida === 'lt' ? 'ml' : 'g'
                                        : producto.unidad_medida === 'lt' ? 'L' : 'kg'}
                                </button>
                            </div>
                        )}
                        <div className="flex items-center justify-between rounded-lg bg-slate-100 p-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7 rounded-md hover:bg-slate-200"
                                onClick={() =>
                                    setCantidadesAgregar((prev) => ({
                                        ...prev,
                                        [producto.id]: Math.max(modoGramosProducto[producto.id] ? 1 : 1, (prev[producto.id] || 1) - (modoGramosProducto[producto.id] ? 10 : 1)),
                                    }))
                                }
                            >
                                <Minus className="h-3 w-3" />
                            </Button>
                            <div className="flex items-center">
                                <Input
                                    type="number"
                                    min="1"
                                    step={modoGramosProducto[producto.id] ? "10" : "1"}
                                    value={cantidadesAgregar[producto.id] || 1}
                                    onChange={(e) => {
                                        const val = parseInt(e.target.value) || 1;
                                        setCantidadesAgregar((prev) => ({
                                            ...prev,
                                            [producto.id]: Math.max(1, val),
                                        }));
                                    }}
                                    className="h-7 w-14 border-0 bg-transparent text-center text-sm font-semibold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                />
                                {modoGramosProducto[producto.id] && (
                                    <span className="text-[10px] font-bold text-slate-400 ml-0.5">
                                        {producto.unidad_medida === 'lt' ? 'ml' : 'g'}
                                    </span>
                                )}
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7 rounded-md hover:bg-slate-200"
                                onClick={() =>
                                    setCantidadesAgregar((prev) => ({
                                        ...prev,
                                        [producto.id]: (prev[producto.id] || 1) + (modoGramosProducto[producto.id] ? 10 : 1),
                                    }))
                                }
                            >
                                <Plus className="h-3 w-3" />
                            </Button>
                        </div>
                        <Button
                            className={`h-9 w-full rounded-lg text-sm font-semibold transition-colors ${productoAgregado === producto.id ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-primary hover:bg-primary/90 text-white'}`}
                            onClick={() => agregarAlCarrito(producto.id)}
                            disabled={productoAgregado === producto.id}
                        >
                            {productoAgregado === producto.id ? (
                                <>
                                    <Check className="mr-1.5 h-4 w-4" /> Listo
                                </>
                            ) : (
                                <>
                                    <ShoppingCart className="mr-1.5 h-4 w-4" /> Agregar
                                </>
                            )}
                        </Button>
                    </>
                )}
            </div>
        </div>
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Marketplace', href: '/tienda' },
                { title: store.title, href: `/tienda/${store.slug}` },
            ]}
        >
            <Head title={store.title} />

            {/* Facebook-style Header Area */}
            <div className="bg-white shadow-sm border-b border-slate-200">
                <div className="mx-auto max-w-6xl">
                    {/* Cover Photo */}
                    <div className="relative h-64 w-full bg-gradient-to-r from-slate-200 to-slate-300 sm:h-80 md:h-96 rounded-b-xl overflow-hidden group">
                        {store.user.cover_photo_url && (
                            <img
                                src={store.user.cover_photo_url}
                                className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                            />
                        )}
                        <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-80" />
                    </div>

                    {/* Profile Info Bar */}
                    <div className="relative px-4 sm:px-8 pb-4 flex flex-col sm:flex-row items-center sm:items-end sm:justify-between -mt-12 sm:-mt-16 mb-4">
                        <div className="flex flex-col sm:flex-row items-center sm:items-end gap-4 sm:gap-6 z-10 w-full">
                            {/* Avatar */}
                            <div className="relative h-32 w-32 shrink-0 overflow-hidden rounded-full border-4 border-white bg-white shadow-md sm:h-40 sm:w-40">
                                {store.user.profile_photo_url ? (
                                    <img
                                        src={store.user.profile_photo_url}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center bg-slate-100 text-5xl font-bold text-slate-300">
                                        {store.title.charAt(0)}
                                    </div>
                                )}
                                <div className="absolute bottom-2 right-2 h-4 w-4 rounded-full border-2 border-white bg-green-500"></div>
                            </div>

                            {/* Text Info */}
                            <div className="flex-1 text-center sm:text-left pb-2 sm:pb-4">
                                <h1 className="text-3xl font-bold tracking-tight text-slate-900 flex items-center justify-center sm:justify-start gap-2">
                                    {store.title}
                                    {store.is_verified && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span className="flex items-center gap-1 cursor-help" aria-label="Tienda verificada">
                                                    <CheckCircle2 className="h-5 w-5 text-blue-500 fill-blue-500" />
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" align="center" className="bg-blue-50 text-blue-700 border-blue-200 px-3 py-1.5 text-xs">
                                                <div className="flex items-center gap-1.5">
                                                    <CheckCircle2 className="h-3 w-3 text-blue-500 fill-blue-500" />
                                                    Tienda verificada
                                                </div>
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                    {store.is_official && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span className="flex items-center gap-1 cursor-help" aria-label="Tienda Oficial">
                                                    <Star className="h-5 w-5 text-amber-500 fill-amber-500" />
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" align="center" className="bg-amber-50 text-amber-700 border-amber-200 px-3 py-1.5 text-xs">
                                                <div className="flex items-center gap-1.5">
                                                    <Star className="h-3 w-3 text-amber-500 fill-amber-500" />
                                                    Tienda Oficial
                                                </div>
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </h1>
                                <p className="text-sm font-medium text-slate-500 mt-1 flex items-center justify-center sm:justify-start gap-4">
                                    <span className="flex items-center gap-1">
                                        <Star className="h-4 w-4 text-amber-400 fill-amber-400" />
                                        {averageRating} ({store.rating_count} opiniones)
                                    </span>
                                    <span>•</span>
                                    <span>{store.likes_count} me gusta</span>
                                </p>
                            </div>

                            {/* Actions */}
                            <div className="flex gap-2 pb-2 sm:pb-4 w-full sm:w-auto justify-center shrink-0">
                                <Button
                                    variant={localLike ? "default" : "secondary"}
                                    className={`h-10 rounded-lg px-4 font-semibold ${localLike ? 'bg-primary text-white hover:bg-primary/90' : 'bg-slate-200 text-slate-900 hover:bg-slate-300'}`}
                                    onClick={handleLike}
                                >
                                    <Heart className={cn("mr-2 h-4 w-4", localLike ? "fill-white" : "")} />
                                    {localLike ? 'Te gusta' : 'Me gusta'}
                                </Button>
                                <Link
                                    href={chat.start(store.slug).url}
                                    className="inline-flex h-10 items-center justify-center rounded-lg px-4 font-semibold bg-slate-200 text-slate-900 hover:bg-slate-300"
                                >
                                    <MessageSquare className="mr-2 h-4 w-4" />
                                    Mensaje
                                </Link>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="secondary"
                                            className="h-10 rounded-lg px-4 font-semibold bg-slate-200 text-slate-900 hover:bg-slate-300"
                                        >
                                            <Share2 className="mr-2 h-4 w-4" />
                                            Compartir
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-52">
                                        <DropdownMenuItem onClick={() => handleSocialShare('whatsapp')}>
                                            <MessageCircle className="mr-2 h-4 w-4 text-green-500" />
                                            WhatsApp
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleSocialShare('facebook')}>
                                            <Facebook className="mr-2 h-4 w-4 text-blue-600" />
                                            Facebook
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleSocialShare('copy')}>
                                            <Copy className="mr-2 h-4 w-4" />
                                            Copiar enlace
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                    
                    {/* Navigation Tabs */}
                    <div className="flex px-4 sm:px-8 border-t border-slate-200 pt-1">
                        <button
                            onClick={() => setTabActivo('productos')}
                            className={`px-4 py-3 text-sm font-bold transition-colors rounded-t-lg ${tabActivo === 'productos' ? 'text-primary border-b-4 border-primary' : 'text-slate-500 hover:bg-slate-50'}`}
                        >
                            Productos
                        </button>
                        <button
                            onClick={() => setTabActivo('informacion')}
                            className={`px-4 py-3 text-sm font-bold transition-colors rounded-t-lg ${tabActivo === 'informacion' ? 'text-primary border-b-4 border-primary' : 'text-slate-500 hover:bg-slate-50'}`}
                        >
                            Información
                        </button>
                        <button
                            onClick={() => setTabActivo('opiniones')}
                            className={`px-4 py-3 text-sm font-bold transition-colors rounded-t-lg ${tabActivo === 'opiniones' ? 'text-primary border-b-4 border-primary' : 'text-slate-500 hover:bg-slate-50'}`}
                        >
                            Opiniones
                            {reviews.length > 0 && (
                                <span className="ml-1.5 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] text-primary">
                                    {reviews.length}
                                </span>
                            )}
                        </button>
                    </div>
                </div>
            </div>

            {/* Main Content Area - Gray Background like Facebook */}
            <div className="bg-[#f0f2f5] min-h-screen pt-6 pb-12">
                <div className="mx-auto max-w-6xl px-4 flex flex-col lg:flex-row gap-6">
                    
                    {/* Left Column (Intro & Categories) */}
                    <div className="w-full lg:w-[360px] flex-shrink-0 space-y-4">
                        {/* Intro Card */}
                        <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                            <h2 className="text-lg font-bold text-slate-900 mb-4">Detalles</h2>
                            <div className="space-y-4">
                                {store.description && (
                                    <div className="text-sm text-slate-800 text-center pb-4 border-b border-slate-100">
                                        "{store.description}"
                                    </div>
                                )}
                                
                                {store.user.region && (
                                    <div className="flex items-center gap-3 text-sm text-slate-800">
                                        <MapPin className="h-5 w-5 text-slate-400" />
                                        <span>Vive en <span className="font-semibold">{store.user.comuna || store.user.region}</span></span>
                                    </div>
                                )}
                                
                                {/* Store Badges */}
                                <div className="flex items-center gap-2 flex-wrap mt-2">
                                    {store.is_verified && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span className="flex items-center gap-1 cursor-help" aria-label="Tienda verificada">
                                                    <CheckCircle2 className="h-4 w-4 text-blue-500 fill-blue-500" />
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" align="center" className="bg-blue-50 text-blue-700 border-blue-200 px-3 py-1.5 text-xs">
                                                Tienda verificada por la plataforma
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                    {store.is_official && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span className="flex items-center gap-1 cursor-help" aria-label="Tienda Oficial">
                                                    <Star className="h-4 w-4 text-amber-500 fill-amber-500" />
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" align="center" className="bg-amber-50 text-amber-700 border-amber-200 px-3 py-1.5 text-xs">
                                                Tienda Oficial verificada
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </div>
                                
                                {store.email && (
                                    <div className="flex items-center gap-3 text-sm text-slate-800">
                                        <Mail className="h-5 w-5 text-slate-400" />
                                        <span>{store.email}</span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Categories Card */}
                        <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                            <h2 className="text-lg font-bold text-slate-900 mb-4 flex items-center justify-between">
                                Categorías
                                <span className="text-sm font-normal text-primary hover:underline cursor-pointer">Ver todas</span>
                            </h2>
                            <div className="grid grid-cols-2 gap-2">
                                {store.categorias.map((cat) => (
                                    <Link
                                        key={cat.id}
                                        href={`/tienda/${store.slug}/categoria/${cat.id}`}
                                        className="relative group rounded-lg overflow-hidden border border-slate-100 aspect-square"
                                    >
                                        <img
                                            src={cat.imagen ? `/storage/${cat.imagen}` : '/placeholder.png'}
                                            className="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        />
                                        <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                                        <span className="absolute bottom-2 left-2 right-2 text-xs font-bold text-white truncate">
                                            {cat.nombre}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Middle Column */}
                    <div className="flex-1 space-y-4">
                        {tabActivo === 'productos' && (
                            <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                                <h2 className="text-lg font-bold text-slate-900 mb-4">Catálogo de Productos</h2>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {store.productos.map((p) => (
                                        <ProductCard key={p.id} producto={p} />
                                    ))}
                                </div>
                            </div>
                        )}

                        {tabActivo === 'informacion' && (
                            <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                                <h2 className="text-lg font-bold text-slate-900 mb-4">Información de la Tienda</h2>
                                <div className="space-y-4">
                                    {store.description && (
                                        <div className="text-sm text-slate-800">
                                            <span className="font-semibold">Descripción:</span>
                                            <p className="mt-1 text-slate-600">{store.description}</p>
                                        </div>
                                    )}
                                    {store.user.region && (
                                        <div className="flex items-center gap-3 text-sm">
                                            <MapPin className="h-5 w-5 text-slate-400" />
                                            <span><span className="font-semibold">Ubicación:</span> {store.user.comuna || store.user.region}</span>
                                        </div>
                                    )}
                                    {store.email && (
                                        <div className="flex items-center gap-3 text-sm">
                                            <Mail className="h-5 w-5 text-slate-400" />
                                            <span><span className="font-semibold">Email:</span> {store.email}</span>
                                        </div>
                                    )}
                                    {store.phone && (
                                        <div className="flex items-center gap-3 text-sm">
                                            <Phone className="h-5 w-5 text-slate-400" />
                                            <span><span className="font-semibold">Teléfono:</span> {store.phone}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {tabActivo === 'opiniones' && (
                            <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                                <h2 className="text-lg font-bold text-slate-900 mb-4 flex items-center justify-between">
                                    Opiniones de Clientes
                                    <span className="text-sm font-normal text-slate-500">{reviews.length} {reviews.length === 1 ? 'opinión' : 'opiniones'}</span>
                                </h2>

                                {reviews.length === 0 && (
                                    <div className="text-center py-8 text-slate-400">
                                        <MessageSquare className="h-10 w-10 mx-auto mb-3" />
                                        <p className="font-medium">No hay opiniones aún</p>
                                        <p className="text-sm mt-1">Sé el primero en opinar</p>
                                    </div>
                                )}

                                <div className="space-y-4">
                                    {reviews.map((review) => (
                                        <div key={review.id} className="flex gap-3 border-b border-slate-100 pb-4 last:border-0">
                                            <div className="h-10 w-10 rounded-full bg-slate-200 overflow-hidden shrink-0">
                                                {review.user.profile_photo_url ? (
                                                    <img src={review.user.profile_photo_url} className="h-full w-full object-cover" />
                                                ) : (
                                                    <div className="h-full w-full flex items-center justify-center text-sm font-bold text-slate-500">
                                                        {review.user.name.charAt(0)}
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-bold text-sm text-slate-900">{review.user.name}</span>
                                                    <span className="text-[10px] text-slate-400">
                                                        {new Date(review.created_at).toLocaleDateString(currency.locale, { year: 'numeric', month: 'short', day: 'numeric' })}
                                                    </span>
                                                </div>
                                                <p className="text-sm text-slate-700 mt-1">{review.comentario}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {auth.user && hasPurchased && !userReview && (
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            if (!opinionTexto.trim()) return;
                                            setEnviandoOpinion(true);
                                            router.post(`/tienda/${store.slug}/opinion`, { comentario: opinionTexto }, {
                                                onSuccess: () => {
                                                    setOpinionTexto('');
                                                    setEnviandoOpinion(false);
                                                },
                                                onError: () => {
                                                    setEnviandoOpinion(false);
                                                },
                                            });
                                        }}
                                        className="mt-6 border-t border-slate-100 pt-4"
                                    >
                                        <h3 className="font-bold text-sm text-slate-900 mb-3">Deja tu opinión</h3>
                                        <textarea
                                            value={opinionTexto}
                                            onChange={(e) => setOpinionTexto(e.target.value)}
                                            placeholder="Escribe tu opinión sobre esta tienda..."
                                            className="w-full rounded-lg border border-slate-200 p-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                            rows={3}
                                            maxLength={1000}
                                        />
                                        <div className="flex items-center justify-between mt-2">
                                            <span className="text-[10px] text-slate-400">{opinionTexto.length}/1000</span>
                                            <Button
                                                type="submit"
                                                disabled={!opinionTexto.trim() || enviandoOpinion}
                                                className="h-9 rounded-lg text-sm font-semibold"
                                            >
                                                {enviandoOpinion ? 'Publicando...' : 'Publicar Opinión'}
                                            </Button>
                                        </div>
                                    </form>
                                )}

                                {auth.user && hasPurchased && userReview && (
                                    <div className="mt-6 border-t border-slate-100 pt-4 bg-primary/5 rounded-xl p-4">
                                        <p className="text-sm font-semibold text-primary">Ya publicaste tu opinión</p>
                                        <p className="text-sm text-slate-600 mt-1">"{userReview.comentario}"</p>
                                    </div>
                                )}

                                {!auth.user && (
                                    <div className="mt-6 border-t border-slate-100 pt-4 text-center">
                                        <Link href="/login" className="text-sm font-semibold text-primary hover:underline">
                                            Inicia sesión para dejar una opinión
                                        </Link>
                                    </div>
                                )}

                                {auth.user && !hasPurchased && (
                                    <div className="mt-6 border-t border-slate-100 pt-4">
                                        <p className="text-sm text-slate-500 text-center">
                                            Solo los clientes que han comprado en esta tienda pueden dejar una opinión.
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Right Column (Cart Widget) */}
                    <div className="w-full lg:w-[320px] flex-shrink-0">
                        <div className="sticky top-20 bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                            <h2 className="text-lg font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Tu Pedido</h2>
                            <div className="space-y-4 max-h-[40vh] overflow-y-auto mb-4 custom-scrollbar">
                                {productosEnCarrito.map((p) => (
                                    <div key={p.id} className="flex items-center gap-3">
                                        <div className="h-12 w-12 rounded-lg bg-slate-100 p-1 flex-shrink-0 border border-slate-200">
                                            <img src={`/storage/${p.imagen}`} className="h-full w-full object-contain" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-semibold text-slate-900 truncate">{p.nombre}</p>
                                            <p className="text-xs text-slate-500">
                                                {['kg', 'lt'].includes(p.unidad_medida) && modoGramosProducto[p.id]
                                                    ? `${(carrito[p.id] * 1000).toLocaleString()} ${p.unidad_medida === 'lt' ? 'ml' : 'g'}`
                                                    : carrito[p.id]} x ${p.precio_venta.toLocaleString()}
                                            </p>
                                            {['kg', 'lt'].includes(p.unidad_medida) && modoGramosProducto[p.id] && (
                                                <p className="text-[10px] text-emerald-600 font-bold">
                                                    ≈ ${Math.round(
                                                        precioProporcional(p, carrito[p.id] * 1000) ?? 0,
                                                    ).toLocaleString()}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                {totalItems === 0 && (
                                    <div className="text-center py-6">
                                        <ShoppingCart className="h-10 w-10 text-slate-300 mx-auto mb-2" />
                                        <p className="text-sm font-medium text-slate-500">Tu carrito está vacío</p>
                                    </div>
                                )}
                            </div>
                            
                            {totalItems > 0 && (
                                <div className="border-t border-slate-100 pt-4">
                                    <div className="flex justify-between items-center mb-4">
                                        <span className="font-bold text-slate-700">Total</span>
                                        <span className="text-lg font-black text-primary">${totalCarrito.toLocaleString()}</span>
                                    </div>
                                    
                                    <Sheet open={carritoAbierto} onOpenChange={setCarritoAbierto}>
                                        <SheetTrigger asChild>
                                            <Button className="w-full h-10 rounded-lg font-bold text-sm bg-primary hover:bg-primary/90 text-white shadow-sm">
                                                Revisar y Pagar
                                            </Button>
                                        </SheetTrigger>
                                        <SheetContent className="rounded-l-[3rem] border-l-0 bg-white sm:max-w-md">
                                        <SheetHeader className="border-b pb-8">
                                            <SheetTitle className="text-3xl font-black tracking-tighter uppercase">
                                                Mi Compra
                                            </SheetTitle>
                                        </SheetHeader>
                                        <div className="flex-1 overflow-y-auto py-8">
                                            {productosEnCarrito.map((p) => (
                                                <div
                                                    key={p.id}
                                                    className="mb-6 flex gap-6 rounded-3xl border border-slate-100 bg-slate-50 p-4 dark:bg-slate-900"
                                                >
                                                    <div className="h-24 w-24 overflow-hidden rounded-2xl bg-white p-3">
                                                        <img
                                                            src={`/storage/${p.imagen}`}
                                                            className="h-full w-full object-contain"
                                                        />
                                                    </div>
                                                    <div className="flex flex-1 flex-col justify-center">
                                                        <p className="mb-2 text-xs font-black uppercase">
                                                            {p.nombre}
                                                        </p>
                                                        {['kg', 'lt'].includes(p.unidad_medida) && modoGramosProducto[p.id] && (
                                                            <p className="text-[10px] text-emerald-600 font-bold mb-1">
                                                                ≈ ${Math.round(
                                                                    p.precio_venta * carrito[p.id],
                                                                ).toLocaleString()}
                                                                &nbsp;({(carrito[p.id] * 1000).toLocaleString()}&nbsp;{p.unidad_medida === 'lt' ? 'ml' : 'g'})
                                                            </p>
                                                        )}
                                                        <div className="flex items-center gap-3">
                                                            <Button
                                                                variant="outline"
                                                                size="icon"
                                                                className="h-8 w-8 rounded-xl"
                                                                    onClick={() =>
                                                                        setCarrito(
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                [p.id]: Math.max(
                                                                                    0,
                                                                                    (prev[
                                                                                        p
                                                                                            .id
                                                                                    ] ||
                                                                                        0) -
                                                                                        (['kg', 'lt'].includes(p.unidad_medida) && modoGramosProducto[p.id] ? 0.1 : 1),
                                                                                ),
                                                                            }),
                                                                        )
                                                                    }
                                                                >
                                                                    <Minus className="h-3 w-3" />
                                                                </Button>
                                                                <Input
                                                                type="number"
                                                                min="0"
                                                                step={['kg', 'lt'].includes(p.unidad_medida) && modoGramosProducto[p.id] ? "0.001" : "1"}
                                                                value={['kg', 'lt'].includes(p.unidad_medida) && modoGramosProducto[p.id] ? (carrito[p.id] * 1000) : carrito[p.id]}
                                                                onChange={(e) => {
                                                                    const val = ['kg', 'lt'].includes(p.unidad_medida) && modoGramosProducto[p.id]
                                                                        ? (parseFloat(e.target.value) || 0) / 1000
                                                                        : parseInt(e.target.value) || 0;
                                                                    setCarrito((prev) => ({
                                                                        ...prev,
                                                                        [p.id]: Math.max(0, val),
                                                                    }));
                                                                }}
                                                                className="h-8 w-16 border-0 bg-transparent text-center font-black [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                            />
                                                            <Button
                                                                variant="outline"
                                                                size="icon"
                                                                className="h-8 w-8 rounded-xl"
                                                                    onClick={() =>
                                                                        setCarrito(
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                [p.id]:
                                                                                    (prev[
                                                                                        p
                                                                                            .id
                                                                                    ] ||
                                                                                        0) +
                                                                                    (['kg', 'lt'].includes(p.unidad_medida) && modoGramosProducto[p.id] ? 0.1 : 1),
                                                                            }),
                                                                        )
                                                                    }
                                                                >
                                                                    <Plus className="h-3 w-3" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="space-y-6 border-t p-8">
                                            <div className="flex items-center justify-between">
                                                <span className="font-black text-slate-400 uppercase">
                                                    Total a pagar
                                                </span>
                                                <span className="text-3xl font-black">
                                                    $
                                                    {totalCarrito.toLocaleString()}
                                                </span>
                                            </div>
                                            <Dialog
                                                open={checkoutAbierto}
                                                onOpenChange={
                                                    setCheckoutAbierto
                                                }
                                            >
                                                <DialogTrigger asChild>
                                                    <Button className="h-16 w-full rounded-[2rem] text-lg font-black tracking-widest uppercase shadow-2xl shadow-primary/30">
                                                        Pedir Ahora
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent className="max-h-[90vh] overflow-y-auto rounded-[3rem] p-6">
                                                    <DialogHeader>
                                                        <DialogTitle className="text-2xl font-black tracking-tighter uppercase">
                                                            Datos de Envío
                                                        </DialogTitle>
                                                    </DialogHeader>
                                                    <div className="mt-4 space-y-5">
                                                        {/* DatosPersonales */}
                                                        <div className="space-y-3">
                                                            <Label className="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                                                Datos Personales
                                                            </Label>
                                                            <FormInput
                                                                id="nombre_checkout"
                                                                label="NOMBRE COMPLETO"
                                                                value={
                                                                    datosCheckout.nombre_cliente
                                                                }
                                                                onChange={(e) =>
                                                                    setDatosCheckout(
                                                                        {
                                                                            ...datosCheckout,
                                                                            nombre_cliente:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="Ej: Juan Perez"
                                                            />
                                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                                 <FormInput
                                                                     id="rut_checkout"
                                                                     label="RUT (Opcional)"
                                                                     value={
                                                                         datosCheckout.rut_cliente
                                                                     }
                                                                     onChange={(
                                                                         e,
                                                                     ) =>
                                                                         setDatosCheckout(
                                                                             {
                                                                                 ...datosCheckout,
                                                                                 rut_cliente:
                                                                                     e
                                                                                         .target
                                                                                         .value,
                                                                             },
                                                                         )
                                                                     }
                                                                     onBlur={(e) =>
                                                                         setDatosCheckout(
                                                                             {
                                                                                 ...datosCheckout,
                                                                                 rut_cliente:
                                                                                     formatRut(e.target.value),
                                                                             },
                                                                         )
                                                                     }
                                                                     placeholder="Ej: 12345678-9"
                                                                 />
                                                                <FormInput
                                                                    id="email_checkout"
                                                                    label="CORREO ELECTRÓNICO"
                                                                    value={
                                                                        datosCheckout.email_cliente
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setDatosCheckout(
                                                                            {
                                                                                ...datosCheckout,
                                                                                email_cliente:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    placeholder="correo@ejemplo.cl"
                                                                />
                                                            </div>
                                                            <FormInput
                                                                id="telefono_checkout"
                                                                label="TELÉFONO DE CONTACTO"
                                                                value={
                                                                    datosCheckout.telefono_cliente
                                                                }
                                                                onChange={(e) =>
                                                                    setDatosCheckout(
                                                                        {
                                                                            ...datosCheckout,
                                                                            telefono_cliente:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="Ej: +56 9 1234 5678"
                                                            />
                                                        </div>

                                                        {/* Dirección Chile */}
                                                        <div className="space-y-3 pt-2">
                                                            <Label className="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                                                Dirección de
                                                                Entrega
                                                            </Label>
                                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                                <div className="space-y-2">
                                                                    <Label className="text-xs font-bold text-slate-600">
                                                                        REGIÓN *
                                                                    </Label>
                                                                    <Select
                                                                        value={
                                                                            datosCheckout.region
                                                                        }
                                                                        onValueChange={(
                                                                            v,
                                                                        ) =>
                                                                            setDatosCheckout(
                                                                                {
                                                                                    ...datosCheckout,
                                                                                    region: v,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <SelectTrigger className="h-11 rounded-xl">
                                                                            <SelectValue placeholder="Seleccionar región" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            <SelectItem value="arica">
                                                                                Arica
                                                                                y
                                                                                Parinacota
                                                                            </SelectItem>
                                                                            <SelectItem value="tarapaca">
                                                                                Tarapacá
                                                                            </SelectItem>
                                                                            <SelectItem value="antofagasta">
                                                                                Antofagasta
                                                                            </SelectItem>
                                                                            <SelectItem value="atacama">
                                                                                Atacama
                                                                            </SelectItem>
                                                                            <SelectItem value="coquimbo">
                                                                                Coquimbo
                                                                            </SelectItem>
                                                                            <SelectItem value="valparaiso">
                                                                                Valparaíso
                                                                            </SelectItem>
                                                                            <SelectItem value="metropolitana">
                                                                                Metropolitana
                                                                            </SelectItem>
                                                                            <SelectItem value="ohiggins">
                                                                                O'Higgins
                                                                            </SelectItem>
                                                                            <SelectItem value="maule">
                                                                                Maule
                                                                            </SelectItem>
                                                                            <SelectItem value="biobio">
                                                                                Biobío
                                                                            </SelectItem>
                                                                            <SelectItem value="araucania">
                                                                                La
                                                                                Araucanía
                                                                            </SelectItem>
                                                                            <SelectItem value="losrios">
                                                                                Los
                                                                                Ríos
                                                                            </SelectItem>
                                                                            <SelectItem value="loslagos">
                                                                                Los
                                                                                Lagos
                                                                            </SelectItem>
                                                                            <SelectItem value="aysen">
                                                                                Aysén
                                                                            </SelectItem>
                                                                            <SelectItem value="magallanes">
                                                                                Magallanes
                                                                            </SelectItem>
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                                <div className="space-y-2">
                                                                    <Label className="text-xs font-bold text-slate-600">
                                                                        COMUNA *
                                                                    </Label>
                                                                    <Select
                                                                        value={
                                                                            datosCheckout.comuna
                                                                        }
                                                                        onValueChange={(
                                                                            v,
                                                                        ) =>
                                                                            setDatosCheckout(
                                                                                {
                                                                                    ...datosCheckout,
                                                                                    comuna: v,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <SelectTrigger className="h-11 rounded-xl">
                                                                            <SelectValue placeholder="Seleccionar comuna" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            <SelectItem value="santiago">
                                                                                Santiago
                                                                            </SelectItem>
                                                                            <SelectItem value="providencia">
                                                                                Providencia
                                                                            </SelectItem>
                                                                            <SelectItem value="las_condes">
                                                                                Las
                                                                                Condes
                                                                            </SelectItem>
                                                                            <SelectItem value="nuñoa">
                                                                                Ñuñoa
                                                                            </SelectItem>
                                                                            <SelectItem value="vitacura">
                                                                                Vitacura
                                                                            </SelectItem>
                                                                            <SelectItem value="maipu">
                                                                                Maipú
                                                                            </SelectItem>
                                                                            <SelectItem value="la_florida">
                                                                                La
                                                                                Florida
                                                                            </SelectItem>
                                                                            <SelectItem value="san_bernardo">
                                                                                San
                                                                                Bernardo
                                                                            </SelectItem>
                                                                            <SelectItem value="puente_alto">
                                                                                Puente
                                                                                Alto
                                                                            </SelectItem>
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                            </div>
                                                            <FormInput
                                                                id="direccion_checkout"
                                                                label="DIRECCIÓN (Calle)"
                                                                value={
                                                                    datosCheckout.direccion_cliente
                                                                }
                                                                onChange={(e) =>
                                                                    setDatosCheckout(
                                                                        {
                                                                            ...datosCheckout,
                                                                            direccion_cliente:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="Ej: Av. Libertador Bernardo O'Higgins"
                                                            />
                                                            <div className="grid grid-cols-2 gap-4">
                                                                <FormInput
                                                                    id="numero_checkout"
                                                                    label="NÚMERO"
                                                                    value={
                                                                        datosCheckout.numero_direccion
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setDatosCheckout(
                                                                            {
                                                                                ...datosCheckout,
                                                                                numero_direccion:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    placeholder="Ej: 1234"
                                                                />
                                                                <FormInput
                                                                    id="depto_checkout"
                                                                    label="Depto/Casa (Opcional)"
                                                                    value={
                                                                        datosCheckout.depto_casa
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setDatosCheckout(
                                                                            {
                                                                                ...datosCheckout,
                                                                                depto_casa:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    placeholder="Ej: Depto 501"
                                                                />
                                                            </div>
                                                        </div>

                                                        <div className="space-y-3 pt-2">
                                                            <Label className="text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                                                Método de Pago
                                                            </Label>
                                                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        setDatosCheckout(
                                                                            {
                                                                                ...datosCheckout,
                                                                                metodo_pago:
                                                                                    'efectivo',
                                                                            },
                                                                        )
                                                                    }
                                                                    className={`flex flex-col items-center justify-center rounded-2xl border p-3 transition-all ${datosCheckout.metodo_pago === 'efectivo' ? 'border-primary bg-primary/5 text-primary shadow-sm' : 'border-slate-100 text-slate-400 hover:border-slate-200 hover:bg-slate-50'}`}
                                                                >
                                                                    <Wallet className="mb-2 h-5 w-5" />
                                                                    <span className="text-[10px] font-black tracking-tight uppercase">
                                                                        Local
                                                                    </span>
                                                                </button>

                                                                {paymentConfig?.is_active && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setDatosCheckout(
                                                                                {
                                                                                    ...datosCheckout,
                                                                                    metodo_pago:
                                                                                        'webpay',
                                                                                },
                                                                            )
                                                                        }
                                                                        className={`flex flex-col items-center justify-center rounded-2xl border p-3 transition-all ${datosCheckout.metodo_pago === 'webpay' ? 'border-indigo-500 bg-indigo-50 text-indigo-600 shadow-sm' : 'border-slate-100 text-slate-400 hover:border-slate-200 hover:bg-slate-50'}`}
                                                                    >
                                                                        <CreditCard className="mb-2 h-5 w-5" />
                                                                        <span className="text-[10px] font-black tracking-tight uppercase">
                                                                            Webpay
                                                                        </span>
                                                                    </button>
                                                                )}

                                                                {paymentConfig?.paypal_active && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setDatosCheckout(
                                                                                {
                                                                                    ...datosCheckout,
                                                                                    metodo_pago:
                                                                                        'paypal',
                                                                                },
                                                                            )
                                                                        }
                                                                        className={`flex flex-col items-center justify-center rounded-2xl border p-3 transition-all ${datosCheckout.metodo_pago === 'paypal' ? 'border-blue-500 bg-blue-50 text-blue-600 shadow-sm' : 'border-slate-100 text-slate-400 hover:border-slate-200 hover:bg-slate-50'}`}
                                                                    >
                                                                        <CreditCard className="mb-2 h-5 w-5" />
                                                                        <span className="text-[10px] font-black tracking-tight uppercase">
                                                                            PayPal
                                                                        </span>
                                                                    </button>
                                                                )}

                                                                {paymentConfig?.mercadopago_active && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setDatosCheckout(
                                                                                {
                                                                                    ...datosCheckout,
                                                                                    metodo_pago:
                                                                                        'mercadopago',
                                                                                },
                                                                            )
                                                                        }
                                                                        className={`flex flex-col items-center justify-center rounded-2xl border p-3 transition-all ${datosCheckout.metodo_pago === 'mercadopago' ? 'border-sky-500 bg-sky-50 text-sky-600 shadow-sm' : 'border-slate-100 text-slate-400 hover:border-slate-200 hover:bg-slate-50'}`}
                                                                    >
                                                                        <CreditCard className="mb-2 h-5 w-5" />
                                                                        <span className="text-[10px] font-black tracking-tight uppercase">
                                                                            M.
                                                                            Pago
                                                                        </span>
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </div>

                                                        <Button
                                                            onClick={
                                                                enviarCheckout
                                                            }
                                                            disabled={
                                                                procesando
                                                            }
                                                            className={cn(
                                                                'h-16 w-full rounded-2xl font-black tracking-[0.2em] uppercase transition-all hover:scale-[1.02]',
                                                                datosCheckout.metodo_pago ===
                                                                    'webpay' &&
                                                                    'bg-indigo-600 shadow-indigo-500/25 hover:bg-indigo-700',
                                                                datosCheckout.metodo_pago ===
                                                                    'paypal' &&
                                                                    'bg-blue-600 shadow-blue-500/25 hover:bg-blue-700',
                                                                datosCheckout.metodo_pago ===
                                                                    'mercadopago' &&
                                                                    'bg-sky-600 shadow-sky-500/25 hover:bg-sky-700',
                                                            )}
                                                        >
                                                            {procesando
                                                                ? 'Enviando...'
                                                                : datosCheckout.metodo_pago ===
                                                                    'efectivo'
                                                                  ? 'Confirmar Pedido'
                                                                  : `Pagar con ${datosCheckout.metodo_pago.charAt(0).toUpperCase() + datosCheckout.metodo_pago.slice(1)}`}
                                                        </Button>
                                                    </div>
                                                </DialogContent>
                                            </Dialog>
                                        </div>
                                    </SheetContent>
                                    </Sheet>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

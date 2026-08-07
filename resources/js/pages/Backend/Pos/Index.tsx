import { Head, router, useForm } from '@inertiajs/react';
import {
    LayoutGrid,
    List,
    Barcode,
    Search,
    Trash2,
    ShoppingCart,
    Plus,
    Minus,
    CreditCard,
    Banknote,
    User as UserIcon,
    FileText,
    Receipt,
    Package,
    Wallet,
    Ticket,
    Building2,
    MessageCircle,
    CheckCircle2,
    AlertTriangle,
    RefreshCw,
    ArrowLeft,
} from 'lucide-react';
import React, { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';

interface Sku {
    id: number;
    sku: string;
    precio_venta: number;
    stock: number;
    variantes: { variante: string; valor: string }[];
}

interface Producto {
    id: number;
    nombre: string;
    descripcion: string | null;
    codigo: string | null;
    precio_venta: number;
    precio_con_variantes: number;
    stock: number;
    stock_minimo: number;
    unidad_medida?: 'unidad' | 'kg' | 'lt';
    peso_base: number;
    contenido_por_unidad: number;
    medida_pesable: boolean;
    imagen: string | null;
    tiene_variantes: boolean;
    skus: Sku[];
    envase_retornable?: boolean;
    envase_producto_id?: number;
    envase_precio?: number;
    inventarios?: Array<{
        almacen_id: number;
        almacen_nombre?: string;
        cantidad: number;
        cantidad_minima: number;
    }>;
}

interface Cliente {
    id: number;
    nombre: string;
    rut: string | null;
}

interface Almacen {
    id: number;
    nombre: string;
}

interface Promocion {
    id: number;
    nombre: string;
    tipo: 'porcentaje' | 'precio_fijo' | 'combo_2x1';
    valor: number;
    skus: string[] | null;
    categoria_id: number | null;
    compra_minima: number;
}

interface CartItem {
    cartId: string;
    productoId: number;
    skuId: number | null;
    nombre: string;
    sku: string | null;
    variantes: string | null;
    precio_venta: number;
    cantidad: number;
    stock: number;
    unidad_medida?: string;
    peso_base?: number;
    contenido_por_unidad?: number;
    medida_pesable?: boolean;
    modoGramos?: boolean;
    envase_retornable?: boolean;
    envase_precio?: number;
    cantidad_retornada?: number;
}

export default function PosIndex({
    productos,
    clientes,
    almacenes = [],
    promociones = [],
    iva_tasa = 0.19,
}: {
    productos: Producto[];
    clientes: Cliente[];
    almacenes?: Almacen[];
    promociones?: Promocion[];
    iva_tasa?: number;
}) {
    const [cart, setCart] = useState<CartItem[]>(() => {
        try {
            const saved = localStorage.getItem('pos_cart');
            return saved ? (JSON.parse(saved) as CartItem[]) : [];
        } catch {
            return [];
        }
    });
    const [busqueda, setBusqueda] = useState('');
    const [scannedCode, setScannedCode] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);
    const [procesando, setProcesando] = useState(false);
    const [productoSeleccionado, setProductoSeleccionado] =
        useState<Producto | null>(null);
    const [skuSeleccionado, setSkuSeleccionado] = useState<Sku | null>(null);
    const [ultimaVenta, setUltimaVenta] = useState<{
        ventaId: number | null;
        numero: string;
        items: CartItem[];
        subtotal: number;
        descuento: number;
        iva: number;
        total: number;
        metodoPago: string;
        tipoDocumento: string;
        cupon?: {
            codigo: string;
            nombre: string;
            descuento: number;
        } | null;
    } | null>(null);
    const { hasPermission } = usePermissions();
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const canCreate = hasPermission('ventas.pos.create');
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const canEdit = hasPermission('ventas.pos.edit');
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const canDelete = hasPermission('ventas.pos.delete');
    const [emitirDteOpen, setEmitirDteOpen] = useState(false);
    const [emitiendoDte, setEmitiendoDte] = useState(false);
    const [dteResultado, setDteResultado] = useState<{
        success: boolean;
        message: string;
        folio?: number;
        estado?: string;
    } | null>(null);

    const [clienteId, setClienteId] = useState<string | undefined>(undefined);
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [metodoPago, setMetodoPago] = useState<
        | 'efectivo'
        | 'tarjeta'
        | 'transferencia'
        | 'vale'
        | 'visa_transbank'
        | 'binance'
        | 'contactar'
    >('efectivo');
    const [tipoDocumento, setTipoDocumento] = useState<'boleta' | 'factura'>(
        'boleta',
    );
    const [almacenId, setAlmacenId] = useState<string>(
        almacenes.length > 0 ? String(almacenes[0].id) : '',
    );
    const [codigoCupon, setCodigoCupon] = useState('');
    const [cuponValidando, setCuponValidando] = useState(false);
    const [cuponValido, setCuponValido] = useState<boolean | null>(null);
    const [cuponMensaje, setCuponMensaje] = useState('');
    const [cuponDescuento, setCuponDescuento] = useState(0);
    const [cuponAplicado, setCuponAplicado] = useState(false);
    const [descuentoManualActivo, setDescuentoManualActivo] = useState(false);
    const [tipoDescuentoManual, setTipoDescuentoManual] = useState<'fijo' | 'porcentaje'>('porcentaje');
    const [valorDescuentoManual, setValorDescuentoManual] = useState(0);
    const [incluyeIva, setIncluyeIva] = useState(true);
    const [mostrarModalPagos, setMostrarModalPagos] = useState(false);
    const [mostrandoMetodosPago, setMostrandoMetodosPago] = useState(false);
    const [cartOpen, setCartOpen] = useState(false);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');

    const subtotal = useMemo(() => {
        return cart.reduce((acc, item) => {
            let envaseCost = 0;
            if (item.envase_retornable && item.envase_precio) {
                const pend = Math.max(0, item.cantidad - (item.cantidad_retornada || 0));
                envaseCost = pend * item.envase_precio;
            }
            return acc + (item.precio_venta * item.cantidad) + envaseCost;
        }, 0);
    }, [cart]);

    const validarCupon = useCallback(() => {
        if (!codigoCupon || codigoCupon.length < 3) {
            setCuponValido(null);
            setCuponMensaje('Ingrese un código de cupón válido.');
            setCuponDescuento(0);
            setCuponAplicado(false);
            return;
        }

        setCuponValidando(true);
        const monto = subtotal;

        const items = cart.map((item) => ({
            id: item.productoId,
            cantidad: item.cantidad,
            precio: item.precio_venta,
        }));

        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        fetch('/validar-cupon', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ codigo: codigoCupon, monto, items }),
        })
            .then(async (res) => {
                if (!res.ok) {
                    if (res.status === 419) throw new Error('Sesión expirada. Por favor recarga la página.');
                    if (res.status === 429) throw new Error('Demasiados intentos. Intenta en unos minutos.');
                    throw new Error('Error al validar el cupón.');
                }
                return res.json();
            })
            .then((data: { valido: boolean; descuento?: number; mensaje?: string; productos_ids?: number[]; productos_nombres?: string[] }) => {
                setCuponValido(data.valido);
                setCuponMensaje(data.mensaje ?? '');
                setCuponDescuento(data.valido ? (data.descuento ?? 0) : 0);
                setCuponAplicado(data.valido);
                if (data.valido) {
                    toast.success('Cupón aplicado correctamente');
                }
            })
            .catch((error) => {
                setCuponValido(null);
                setCuponMensaje(error instanceof Error ? error.message : 'Error al validar el cupón.');
                setCuponAplicado(false);
            })
            .finally(() => setCuponValidando(false));
    }, [codigoCupon, cart, subtotal]);

    const limpiarCupon = useCallback(() => {
        setCodigoCupon('');
        setCuponValido(null);
        setCuponMensaje('');
        setCuponDescuento(0);
        setCuponAplicado(false);
    }, []);

    const buscarProductoSku = useCallback((codigo: string) => {
        const codigoLimpio = codigo.trim();
        const producto = productos.find(
            (p) => p.codigo === codigoLimpio || String(p.id) === codigoLimpio,
        );
        if (producto) {
            if (producto.tiene_variantes && producto.skus.length > 0) {
                const sku =
                    producto.skus.find((s) => s.sku === codigoLimpio) ||
                    producto.skus[0];
                return { producto, sku };
            }
            return { producto, sku: null };
        }
        return null;
    }, [productos]);

    const agregarAlCarrito = useCallback((producto: Producto, sku: Sku | null = null) => {
        const precio = sku?.precio_venta ?? producto.precio_venta;
        const stock = sku?.stock ?? producto.stock;

        setCart((prev) => {
            const existingIndex = prev.findIndex(
                (item) =>
                    item.productoId === producto.id &&
                    item.skuId === (sku?.id ?? null),
            );
            if (existingIndex >= 0) {
                return prev.map((item, idx) =>
                    idx === existingIndex
                        ? {
                            ...item,
                            cantidad: parseFloat(
                                (item.cantidad + 1).toFixed(3),
                            ),
                            cantidad_retornada: item.envase_retornable
                                ? Math.min(Math.floor(item.cantidad + 1), (item.cantidad_retornada || 0) + 1)
                                : item.cantidad_retornada,
                        }
                        : item,
                );
            }
            const step =
                (producto.medida_pesable ?? false) &&
                    (producto.unidad_medida === 'kg' ||
                        producto.unidad_medida === 'lt')
                    ? 0.1
                    : 1;
            const skuDesc = sku
                ? sku.variantes
                    .map((v) => `${v.variante}: ${v.valor}`)
                    .join(', ')
                : null;
            return [
                ...prev,
                {
                    cartId: Math.random().toString(36).substr(2, 9),
                    productoId: producto.id,
                    skuId: sku?.id ?? null,
                    nombre: producto.nombre,
                    sku: sku?.sku ?? null,
                    variantes: skuDesc,
                    precio_venta: precio,
                    cantidad: step,
                    stock: stock,
                    unidad_medida: producto.unidad_medida,
                    peso_base: producto.peso_base ?? 0,
                    contenido_por_unidad: producto.contenido_por_unidad ?? 1,
                    medida_pesable: producto.medida_pesable,
                    modoGramos: false,
                    envase_retornable: (producto as any).envase_retornable,
                    envase_precio: (producto as any).envase_precio,
                    // Para cilindros de gas (retornables con unidad_medida='unidad' y medida_pesable=false),
                    // cantidad_retornada se inicializa igual a la cantidad del producto
                    // El operador puede ajustar manualmente si el cliente no devuelve todos los envases
                    cantidad_retornada: (producto as any).envase_retornable ? step : 0,
                },
            ];
        });
    }, []);

    const procesarCodigoBarra = useCallback((codigo: string) => {
        const resultado = buscarProductoSku(codigo);
        if (resultado) {
            const { producto, sku } = resultado;
            agregarAlCarrito(producto, sku);
            toast.success(
                `Agregado: ${producto.nombre}${sku ? ` (${sku.variantes.map((v) => v.valor).join(' - ')})` : ''}`,
            );
        } else {
            toast.error(`Producto no encontrado: ${codigo}`);
        }
        setScannedCode('');
        if (inputRef.current) inputRef.current.value = '';
    }, [buscarProductoSku, agregarAlCarrito]);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (
                e.target instanceof HTMLInputElement ||
                e.target instanceof HTMLTextAreaElement
            ) {
                if (
                    e.key === 'Enter' &&
                    e.target === inputRef.current &&
                    scannedCode.trim() !== ''
                ) {
                    procesarCodigoBarra(scannedCode);
                }
                return;
            }

            if (e.key === 'Enter') {
                if (scannedCode.trim() !== '') {
                    procesarCodigoBarra(scannedCode);
                }
            } else if (e.key.length === 1) {
                setScannedCode((prev) => prev + e.key);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [scannedCode, productos, procesarCodigoBarra]);

    useEffect(() => {
        localStorage.setItem('pos_cart', JSON.stringify(cart));
    }, [cart]);

    useEffect(() => {
        if (scannedCode) {
            const timeout = setTimeout(() => setScannedCode(''), 500);
            return () => clearTimeout(timeout);
        }
    }, [scannedCode]);

    const handleProductoClick = (producto: Producto) => {
        if (producto.tiene_variantes) {
            setProductoSeleccionado(producto);
            setSkuSeleccionado(null);
        } else {
            agregarAlCarrito(producto, null);
            toast.success(`Agregado: ${producto.nombre}`);
        }
    };

    const confirmarVariante = () => {
        if (productoSeleccionado && skuSeleccionado) {
            agregarAlCarrito(productoSeleccionado, skuSeleccionado);
            toast.success(
                `Agregado: ${productoSeleccionado.nombre} (${skuSeleccionado.variantes.map((v) => v.valor).join(' - ')})`,
            );
            setProductoSeleccionado(null);
            setSkuSeleccionado(null);
        }
    };

    const esGrameable = (item: CartItem) =>
        item.unidad_medida === 'kg' || item.unidad_medida === 'lt';

    const toggleModoGramos = (cartId: string) => {
        setCart((prev) =>
            prev.map((item) =>
                item.cartId === cartId
                    ? { ...item, modoGramos: !item.modoGramos }
                    : item,
            ),
        );
    };

    const actualizarCantidad = (cartId: string, delta: number) => {
        setCart((prev) =>
            prev.map((item) => {
                if (item.cartId === cartId) {
                    if (item.modoGramos) {
                        const gramos = Math.round(item.cantidad * 1000);
                        const nuevosGramos = Math.max(1, gramos + delta);
                        return {
                            ...item,
                            cantidad: parseFloat(
                                (nuevosGramos / 1000).toFixed(3),
                            ),
                        };
                    }
                    const step =
                        (item.medida_pesable ?? false) &&
                            (item.unidad_medida === 'kg' ||
                                item.unidad_medida === 'lt')
                            ? 0.1
                            : 1;
                    const finalDelta = delta > 0 ? step : -step;
                    const nuevaCantidad = Math.max(
                        step,
                        item.cantidad + finalDelta,
                    );
                    const maxRetornada = Math.floor(nuevaCantidad);
                    return {
                        ...item,
                        cantidad: parseFloat(nuevaCantidad.toFixed(3)),
                        cantidad_retornada: item.envase_retornable
                            ? Math.min(maxRetornada, (item.cantidad_retornada || 0) + (delta > 0 ? Math.abs(finalDelta) : 0))
                            : item.cantidad_retornada,
                    };
                }
                return item;
            }),
        );
    };

    const setCantidadItem = (cartId: string, value: number) => {
        setCart((prev) =>
            prev.map((item) => {
                if (item.cartId === cartId) {
                    if (item.modoGramos) {
                        const nuevosGramos = Math.max(1, value || 0);
                        return {
                            ...item,
                            cantidad: parseFloat(
                                (nuevosGramos / 1000).toFixed(3),
                            ),
                        };
                    }
                    const step =
                        (item.medida_pesable ?? false) &&
                            (item.unidad_medida === 'kg' ||
                                item.unidad_medida === 'lt')
                            ? 0.1
                            : 1;
                    const nuevaCantidad = Math.max(step, value || 0);
                    const maxRetornada = Math.floor(nuevaCantidad);
                    return {
                        ...item,
                        cantidad: parseFloat(nuevaCantidad.toFixed(3)),
                        cantidad_retornada: item.envase_retornable
                            ? Math.min(maxRetornada, Math.max((item.cantidad_retornada || 0), maxRetornada))
                            : item.cantidad_retornada,
                    };
                }
                return item;
            }),
        );
    };

    const setCantidadRetornada = (cartId: string, value: number) => {
        setCart((prev) =>
            prev.map((item) => {
                if (item.cartId === cartId) {
                    const max = Math.max(0, Math.floor(item.cantidad));
                    const cantidadRetornada = Math.min(max, Math.max(0, Math.floor(value || 0)));
                    return {
                        ...item,
                        cantidad_retornada: cantidadRetornada,
                    };
                }
                return item;
            }),
        );
    };

    const eliminarItem = (cartId: string) => {
        setCart((prev) => prev.filter((item) => item.cartId !== cartId));
    };

    const vaciarCarrito = () => {
        setCart([]);
        localStorage.removeItem('pos_cart');
        setMostrandoMetodosPago(false);
    };

    const descuento = useMemo(() => {
        if (promociones.length === 0) return 0;

        const totalCarrito = cart.reduce(
            (acc, item) => acc + item.precio_venta * item.cantidad,
            0,
        );

        let descuentoTotal = 0;

        for (const promo of promociones) {
            if (promo.compra_minima && totalCarrito < promo.compra_minima) {
                continue;
            }

            if (promo.tipo === 'porcentaje') {
                descuentoTotal += totalCarrito * (promo.valor / 100);
            } else if (promo.tipo === 'precio_fijo') {
                descuentoTotal += promo.valor;
            }
        }

        return descuentoTotal;
    }, [cart, promociones]);

    const baseImponible = useMemo(
        () => Math.max(0, subtotal - descuento),
        [subtotal, descuento],
    );
    const iva = useMemo(
        () => incluyeIva ? baseImponible * iva_tasa : 0,
        [baseImponible, iva_tasa, incluyeIva],
    );
    const descuentoManual = useMemo(() => {
        if (!descuentoManualActivo || !valorDescuentoManual || valorDescuentoManual <= 0) return 0;
        if (tipoDescuentoManual === 'porcentaje') {
            return subtotal * (Math.min(valorDescuentoManual, 100) / 100);
        }
        return Math.min(valorDescuentoManual, subtotal);
    }, [descuentoManualActivo, valorDescuentoManual, tipoDescuentoManual, subtotal]);
    const totalCuponAplicado = cuponAplicado ? cuponDescuento : 0;
    const total = useMemo(() => Math.max(0, baseImponible + iva - descuentoManual - totalCuponAplicado), [baseImponible, iva, descuentoManual, totalCuponAplicado]);

    const getStockForAlmacen = (producto: Producto, almacenId: string): number => {
        if (!almacenId) return producto.stock || 0;
        const inv = producto.inventarios?.find((inv) => inv.almacen_id === parseInt(almacenId));
        return inv?.cantidad || 0;
    };

    const getStockMinimoForAlmacen = (producto: Producto, almacenId: string): number => {
        if (!almacenId) return producto.stock_minimo || 0;
        const inv = producto.inventarios?.find((inv) => inv.almacen_id === parseInt(almacenId));
        return inv?.cantidad_minima || producto.stock_minimo || 0;
    };

    const productosFiltrados = useMemo(() => {
        let filtered = productos;

        // Filter by selected almacen (warehouse)
        if (almacenId) {
            filtered = filtered.filter((p) => {
                const inv = p.inventarios?.find((inv) => inv.almacen_id === parseInt(almacenId));
                return inv && inv.cantidad > 0;
            });
        }

        if (!busqueda) return filtered.slice(0, 12);
        const lowerB = busqueda.toLowerCase();
        return filtered.filter(
            (p) =>
                p.nombre.toLowerCase().includes(lowerB) ||
                (p.codigo && p.codigo.toLowerCase().includes(lowerB)),
        );
    }, [busqueda, productos, almacenId]);

    const posForm = useForm({
        cliente_id: '',
        metodo_pago: '',
        tipo_documento: '',
        items: [] as Array<{
            producto_id: number;
            sku_variante_id: number | null;
            cantidad: number;
            precio: number;
            cantidad_retornada?: number;
        }>,
        subtotal: 0,
        iva: 0,
        total: 0,
        incluye_iva: true,
        descuento: 0,
        descuento_manual: 0,
        tipo_descuento_manual: null as 'fijo' | 'porcentaje' | null,
        valor_descuento_manual: 0,
        almacen_id: '',
        cupon_codigo: undefined as string | undefined,
    });

    const procesarPago = (
        metodo:
            | 'efectivo'
            | 'tarjeta'
            | 'transferencia'
            | 'vale'
            | 'visa_transbank'
            | 'binance'
            | 'contactar',
    ) => {
        if (cart.length === 0) return;

        posForm.setData({
            cliente_id: clienteId,
            metodo_pago: metodo,
            tipo_documento: tipoDocumento,
            items: cart.map((item) => ({
                producto_id: item.productoId,
                sku_variante_id: item.skuId,
                cantidad: item.cantidad,
                precio: item.precio_venta,
                cantidad_retornada: item.cantidad_retornada,
            })),
            subtotal: baseImponible,
            iva: iva,
            total: total,
            incluye_iva: incluyeIva,
            descuento: descuento,
            descuento_manual: descuentoManual,
            tipo_descuento_manual: descuentoManualActivo && valorDescuentoManual > 0 ? tipoDescuentoManual : null,
            valor_descuento_manual: descuentoManualActivo ? valorDescuentoManual : 0,
            almacen_id: almacenId,
            cupon_codigo: codigoCupon || undefined,
        });

        posForm.post('/pos', {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page as any)?.props?.flash ?? {};
                const ultimaVentaId = flash.ultima_venta_id ?? null;
                const numeroVenta = 'POS-' + Date.now();
                setUltimaVenta({
                    ventaId: ultimaVentaId,
                    numero: numeroVenta,
                    items: [...cart],
                    subtotal: subtotal,
                    descuento: descuento,
                    iva: iva,
                    total: total,
                    metodoPago: metodo,
                    tipoDocumento: tipoDocumento,
                    cupon: cuponAplicado && cuponDescuento > 0 ? {
                        codigo: codigoCupon,
                        nombre: codigoCupon,
                        descuento: cuponDescuento,
                    } : null,
                });
                vaciarCarrito();
                limpiarCupon();
                posForm.reset();
                setMostrandoMetodosPago(false);
                toast.success('Venta registrada exitosamente');
            },
            onError: (errors) => {
                // El backend es la fuente de verdad (lockForUpdate + transacciones)
                // Si hay error de stock, mostramos el mensaje del servidor
                const mensaje = Object.values(errors).join(' ') || 'Error al procesar la venta';
                toast.error(mensaje);
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Caja POS', href: '/pos' },
            ]}
        >
            <Head title="Caja POS" />
            <div className="flex min-h-dvh overflow-hidden bg-background">
                <div className="flex flex-1 flex-col gap-4 overflow-hidden border-r p-4">
                    <div className="flex items-center gap-3">
                        <div className="relative flex-1">
                            <Search className="absolute top-3 left-3 h-5 w-5 text-muted-foreground" />
                            <Input
                                ref={inputRef}
                                type="text"
                                placeholder="Buscar producto o escanear..."
                                className="h-12 pl-10 text-lg"
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                autoFocus
                            />
                        </div>
                    </div>

                    <div className="flex-1 overflow-y-auto pr-2">
                        {productosFiltrados.length === 0 ? (
                            <div className="flex h-full flex-col items-center justify-center text-muted-foreground">
                                <ShoppingCart className="mb-4 h-16 w-16 opacity-20" />
                                <p>No se encontraron productos.</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                {productosFiltrados.map((prod) => (
                                    <Card
                                        key={prod.id}
                                        className="h-fit cursor-pointer transition-all hover:border-primary hover:shadow-md"
                                        onClick={() =>
                                            handleProductoClick(prod)
                                        }
                                    >
                                        <div className="group relative flex aspect-square items-center justify-center overflow-hidden bg-muted/30 p-2 text-muted-foreground">
                                            {prod.imagen ? (
                                                <img
                                                    src={
                                                        '/storage/' +
                                                        prod.imagen
                                                    }
                                                    className="h-full w-full rounded object-cover"
                                                    alt={prod.nombre}
                                                />
                                            ) : (
                                                <span className="text-2xl font-black opacity-10">
                                                    {prod.nombre
                                                        .substring(0, 2)
                                                        .toUpperCase()}
                                                </span>
                                            )}
                                            <div className="absolute inset-0 flex items-center justify-center bg-primary/10 opacity-0 transition-opacity group-hover:opacity-100">
                                                {prod.tiene_variantes ? (
                                                    <Package className="h-10 w-10 text-primary" />
                                                ) : (
                                                    <Plus className="h-10 w-10 text-primary" />
                                                )}
                                            </div>
{getStockForAlmacen(prod, almacenId) > 0 &&
                                                    getStockForAlmacen(prod, almacenId) <=
                                                    getStockMinimoForAlmacen(prod, almacenId) && (
                                                        <Badge
                                                            variant="destructive"
                                                            className="absolute top-2 right-2 text-[10px]"
                                                        >
                                                            Bajo stock
                                                        </Badge>
                                                    )}
                                                {getStockForAlmacen(prod, almacenId) === 0 && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="absolute top-2 right-2 text-[10px]"
                                                    >
                                                        Sin stock
                                                    </Badge>
                                                )}
                                        </div>
                                        <CardContent className="p-3">
                                            <div className="mb-1 truncate text-xs font-bold text-muted-foreground uppercase">
                                                {prod.codigo || 'S/C'}
                                            </div>
                                            <div className="mb-2 line-clamp-2 h-10 text-sm leading-tight font-semibold">
                                                {prod.nombre}
                                            </div>
                                            {prod.tiene_variantes && (
                                                <div className="mb-1 text-xs text-muted-foreground">
                                                    {prod.skus.length} variantes
                                                </div>
                                            )}
                                            <div className="mt-auto flex items-center justify-between">
                                                <div className="text-lg leading-none font-black text-emerald-600">
                                                    {formatCurrency(
                                                        prod.tiene_variantes
                                                            ? prod.precio_con_variantes
                                                            : prod.precio_venta,
                                                    )}
                                                </div>
                                                {prod.tiene_variantes ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="h-4 py-0 text-[10px] uppercase"
                                                    >
                                                        SKUs
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="h-4 py-0 text-[10px] uppercase"
                                                    >
                                                        {prod.unidad_medida ||
                                                            'u'}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                Stock:{' '}
                                                {getStockForAlmacen(prod, almacenId) > 0
                                                    ? getStockForAlmacen(prod, almacenId)
                                                    : 'Sin stock'}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <div className="hidden lg:flex w-[400px] flex-col bg-muted/10">
                    <div className="space-y-4 border-b bg-background p-4 shadow-sm">
                        <div className="flex items-center gap-2">
                            <UserIcon className="h-4 w-4 text-muted-foreground" />
                            <Select
                                value={clienteId}
                                onValueChange={setClienteId}
                            >
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="Cliente Genérico" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">
                                        Cliente Genérico / Sin Cliente
                                    </SelectItem>
                                    {clientes.map((c) => (
                                        <SelectItem
                                            key={c.id}
                                            value={String(c.id)}
                                        >
                                            {c.nombre} ({c.rut})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex gap-2">
                            <Button
                                variant={
                                    tipoDocumento === 'boleta'
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                className="flex-1"
                                onClick={() => setTipoDocumento('boleta')}
                            >
                                <Receipt className="mr-2 h-4 w-4" /> Boleta
                            </Button>
                            <Button
                                variant={
                                    tipoDocumento === 'factura'
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                className="flex-1"
                                onClick={() => setTipoDocumento('factura')}
                            >
                                <FileText className="mr-2 h-4 w-4" /> Factura
                            </Button>
                        </div>

                        <div className="space-y-2">
                            <Label className="text-xs font-black text-primary/70 uppercase">
                                Almacén de Origen
                            </Label>
                            <Select
                                value={almacenId}
                                onValueChange={setAlmacenId}
                            >
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="Seleccionar Almacén" />
                                </SelectTrigger>
                                <SelectContent>
                                    {almacenes.map((a) => (
                                        <SelectItem
                                            key={a.id}
                                            value={String(a.id)}
                                        >
                                            {a.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="flex flex-1 flex-col gap-2 overflow-y-auto p-4">
                        {cart.length > 0 && (
                            <div className="flex items-center justify-between px-1">
                                <span className="text-xs font-medium text-muted-foreground">
                                    {cart.length} {cart.length === 1 ? 'producto' : 'productos'}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 text-xs text-destructive hover:text-destructive"
                                    onClick={vaciarCarrito}
                                >
                                    <Trash2 className="mr-1 h-3 w-3" />
                                    Limpiar
                                </Button>
                            </div>
                        )}
                        {cart.length === 0 ? (
                            <div className="flex h-full flex-col items-center justify-center py-20 text-muted-foreground/40">
                                <Barcode className="mb-4 h-20 w-20" />
                                <p className="font-bold">CAJA LISTA</p>
                                <p className="text-xs">
                                    Escanee o busque productos
                                </p>
                            </div>
                        ) : (
                            cart.map((item) => (
                                <Card
                                    key={item.cartId}
                                    className="shrink-0 overflow-hidden border-none shadow-sm"
                                >
                                    <div className="flex gap-3 p-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate text-sm font-bold">
                                                {item.nombre}
                                            </div>
                                            {item.variantes && (
                                                <div className="text-xs text-muted-foreground">
                                                    {item.variantes}
                                                </div>
                                            )}
                                            <div className="text-xs text-muted-foreground">
                                                {formatCurrency(
                                                    item.precio_venta,
                                                )}{' '}
                                                x {item.cantidad}
                                                {(item.medida_pesable || (item.peso_base ?? 0) > 0 || (item.contenido_por_unidad ?? 1) !== 1) && (
                                                    <span className="ml-1 text-[10px]">
                                                        ({(item.cantidad * (item.contenido_por_unidad ?? 1) + item.cantidad * (item.peso_base ?? 0)).toFixed(3)} {item.unidad_medida} reales)
                                                    </span>
                                                )}
                                                {item.stock > 0 &&
                                                    item.stock <= 5 && (
                                                        <span className="ml-1 text-orange-500">
                                                            (Stock: {item.stock}
                                                            )
                                                        </span>
                                                    )}
                                            </div>
                                        </div>
                                        <div className="flex flex-col items-end justify-between text-right">
                                            <div className="font-black text-emerald-600">
                                                {formatCurrency(
                                                    item.precio_venta * item.cantidad + (item.envase_retornable && item.envase_precio ? Math.max(0, item.cantidad - (item.cantidad_retornada || 0)) * item.envase_precio : 0),
                                                )}
                                            </div>
                                            <div className="mt-2 flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 rounded-full bg-muted"
                                                    onClick={() =>
                                                        actualizarCantidad(
                                                            item.cartId,
                                                            -1,
                                                        )
                                                    }
                                                >
                                                    <Minus className="h-3 w-3" />
                                                </Button>
                                                <Input
                                                    type="number"
                                                    step={item.modoGramos ? '1' : (esGrameable(item) ? '0.1' : '1')}
                                                    min={item.modoGramos ? '1' : (esGrameable(item) ? '0.1' : '1')}
                                                    value={item.modoGramos ? Math.round(item.cantidad * 1000) : item.cantidad}
                                                    onChange={(e) => setCantidadItem(item.cartId, parseFloat(e.target.value) || 0)}
                                                    className="h-7 w-16 text-center text-xs font-bold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                />
                                                <span className="text-[8px] uppercase">
                                                    {item.modoGramos
                                                        ? (item.unidad_medida === 'kg' ? 'g' : item.unidad_medida === 'lt' ? 'ml' : 'u')
                                                        : (esGrameable(item) ? item.unidad_medida : 'u')}
                                                </span>
                                                {esGrameable(item) && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 w-7 rounded-full bg-muted text-[9px] font-bold"
                                                        onClick={() => toggleModoGramos(item.cartId)}
                                                        title={item.modoGramos ? 'Cambiar a unidad completa' : 'Cambiar a gramos/ml'}
                                                    >
                                                        {item.modoGramos
                                                            ? (item.unidad_medida === 'kg' ? 'kg' : 'L')
                                                            : (item.unidad_medida === 'kg' ? 'g' : 'ml')}
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 rounded-full bg-muted"
                                                    onClick={() =>
                                                        actualizarCantidad(
                                                            item.cartId,
                                                            1,
                                                        )
                                                    }
                                                >
                                                    <Plus className="h-3 w-3" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="ml-2 h-7 w-7 text-destructive hover:bg-destructive/10"
                                                    onClick={() =>
                                                        eliminarItem(
                                                            item.cartId,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                    {item.envase_retornable && (
                                        <div className="flex items-center justify-between gap-2 border-t bg-orange-50/50 p-2 dark:bg-orange-900/10">
                                            <div className="text-[10px] font-semibold text-muted-foreground uppercase leading-tight">
                                                Envases Devueltos
                                                <br />
                                                <span className="text-[9px] text-orange-500 normal-case">
                                                    Faltantes: {Math.max(0, item.cantidad - (item.cantidad_retornada || 0))} se cobran a {formatCurrency(item.envase_precio || 0)} c/u
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 rounded-full bg-muted"
                                                    onClick={() => setCantidadRetornada(item.cartId, Math.max(0, (item.cantidad_retornada || 0) - 1))}
                                                >
                                                    <Minus className="h-3 w-3" />
                                                </Button>
                                                <Input
                                                    type="number"
                                                    value={item.cantidad_retornada === undefined ? 0 : item.cantidad_retornada}
                                                    onChange={(e) => setCantidadRetornada(item.cartId, parseFloat(e.target.value))}
                                                    className="h-7 w-16 text-xs text-center p-1 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                    min="0"
                                                    max={Math.ceil(item.cantidad)}
                                                />
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 rounded-full bg-muted"
                                                    onClick={() => setCantidadRetornada(item.cartId, Math.min(Math.ceil(item.cantidad), (item.cantidad_retornada || 0) + 1))}
                                                >
                                                    <Plus className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </Card>
                            ))
                        )}
                    </div>

                    <div className="space-y-4 border-t bg-background p-4 shadow-[0_-4px_10px_rgba(0,0,0,0.05)]">
                        <div className="space-y-1">
                            <div className="flex justify-between text-sm font-medium text-muted-foreground">
                                <span>Subtotal</span>
                                <span>{formatCurrency(subtotal)}</span>
                            </div>
                            {descuento > 0 && (
                                <div className="flex justify-between text-sm font-medium text-orange-600">
                                    <span>Descuento</span>
                                    <span>-{formatCurrency(descuento)}</span>
                                </div>
                            )}
                            {cuponAplicado && cuponDescuento > 0 && (
                                <div className="flex justify-between text-sm font-medium text-emerald-600">
                                    <span>Dto. Cupón</span>
                                    <span>-{formatCurrency(cuponDescuento)}</span>
                                </div>
                            )}
                            {descuentoManual > 0 && (
                                <div className="flex justify-between text-sm font-medium text-purple-600">
                                    <span>Dto. Manual ({tipoDescuentoManual === 'porcentaje' ? `${valorDescuentoManual}%` : `$${formatCurrency(valorDescuentoManual)}`})</span>
                                    <span>-{formatCurrency(descuentoManual)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-sm font-medium text-muted-foreground">
                                <span>IVA ({(iva_tasa * 100).toFixed(0)}%)</span>
                                <span>{formatCurrency(iva)}</span>
                            </div>
                            <div className="flex items-center gap-2 py-1">
                                <Checkbox
                                    id="incluye-iva"
                                    checked={incluyeIva}
                                    onCheckedChange={(checked) => setIncluyeIva(!!checked)}
                                />
                                <Label htmlFor="incluye-iva" className="text-xs font-medium cursor-pointer text-muted-foreground">
                                    Incluir IVA
                                </Label>
                            </div>
                            <div className="mt-2 flex justify-between border-t pt-2 text-xl font-black">
                                <span>TOTAL</span>
                                <span className="text-emerald-600">
                                    {formatCurrency(total)}
                                </span>
                            </div>
                        </div>

                        <div className="space-y-2 border-t pt-3">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="descuento-manual"
                                    checked={descuentoManualActivo}
                                    onCheckedChange={(checked) => {
                                        setDescuentoManualActivo(!!checked);
                                        if (!checked) setValorDescuentoManual(0);
                                    }}
                                />
                                <Label htmlFor="descuento-manual" className="text-sm font-medium cursor-pointer">
                                    Descuento manual
                                </Label>
                            </div>
                            {descuentoManualActivo && (
                                <div className="flex items-center gap-2">
                                    <div className="flex-1">
                                        <Input
                                            type="number"
                                            min="0"
                                            step={tipoDescuentoManual === 'porcentaje' ? '1' : '0.01'}
                                            placeholder={tipoDescuentoManual === 'porcentaje' ? '% descuento' : 'Monto descuento'}
                                            value={valorDescuentoManual || ''}
                                            onChange={(e) => setValorDescuentoManual(Number(e.target.value))}
                                            className="h-9 text-sm"
                                            disabled={procesando}
                                        />
                                    </div>
                                    <Select
                                        value={tipoDescuentoManual}
                                        onValueChange={(v: 'fijo' | 'porcentaje') => setTipoDescuentoManual(v)}
                                    >
                                        <SelectTrigger className="h-9 w-[130px] text-sm">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="porcentaje">%</SelectItem>
                                            <SelectItem value="fijo">Precio fijo</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <Input
                                    id="coupon-code-input"
                                    placeholder="Código cupón"
                                    value={codigoCupon}
                                    onChange={(e) => {
                                        setCodigoCupon(e.target.value.toUpperCase());
                                        if (cuponAplicado) {
                                            setCuponAplicado(false);
                                            setCuponValido(null);
                                            setCuponMensaje('');
                                            setCuponDescuento(0);
                                        }
                                    }}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            validarCupon();
                                        }
                                    }}
                                    className={'h-9 flex-1 text-sm ' + (cuponAplicado ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20' : cuponValido === false ? 'border-red-500' : '')}
                                    disabled={procesando || cuponAplicado}
                                />
                                {!cuponAplicado ? (
                                    <Button
                                        id="validate-coupon-btn"
                                        variant="default"
                                        size="sm"
                                        className="h-9 shrink-0 bg-violet-600 hover:bg-violet-700 text-xs font-bold uppercase"
                                        onClick={validarCupon}
                                        disabled={procesando || cuponValidando || !codigoCupon || codigoCupon.length < 3}
                                    >
                                        {cuponValidando ? (
                                            <RefreshCw className="mr-1 h-3.5 w-3.5 animate-spin" />
                                        ) : (
                                            <Ticket className="mr-1 h-3.5 w-3.5" />
                                        )}
                                        Validar
                                    </Button>
                                ) : (
                                    <Button
                                        id="clear-coupon-btn"
                                        variant="ghost"
                                        size="icon"
                                        className="h-9 w-9 shrink-0 text-destructive hover:bg-destructive/10"
                                        onClick={limpiarCupon}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                            {cuponMensaje && (
                                <p className={'text-xs flex items-center gap-1 ' + (cuponValido ? 'text-emerald-600' : 'text-red-500')}>
                                    {cuponValido ? (
                                        <><CheckCircle2 className="h-3 w-3 shrink-0" />{cuponMensaje}</>
                                    ) : (
                                        <><AlertTriangle className="h-3 w-3 shrink-0" />{cuponMensaje}</>
                                    )}
                                </p>
                            )}
                        </div>

                        <div className="space-y-3">
                            {!mostrandoMetodosPago ? (
                                <>
                                    <Button
                                        className="h-16 w-full bg-emerald-600 shadow-lg shadow-emerald-500/20 hover:bg-emerald-700 text-lg font-bold flex items-center justify-center gap-2"
                                        disabled={cart.length === 0 || posForm.processing}
                                        onClick={() => setMostrandoMetodosPago(true)}
                                    >
                                        <CreditCard className="h-6 w-6" />
                                        <span>Procesar Venta</span>
                                        <span className="font-mono text-xl">
                                            {formatCurrency(total)}
                                        </span>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        className="h-12 w-full bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 font-medium"
                                        disabled={cart.length === 0 || posForm.processing}
                                        onClick={vaciarCarrito}
                                    >
                                        <Trash2 className="mr-2 h-5 w-5" />
                                        <span>Limpiar Caja</span>
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <Button
                                            className="h-16 flex-col gap-1 bg-blue-600 shadow-lg shadow-blue-500/20 hover:bg-blue-700"
                                            disabled={cart.length === 0 || posForm.processing}
                                            onClick={() => procesarPago('tarjeta')}
                                        >
                                            <CreditCard className="h-6 w-6" />
                                            <span className="text-[10px] font-bold uppercase">
                                                Tarjeta
                                            </span>
                                        </Button>
                                        <Button
                                            className="h-16 flex-col gap-1 bg-emerald-600 shadow-lg shadow-emerald-500/20 hover:bg-emerald-700"
                                            disabled={cart.length === 0 || posForm.processing}
                                            onClick={() => procesarPago('efectivo')}
                                        >
                                            <Banknote className="h-6 w-6" />
                                            <span className="text-[10px] font-bold uppercase">
                                                Efectivo
                                            </span>
                                        </Button>
                                    </div>

                                    <Button
                                        className="mt-3 h-12 w-full bg-slate-600 hover:bg-slate-700"
                                        disabled={cart.length === 0 || posForm.processing}
                                        onClick={() => setMostrarModalPagos(true)}
                                    >
                                        <Wallet className="mr-2 h-5 w-5" />
                                        <span className="font-bold uppercase">
                                            Otros Métodos
                                        </span>
                                    </Button>

                                    <Button
                                        variant="ghost"
                                        className="mt-2 h-10 w-full text-sm text-muted-foreground hover:text-foreground"
                                        onClick={() => setMostrandoMetodosPago(false)}
                                    >
                                        <ArrowLeft className="mr-2 h-4 w-4" />
                                        Volver
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <Sheet open={cartOpen} onOpenChange={setCartOpen}>
                    <SheetTrigger asChild>
                        <Button
                            className="fixed right-4 bottom-4 z-40 flex h-14 w-14 items-center justify-center rounded-full shadow-xl lg:hidden"
                            size="icon"
                        >
                            <ShoppingCart className="h-6 w-6" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent aria-describedby={undefined} side="right" className="flex w-full flex-col p-0 sm:max-w-md">
                        <SheetHeader className="border-b px-4 py-3">
                            <SheetTitle>Carrito de Compras</SheetTitle>
                        </SheetHeader>
                        <div className="flex flex-1 flex-col bg-muted/10">
                            <div className="space-y-4 border-b bg-background p-4 shadow-sm">
                                <div className="flex items-center gap-2">
                                    <UserIcon className="h-4 w-4 text-muted-foreground" />
                                    <Select
                                        value={clienteId}
                                        onValueChange={setClienteId}
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Cliente Genérico" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="0">
                                                Cliente Genérico
                                            </SelectItem>
                                            {clientes.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={String(c.id)}
                                                >
                                                    {c.nombre} ({c.rut})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex gap-2">
                                    <Button
                                        variant={tipoDocumento === 'boleta' ? 'default' : 'outline'}
                                        size="sm"
                                        className="flex-1"
                                        onClick={() => setTipoDocumento('boleta')}
                                    >
                                        <Receipt className="mr-2 h-4 w-4" /> Boleta
                                    </Button>
                                    <Button
                                        variant={tipoDocumento === 'factura' ? 'default' : 'outline'}
                                        size="sm"
                                        className="flex-1"
                                        onClick={() => setTipoDocumento('factura')}
                                    >
                                        <FileText className="mr-2 h-4 w-4" /> Factura
                                    </Button>
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-xs font-black uppercase text-primary/70">
                                        Almacén de Origen
                                    </Label>
                                    <Select value={almacenId} onValueChange={setAlmacenId}>
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Seleccionar Almacén" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {almacenes.map((a) => (
                                                <SelectItem key={a.id} value={String(a.id)}>
                                                    {a.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="flex flex-1 flex-col gap-2 overflow-y-auto p-4">
                                {cart.length > 0 && (
                                    <div className="flex items-center justify-between px-1">
                                        <span className="text-xs font-medium text-muted-foreground">
                                            {cart.length} {cart.length === 1 ? 'producto' : 'productos'}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 text-xs text-destructive hover:text-destructive"
                                            onClick={vaciarCarrito}
                                        >
                                            <Trash2 className="mr-1 h-3 w-3" />
                                            Limpiar
                                        </Button>
                                    </div>
                                )}
                                {cart.length === 0 ? (
                                    <div className="flex h-full flex-col items-center justify-center py-20 text-muted-foreground/40">
                                        <Barcode className="mb-4 h-20 w-20" />
                                        <p className="font-bold">CAJA LISTA</p>
                                        <p className="text-xs">Escanee o busque productos</p>
                                    </div>
                                ) : (
                                    cart.map((item) => (
                                        <Card key={item.cartId} className="shrink-0 overflow-hidden border-none shadow-sm">
                                            <div className="flex gap-3 p-3">
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate text-sm font-bold">{item.nombre}</div>
                                                    {item.variantes && (
                                                        <div className="text-xs text-muted-foreground">{item.variantes}</div>
                                                    )}
                                                    <div className="text-xs text-muted-foreground">
                                                        {formatCurrency(item.precio_venta)} x {item.cantidad}
                                                        {item.stock > 0 && item.stock <= 5 && (
                                                            <span className="ml-1 text-orange-500">(Stock: {item.stock})</span>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="flex flex-col items-end justify-between text-right">
                                                    <div className="font-black text-emerald-600">
                                                        {formatCurrency(item.precio_venta * item.cantidad + (item.envase_retornable && item.envase_precio ? Math.max(0, item.cantidad - (item.cantidad_retornada || 0)) * item.envase_precio : 0))}
                                                    </div>
                                                    <div className="mt-2 flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-7 w-7 rounded-full bg-muted"
                                                            onClick={() => actualizarCantidad(item.cartId, -1)}
                                                        >
                                                            <Minus className="h-3 w-3" />
                                                        </Button>
                                                        <Input
                                                            type="number"
                                                            step={item.modoGramos ? '1' : (esGrameable(item) ? '0.1' : '1')}
                                                            min={item.modoGramos ? '1' : (esGrameable(item) ? '0.1' : '1')}
                                                            value={item.modoGramos ? Math.round(item.cantidad * 1000) : item.cantidad}
                                                            onChange={(e) => setCantidadItem(item.cartId, parseFloat(e.target.value) || 0)}
                                                            className="h-7 w-16 text-center text-xs font-bold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                        />
                                                        <span className="text-[8px] uppercase">
                                                            {item.modoGramos
                                                                ? (item.unidad_medida === 'kg' ? 'g' : item.unidad_medida === 'lt' ? 'ml' : 'u')
                                                                : (esGrameable(item) ? item.unidad_medida : 'u')}
                                                        </span>
                                                        {esGrameable(item) && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-7 w-7 rounded-full bg-muted text-[9px] font-bold"
                                                                onClick={() => toggleModoGramos(item.cartId)}
                                                                title={item.modoGramos ? 'Cambiar a unidad completa' : 'Cambiar a gramos/ml'}
                                                            >
                                                                {item.modoGramos
                                                                    ? (item.unidad_medida === 'kg' ? 'kg' : 'L')
                                                                    : (item.unidad_medida === 'kg' ? 'g' : 'ml')}
                                                            </Button>
                                                        )}
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-7 w-7 rounded-full bg-muted"
                                                            onClick={() => actualizarCantidad(item.cartId, 1)}
                                                        >
                                                            <Plus className="h-3 w-3" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="ml-2 h-7 w-7 text-destructive hover:bg-destructive/10"
                                                            onClick={() => eliminarItem(item.cartId)}
                                                        >
                                                            <Trash2 className="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
{item.envase_retornable && (
                                            <div className="flex items-center justify-between gap-2 border-t bg-orange-50/50 p-2 dark:bg-orange-900/10">
                                                <div className="text-[10px] font-semibold text-muted-foreground uppercase leading-tight">
                                                    Envases Devueltos
                                                    <br />
                                                    <span className="text-[9px] text-orange-500 normal-case">
                                                        Faltantes: {Math.max(0, item.cantidad - (item.cantidad_retornada || 0))} se cobran a {formatCurrency(item.envase_precio || 0)} c/u
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-7 w-7 rounded-full bg-muted"
                                                        onClick={() => setCantidadRetornada(item.cartId, Math.max(0, (item.cantidad_retornada || 0) - 1))}
                                                    >
                                                        <Minus className="h-3 w-3" />
                                                    </Button>
                                                    <Input
                                                        type="number"
                                                        value={item.cantidad_retornada === undefined ? 0 : item.cantidad_retornada}
                                                        onChange={(e) => setCantidadRetornada(item.cartId, parseFloat(e.target.value))}
                                                        className="h-7 w-16 text-xs text-center p-1 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                        min="0"
                                                        max={Math.ceil(item.cantidad)}
                                                    />
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-7 w-7 rounded-full bg-muted"
                                                        onClick={() => setCantidadRetornada(item.cartId, Math.min(Math.ceil(item.cantidad), (item.cantidad_retornada || 0) + 1))}
                                                    >
                                                        <Plus className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            </div>
                                        )}
                                        </Card>
                                    ))
                                )}
                            </div>

                            <div className="space-y-4 border-t bg-background p-4 shadow-[0_-4px_10px_rgba(0,0,0,0.05)]">
                                <div className="space-y-1">
                                    <div className="flex justify-between text-sm font-medium text-muted-foreground">
                                        <span>Subtotal</span>
                                        <span>{formatCurrency(subtotal)}</span>
                                    </div>
                                    {descuento > 0 && (
                                        <div className="flex justify-between text-sm font-medium text-orange-600">
                                            <span>Descuento</span>
                                            <span>-{formatCurrency(descuento)}</span>
                                        </div>
                                    )}
                                    {cuponAplicado && cuponDescuento > 0 && (
                                        <div className="flex justify-between text-sm font-medium text-emerald-600">
                                            <span>Dto. Cupón</span>
                                            <span>-{formatCurrency(cuponDescuento)}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-sm font-medium text-muted-foreground">
                                        <span>IVA ({(iva_tasa * 100).toFixed(0)}%)</span>
                                        <span>{formatCurrency(iva)}</span>
                                    </div>
                                    <div className="flex items-center gap-2 py-1">
                                        <Checkbox
                                            id="incluye-iva-mobile"
                                            checked={incluyeIva}
                                            onCheckedChange={(checked) => setIncluyeIva(!!checked)}
                                        />
                                        <Label htmlFor="incluye-iva-mobile" className="text-xs font-medium cursor-pointer text-muted-foreground">
                                            Incluir IVA
                                        </Label>
                                    </div>
                                    <div className="mt-2 flex justify-between border-t pt-2 text-xl font-black">
                                        <span>TOTAL</span>
                                        <span className="text-emerald-600">{formatCurrency(total)}</span>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id="coupon-code-input-mobile"
                                            placeholder="Código cupón"
                                            value={codigoCupon}
                                            onChange={(e) => {
                                                setCodigoCupon(e.target.value.toUpperCase());
                                                if (cuponAplicado) {
                                                    setCuponAplicado(false);
                                                    setCuponValido(null);
                                                    setCuponMensaje('');
                                                    setCuponDescuento(0);
                                                }
                                            }}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    validarCupon();
                                                }
                                            }}
                                            className={'h-9 flex-1 text-sm ' + (cuponAplicado ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20' : cuponValido === false ? 'border-red-500' : '')}
                                            disabled={procesando || cuponAplicado}
                                        />
                                        {!cuponAplicado ? (
                                            <Button
                                                id="validate-coupon-btn-mobile"
                                                variant="default"
                                                size="sm"
                                                className="h-9 shrink-0 bg-violet-600 hover:bg-violet-700 text-xs font-bold uppercase"
                                                onClick={validarCupon}
                                                disabled={procesando || cuponValidando || !codigoCupon || codigoCupon.length < 3}
                                            >
                                                {cuponValidando ? (
                                                    <RefreshCw className="mr-1 h-3.5 w-3.5 animate-spin" />
                                                ) : (
                                                    <Ticket className="mr-1 h-3.5 w-3.5" />
                                                )}
                                                Validar
                                            </Button>
                                        ) : (
                                            <Button
                                                id="clear-coupon-btn-mobile"
                                                variant="ghost"
                                                size="icon"
                                                className="h-9 w-9 shrink-0 text-destructive hover:bg-destructive/10"
                                                onClick={limpiarCupon}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                    {cuponMensaje && (
                                        <p className={'text-xs flex items-center gap-1 ' + (cuponValido ? 'text-emerald-600' : 'text-red-500')}>
                                            {cuponValido ? (
                                                <><CheckCircle2 className="h-3 w-3 shrink-0" />{cuponMensaje}</>
                                            ) : (
                                                <><AlertTriangle className="h-3 w-3 shrink-0" />{cuponMensaje}</>
                                            )}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <Button
                                        className="h-16 flex-col gap-1 bg-blue-600 shadow-lg shadow-blue-500/20 hover:bg-blue-700"
                                        disabled={cart.length === 0 || procesando}
                                        onClick={() => {
                                            procesarPago('tarjeta');
                                            setCartOpen(false);
                                        }}
                                    >
                                        <CreditCard className="h-6 w-6" />
                                        <span className="text-[10px] font-bold uppercase">Tarjeta</span>
                                    </Button>
                                    <Button
                                        className="h-16 flex-col gap-1 bg-emerald-600 shadow-lg shadow-emerald-500/20 hover:bg-emerald-700"
                                        disabled={cart.length === 0 || procesando}
                                        onClick={() => {
                                            procesarPago('efectivo');
                                            setCartOpen(false);
                                        }}
                                    >
                                        <Banknote className="h-6 w-6" />
                                        <span className="text-[10px] font-bold uppercase">Efectivo</span>
                                    </Button>
                                </div>

                                <Button
                                    className="mt-3 h-12 w-full bg-slate-600 hover:bg-slate-700"
                                    disabled={cart.length === 0 || procesando}
                                    onClick={() => {
                                        setMostrarModalPagos(true);
                                        setCartOpen(false);
                                    }}
                                >
                                    <Wallet className="mr-2 h-5 w-5" />
                                    <span className="font-bold uppercase">Otros Métodos</span>
                                </Button>
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>

                <Dialog
                    open={mostrarModalPagos}
                    onOpenChange={setMostrarModalPagos}
                >
                    <DialogContent aria-describedby={undefined} className="w-[95vw] max-w-md sm:max-w-lg md:max-w-2xl lg:max-w-3xl overflow-hidden p-6">
                        <DialogHeader className="pb-4">
                            <DialogTitle className="flex items-center gap-2 text-lg">
                                <Wallet className="h-5 w-5" />
                                Seleccionar Método de Pago
                            </DialogTitle>
                        </DialogHeader>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
                            <Button
                                className="h-20 sm:h-24 flex-col gap-2 bg-purple-600 hover:bg-purple-700 rounded-xl"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('vale');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <Ticket className="h-6 w-6 sm:h-8 sm:w-8" />
                                <span className="text-xs sm:text-sm font-bold leading-tight uppercase tracking-wider">Vales</span>
                            </Button>

                            <Button
                                className="h-20 sm:h-24 flex-col gap-2 bg-indigo-600 hover:bg-indigo-700 rounded-xl"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('transferencia');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <Building2 className="h-6 w-6 sm:h-8 sm:w-8" />
                                <span className="text-xs sm:text-sm font-bold leading-tight uppercase tracking-wider">Transferencia</span>
                            </Button>

                            <Button
                                className="h-20 sm:h-24 flex-col gap-2 bg-orange-600 hover:bg-orange-700 rounded-xl"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('visa_transbank');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <CreditCard className="h-6 w-6 sm:h-8 sm:w-8" />
                                <span className="text-xs sm:text-sm font-bold leading-tight uppercase tracking-wider">Visa Transbank</span>
                            </Button>

                            <Button
                                className="h-20 sm:h-24 flex-col gap-2 bg-yellow-500 hover:bg-yellow-600 rounded-xl"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('binance');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <svg viewBox="0 0 24 24" className="h-6 w-6 sm:h-8 sm:w-8 fill-current">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 13.53l-1.41-1.41-3.64 3.64-1.41-1.41-1.41 1.41 2.82 2.82 4.24-4.24-1.41-1.41z" />
                                </svg>
                                <span className="text-xs sm:text-sm font-bold leading-tight uppercase tracking-wider">Binance</span>
                            </Button>

                            <Button
                                className="col-span-1 sm:col-span-2 lg:col-span-1 xl:col-span-1 h-16 sm:h-20 flex-col gap-2 bg-slate-700 hover:bg-slate-800 rounded-xl"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('contactar');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <MessageCircle className="h-5 w-5 sm:h-6 sm:w-6" />
                                <span className="text-xs sm:text-sm font-bold leading-tight uppercase tracking-wider">Contactar con Administración</span>
                            </Button>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button variant="outline" size="sm" onClick={() => setMostrarModalPagos(false)} className="w-full sm:w-auto">
                                Cancelar
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={!!productoSeleccionado}
                    onOpenChange={(open) => !open && setProductoSeleccionado(null)}
                >
                    <DialogContent aria-describedby={undefined} className="w-[95vw] max-w-md sm:max-w-lg md:max-w-xl lg:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Package className="h-5 w-5" />
                                Seleccionar Variante
                            </DialogTitle>
                        </DialogHeader>
                        {productoSeleccionado && (
                            <div className="space-y-4">
                                <div>
                                    <Label className="text-muted-foreground">
                                        Producto
                                    </Label>
                                    <p className="text-lg font-semibold">
                                        {productoSeleccionado.nombre}
                                    </p>
                                </div>
                                <div>
                                    <Label className="mb-2 block text-muted-foreground">
                                        SKU / Variante
                                    </Label>
                                    <div className="grid max-h-[300px] grid-cols-1 gap-2 overflow-y-auto">
                                        {productoSeleccionado.skus?.map((sku) => (
                                            <div
                                                key={sku.id}
                                                className={`cursor-pointer rounded-lg border p-3 transition-all ${skuSeleccionado?.id === sku.id
                                                        ? 'border-primary bg-primary/10'
                                                        : sku.stock === 0
                                                            ? 'cursor-not-allowed opacity-50'
                                                            : 'hover:border-muted-foreground'
                                                    }`}
                                                onClick={() =>
                                                    sku.stock > 0 &&
                                                    setSkuSeleccionado(sku)
                                                }
                                            >
                                                <div className="flex items-start justify-between">
                                                    <div>
                                                        <p className="font-medium">
                                                            {sku.sku}
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {sku.variantes
                                                                .map(
                                                                    (v) =>
                                                                        `${v.variante}: ${v.valor}`,
                                                                )
                                                                .join(' | ')}
                                                        </p>
                                                        <p
                                                            className={`text-sm ${sku.stock > 0 ? 'text-emerald-600' : 'text-destructive'}`}
                                                        >
                                                            Stock: {sku.stock}
                                                        </p>
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="font-bold text-emerald-600">
                                                            {formatCurrency(
                                                                sku.precio_venta,
                                                            )}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() =>
                                            setProductoSeleccionado(null)
                                        }
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        className="flex-1"
                                        disabled={!skuSeleccionado}
                                        onClick={confirmarVariante}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Agregar
                                    </Button>
                                </div>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>

                {ultimaVenta && (
                    <Dialog open={true} onOpenChange={() => setUltimaVenta(null)}>
                        <DialogContent aria-describedby={undefined} className="sm:max-w-lg">
                            <DialogHeader className="px-6 pt-6">
                                <DialogTitle className="flex items-center gap-2">
                                    <Receipt className="h-5 w-5" />
                                    Venta Completada
                                </DialogTitle>
                            </DialogHeader>
                            <div className="space-y-4 overflow-y-auto px-6 pb-6" style={{ maxHeight: 'calc(85vh - 8rem)' }}>
                                <div className="border-b pb-4 text-center">
                                    <p className="text-sm text-muted-foreground">
                                        Número de Ticket
                                    </p>
                                    <p className="text-2xl font-bold">
                                        {ultimaVenta.numero}
                                    </p>
                                </div>

                                <div className="space-y-2 text-sm">
                                    {ultimaVenta.items.map((item) => (
                                        <div
                                            key={item.cartId}
                                            className="flex justify-between"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {item.nombre}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {item.cantidad} x{' '}
                                                    {formatCurrency(
                                                        item.precio_venta,
                                                    )}
                                                </p>
                                            </div>
                                            <p className="font-bold">
                                                {formatCurrency(
                                                    item.precio_venta * item.cantidad + (item.envase_retornable && item.envase_precio ? Math.max(0, item.cantidad - (item.cantidad_retornada || 0)) * item.envase_precio : 0),
                                                )}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                <div className="space-y-1 border-t pt-2 text-sm">
                                    <div className="flex justify-between">
                                        <span>Subtotal</span>
                                        <span>
                                            {formatCurrency(
                                                ultimaVenta.subtotal,
                                            )}
                                        </span>
                                    </div>
                                    {ultimaVenta.cupon && (
                                        <div className="flex justify-between text-orange-600">
                                            <span>Cupón: {ultimaVenta.cupon.codigo}</span>
                                            <span>
                                                -{formatCurrency(ultimaVenta.cupon.descuento)}
                                            </span>
                                        </div>
                                    )}
                                    {ultimaVenta.descuento > 0 && (
                                        <div className="flex justify-between text-orange-600">
                                            <span>Descuento</span>
                                            <span>
                                                -
                                                {formatCurrency(
                                                    ultimaVenta.descuento,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between">
                                        <span>IVA (19%)</span>
                                        <span>
                                            {formatCurrency(ultimaVenta.iva)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-t pt-2 text-lg font-bold">
                                        <span>TOTAL</span>
                                        <span className="text-emerald-600">
                                            {formatCurrency(ultimaVenta.total)}
                                        </span>
                                    </div>
                                </div>

                                <div className="text-center text-sm text-muted-foreground">
                                    <p>Método de pago: {ultimaVenta.metodoPago}</p>
                                    <p>
                                        Documento:{' '}
                                        {ultimaVenta.tipoDocumento
                                            .charAt(0)
                                            .toUpperCase() +
                                            ultimaVenta.tipoDocumento.slice(1)}
                                    </p>
                                </div>

                                {ultimaVenta.tipoDocumento !== 'cotizacion' &&
                                    ultimaVenta.ventaId && (
                                        <Button
                                            variant="default"
                                            className="w-full gap-2 bg-blue-600 font-bold hover:bg-blue-700"
                                            onClick={() => {
                                                setDteResultado(null);
                                                setEmitirDteOpen(true);
                                            }}
                                        >
                                            <FileText className="h-4 w-4" />
                                            Emitir DTE ({ultimaVenta.tipoDocumento === 'factura' ? 'Factura Electrónica' : 'Boleta Electrónica'})
                                        </Button>
                                    )}

                                <div className="flex gap-2 pt-4">
                                    <Button
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() => setUltimaVenta(null)}
                                    >
                                        Cerrar
                                    </Button>
                                    <Button
                                        className="flex-1"
                                        onClick={() => window.print()}
                                    >
                                        <Receipt className="mr-2 h-4 w-4" />
                                        Imprimir Ticket
                                    </Button>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                )}

                {/* Dialogo de confirmacion para emitir DTE */}
                <Dialog
                    open={emitirDteOpen}
                    onOpenChange={(open) => {
                        if (!emitiendoDte) {
                            setEmitirDteOpen(open);
                            if (!open) setDteResultado(null);
                        }
                    }}
                >
                    <DialogContent aria-describedby={undefined} className="w-[95vw] max-w-md sm:max-w-md overflow-y-auto p-6">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Emitir DTE
                            </DialogTitle>
                        </DialogHeader>

                        {dteResultado ? (
                            <div className="space-y-4">
                                <div
                                    className={`flex items-center gap-3 rounded-lg p-4 ${dteResultado.success
                                            ? 'bg-emerald-50 text-emerald-800'
                                            : 'bg-red-50 text-red-800'
                                        }`}
                                >
                                    {dteResultado.success ? (
                                        <CheckCircle2 className="h-6 w-6 shrink-0 text-emerald-500" />
                                    ) : (
                                        <AlertTriangle className="h-6 w-6 shrink-0 text-red-500" />
                                    )}
                                    <div>
                                        <p className="font-bold">
                                            {dteResultado.success
                                                ? 'DTE Emitido Correctamente'
                                                : 'Error al Emitir DTE'}
                                        </p>
                                        <p className="text-sm">
                                            {dteResultado.message}
                                        </p>
                                        {dteResultado.folio && (
                                            <p className="mt-1 text-sm font-bold">
                                                Folio: {dteResultado.folio} |{' '}
                                                Estado: {dteResultado.estado}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <Button
                                    className="w-full"
                                    onClick={() => {
                                        setEmitirDteOpen(false);
                                        setDteResultado(null);
                                        setUltimaVenta(null);
                                    }}
                                >
                                    Cerrar
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <p className="text-sm text-muted-foreground">
                                    Se emitirá un DTE tipo{' '}
                                    <strong>
                                        {ultimaVenta?.tipoDocumento === 'factura'
                                            ? 'Factura Electrónica (Tipo 33)'
                                            : 'Boleta Electrónica (Tipo 39)'}
                                    </strong>{' '}
                                    para esta venta. Se asignará un folio SII y se
                                    generará el XML firmado.
                                </p>
                                <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                                    <p>
                                        <span className="font-medium text-muted-foreground">
                                            Total:
                                        </span>{' '}
                                        <span className="font-bold">
                                            {formatCurrency(
                                                ultimaVenta?.total ?? 0,
                                            )}
                                        </span>
                                    </p>
                                    <p>
                                        <span className="font-medium text-muted-foreground">
                                            Documento:
                                        </span>{' '}
                                        {ultimaVenta?.tipoDocumento === 'factura'
                                            ? 'Factura'
                                            : 'Boleta'}
                                    </p>
                                    <p>
                                        <span className="font-medium text-muted-foreground">
                                            Ticket:
                                        </span>{' '}
                                        {ultimaVenta?.numero}
                                    </p>
                                </div>
                                <div className="flex flex-col gap-2 pt-2 sm:flex-row">
                                    <Button
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() => {
                                            setEmitirDteOpen(false);
                                            setDteResultado(null);
                                        }}
                                        disabled={emitiendoDte}
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        className="flex-1 gap-2 bg-blue-600 font-bold hover:bg-blue-700"
                                        onClick={async () => {
                                            if (!ultimaVenta?.ventaId) return;
                                            setEmitiendoDte(true);
                                            try {
                                                const res = await fetch(
                                                    `/pos/${ultimaVenta.ventaId}/emitir-dte`,
                                                    { method: 'POST' },
                                                );
                                                const data = await res.json();
                                                setDteResultado({
                                                    success: data.success,
                                                    message: data.message,
                                                    folio: data.dte?.folio,
                                                    estado: data.dte?.estado,
                                                });
                                                if (data.success) {
                                                    toast.success(
                                                        data.message,
                                                    );
                                                } else {
                                                    toast.error(data.message);
                                                }
                                            } catch {
                                                setDteResultado({
                                                    success: false,
                                                    message:
                                                        'Error de conexión al emitir DTE.',
                                                });
                                                toast.error(
                                                    'Error de conexión al emitir DTE.',
                                                );
                                            } finally {
                                                setEmitiendoDte(false);
                                            }
                                        }}
                                        disabled={emitiendoDte}
                                    >
                                        {emitiendoDte ? (
                                            <>
                                                <RefreshCw className="h-4 w-4 animate-spin" />
                                                Emitiendo...
                                            </>
                                        ) : (
                                            <>
                                                <FileText className="h-4 w-4" />
                                                Sí, Emitir DTE
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}

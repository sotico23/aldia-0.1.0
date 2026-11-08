import { Head, useForm } from '@inertiajs/react';
import type { Html5Qrcode } from 'html5-qrcode';
import {
    Barcode,
    Camera,
    Search,
    Trash2,
    ShoppingCart,
    Plus,
    Minus,
    CreditCard,
    Banknote,
    User as UserIcon,
    UserCheck,
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
import React, {
    useState,
    useRef,
    useEffect,
    useMemo,
    useCallback,
} from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    direccion: string | null;
    comuna: string | null;
    ciudad: string | null;
    region: string | null;
    telefono?: string | null;
}

interface DeliveryRate {
    id: number;
    calculation_type: 'comuna' | 'rango_km' | 'valor_fijo_km';
    commune: string | null;
    km_from: number | null;
    km_to: number | null;
    cost: number;
}

interface Almacen {
    id: number;
    nombre: string;
}

interface Empleado {
    id: number;
    nombre: string;
    apellido: string;
    cargo: string | null;
    user_id: number | null;
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
    envase_retornable?: boolean;
    envase_precio?: number;
    cantidad_retornada?: number;
    montoIngresado?: string;
}

function esProductoGrameable(item: CartItem): boolean {
    return (
        (item.medida_pesable ?? false) &&
        (item.unidad_medida === 'kg' || item.unidad_medida === 'lt')
    );
}

/**
 * Convierte el monto que arroja la balanza en la cantidad equivalente.
 * La balanza trabaja en gramos, por eso redondeamos a 3 decimales (0,001 kg).
 */
function cantidadDesdeMonto(monto: number, precioPorMedida: number): number {
    if (
        !Number.isFinite(monto) ||
        monto <= 0 ||
        !Number.isFinite(precioPorMedida) ||
        precioPorMedida <= 0
    ) {
        return 0;
    }

    return Math.max(0.001, Math.round((monto / precioPorMedida) * 1000) / 1000);
}

function montoDesdeCantidad(precioVenta: number, cantidad: number): number {
    return Math.round(precioVenta * cantidad);
}

/**
 * Para productos pesables el monto digitado por el cajero es la fuente de
 * verdad del subtotal. Así $1.500 siempre cobra $1.500, sin importar el
 * redondeo de los 3 decimales usados para descontar stock.
 */
function calcularSubtotalLinea(item: CartItem): number {
    if (esProductoGrameable(item)) {
        const monto = Number(item.montoIngresado);

        if (
            item.montoIngresado !== undefined &&
            item.montoIngresado !== '' &&
            Number.isFinite(monto) &&
            monto > 0
        ) {
            return Math.round(monto);
        }
    }

    return montoDesdeCantidad(item.precio_venta, item.cantidad);
}

function MontoPorMedida({
    item,
    onChange,
    onBlur,
    onCommit,
    autoFocus = false,
    onFocus,
}: {
    item: CartItem;
    onChange: (value: string) => void;
    onBlur: () => void;
    onCommit?: () => void;
    autoFocus?: boolean;
    onFocus?: () => void;
}) {
    const unidad = item.unidad_medida === 'kg' ? 'kg' : 'L';
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!autoFocus) {
            return;
        }

        inputRef.current?.focus();
        inputRef.current?.select();
    }, [autoFocus]);

    const montoMostrado =
        item.montoIngresado ?? String(calcularSubtotalLinea(item));

    return (
        <div className="border-t border-emerald-200/80 bg-emerald-50/70 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/20">
            <div className="mb-1 flex items-center justify-between gap-2">
                <Label
                    htmlFor={`monto-${item.cartId}`}
                    className="text-[10px] font-black tracking-wide text-emerald-700 uppercase dark:text-emerald-400"
                >
                    Monto de balanza
                </Label>
                <span className="text-[10px] font-medium text-muted-foreground">
                    {formatCurrency(item.precio_venta)} / {unidad}
                </span>
            </div>

            <div className="relative">
                <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-xl font-black text-emerald-700 dark:text-emerald-400">
                    $
                </span>
                <Input
                    ref={inputRef}
                    id={`monto-${item.cartId}`}
                    type="text"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    value={montoMostrado}
                    onChange={(event) =>
                        onChange(
                            event.target.value
                                .replace(/\D/g, '')
                                .replace(/^0+(?=\d)/, ''),
                        )
                    }
                    onFocus={onFocus}
                    onBlur={onBlur}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            inputRef.current?.blur();
                            onCommit?.();
                        }
                    }}
                    disabled={item.precio_venta <= 0}
                    className="h-12 border-emerald-300 bg-white pr-3 pl-8 text-right text-2xl font-black tabular-nums shadow-sm focus-visible:border-emerald-500 focus-visible:ring-emerald-500/30 dark:border-emerald-800 dark:bg-emerald-950/40"
                    aria-label={`Monto de balanza para ${item.nombre}`}
                    placeholder="0"
                />
            </div>

            <div className="mt-1.5 flex items-center justify-between text-[11px]">
                <span className="text-muted-foreground">Peso equivalente</span>
                <span className="font-semibold text-foreground tabular-nums">
                    ≈ {item.cantidad.toFixed(3)} {unidad}
                </span>
            </div>
        </div>
    );
}

function DeliveryControls({
    enabled,
    onEnabledChange,
    rates,
    rateId,
    onRateChange,
    distanceKm,
    onDistanceChange,
    manualCost,
    onManualCostChange,
    recipientName,
    onRecipientNameChange,
    phone,
    onPhoneChange,
    commune,
    onCommuneChange,
    address,
    onAddressChange,
    suffix,
}: {
    enabled: boolean;
    onEnabledChange: (enabled: boolean) => void;
    rates: DeliveryRate[];
    rateId: string;
    onRateChange: (rateId: string) => void;
    distanceKm: string;
    onDistanceChange: (distanceKm: string) => void;
    manualCost: string;
    onManualCostChange: (cost: string) => void;
    recipientName: string;
    onRecipientNameChange: (recipientName: string) => void;
    phone: string;
    onPhoneChange: (phone: string) => void;
    commune: string;
    onCommuneChange: (commune: string) => void;
    address: string;
    onAddressChange: (address: string) => void;
    suffix: string;
}) {
    const selectedRate = rates.find((rate) => String(rate.id) === rateId);

    return (
        <div className="space-y-2 border-t pt-3">
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`include-delivery-${suffix}`}
                    checked={enabled}
                    onCheckedChange={(checked) =>
                        onEnabledChange(checked === true)
                    }
                />
                <Label
                    htmlFor={`include-delivery-${suffix}`}
                    className="cursor-pointer text-sm font-medium"
                >
                    Incluir Despacho
                </Label>
            </div>
            {enabled && (
                <div className="space-y-3 rounded-lg bg-muted/30 p-3">
                    <div className="space-y-1">
                        <Label htmlFor={`delivery-rate-${suffix}`}>
                            Comuna o regla de kilómetros
                        </Label>
                        <Select
                            value={rateId || 'manual'}
                            onValueChange={(value) =>
                                onRateChange(value === 'manual' ? '' : value)
                            }
                        >
                            <SelectTrigger id={`delivery-rate-${suffix}`}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="manual">
                                    Ingreso manual
                                </SelectItem>
                                {rates.map((rate) => {
                                    const description =
                                        rate.calculation_type === 'comuna'
                                            ? rate.commune
                                            : rate.calculation_type ===
                                                'rango_km'
                                              ? `${rate.km_from} - ${rate.km_to} km`
                                              : 'Valor fijo por km';

                                    return (
                                        <SelectItem
                                            key={rate.id}
                                            value={String(rate.id)}
                                        >
                                            {description} —{' '}
                                            {formatCurrency(rate.cost)}
                                            {rate.calculation_type ===
                                                'valor_fijo_km' && '/km'}
                                        </SelectItem>
                                    );
                                })}
                            </SelectContent>
                        </Select>
                    </div>

                    {selectedRate?.calculation_type === 'valor_fijo_km' && (
                        <div className="space-y-1">
                            <Label htmlFor={`delivery-distance-${suffix}`}>
                                Distancia (km)
                            </Label>
                            <Input
                                id={`delivery-distance-${suffix}`}
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={distanceKm}
                                onChange={(event) =>
                                    onDistanceChange(event.target.value)
                                }
                                required
                            />
                        </div>
                    )}

                    {!selectedRate && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label htmlFor={`manual-distance-${suffix}`}>
                                    Distancia (km), opcional
                                </Label>
                                <Input
                                    id={`manual-distance-${suffix}`}
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={distanceKm}
                                    onChange={(event) =>
                                        onDistanceChange(event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor={`manual-cost-${suffix}`}>
                                    Monto del despacho
                                </Label>
                                <Input
                                    id={`manual-cost-${suffix}`}
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={manualCost}
                                    onChange={(event) =>
                                        onManualCostChange(event.target.value)
                                    }
                                    required
                                />
                            </div>
                        </div>
                    )}

                    <div className="space-y-1">
                        <Label htmlFor={`delivery-recipient-${suffix}`}>
                            Nombre del cliente (envío) *
                        </Label>
                        <Input
                            id={`delivery-recipient-${suffix}`}
                            value={recipientName}
                            onChange={(event) =>
                                onRecipientNameChange(event.target.value)
                            }
                            maxLength={255}
                            placeholder="Quién recibe el despacho"
                            required
                        />
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor={`delivery-phone-${suffix}`}>
                                Teléfono de contacto *
                            </Label>
                            <Input
                                id={`delivery-phone-${suffix}`}
                                type="tel"
                                value={phone}
                                onChange={(event) =>
                                    onPhoneChange(event.target.value)
                                }
                                maxLength={30}
                                placeholder="+56 9 1234 5678"
                                required
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor={`delivery-commune-${suffix}`}>
                                Comuna
                            </Label>
                            <Input
                                id={`delivery-commune-${suffix}`}
                                value={commune}
                                onChange={(event) =>
                                    onCommuneChange(event.target.value)
                                }
                                maxLength={120}
                                placeholder="Comuna de entrega"
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor={`delivery-address-${suffix}`}>
                            Dirección exacta de entrega *
                        </Label>
                        <Input
                            id={`delivery-address-${suffix}`}
                            value={address}
                            onChange={(event) =>
                                onAddressChange(event.target.value)
                            }
                            maxLength={255}
                            placeholder="Calle, número, depto/casa"
                            required
                        />
                    </div>
                </div>
            )}
        </div>
    );
}

export default function PosIndex({
    productos,
    clientes,
    almacenes = [],
    empleados = [],
    empleado_id = null,
    puede_seleccionar_cajero = true,
    cajero_actual = null,
    promociones = [],
    delivery_rates = [],
    iva_tasa = 0.19,
}: {
    productos: Producto[];
    clientes: Cliente[];
    almacenes?: Almacen[];
    empleados?: Empleado[];
    empleado_id?: number | null;
    puede_seleccionar_cajero?: boolean;
    cajero_actual?: { id: number | null; nombre: string } | null;
    promociones?: Promocion[];
    delivery_rates?: DeliveryRate[];
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
    const [cameraScannerOpen, setCameraScannerOpen] = useState(false);
    const [cameraScannerStatus, setCameraScannerStatus] = useState<
        'starting' | 'ready' | 'error'
    >('starting');
    const [cameraScannerError, setCameraScannerError] = useState<string | null>(
        null,
    );
    const cameraScannerRef = useRef<Html5Qrcode | null>(null);
    const cameraScanHandledRef = useRef(false);
    const [montoFocusId, setMontoFocusId] = useState<string | null>(null);
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
        hasDelivery: boolean;
        deliveryCost: number;
        deliveryRecipientName: string;
        deliveryPhone: string;
        deliveryAddress: string;
        deliveryCommune: string;
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
    const canEmitDte = hasPermission('ventas.facturacion-pos.create');
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
    const [hasDelivery, setHasDelivery] = useState(false);
    const [deliveryRateId, setDeliveryRateId] = useState('');
    const [deliveryDistanceKm, setDeliveryDistanceKm] = useState('');
    const [manualDeliveryCost, setManualDeliveryCost] = useState('');
    const [deliveryRecipientName, setDeliveryRecipientName] = useState('');
    const [deliveryPhone, setDeliveryPhone] = useState('');
    const [deliveryCommune, setDeliveryCommune] = useState('');
    const [deliveryAddress, setDeliveryAddress] = useState('');
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
    const [empleadoId, setEmpleadoId] = useState<string>(() => {
        if (empleado_id) {
            return String(empleado_id);
        }

        if (!puede_seleccionar_cajero) {
            return '';
        }

        return empleados.length > 0 ? String(empleados[0].id) : '';
    });
    const [codigoCupon, setCodigoCupon] = useState('');
    const [cuponValidando, setCuponValidando] = useState(false);
    const [cuponValido, setCuponValido] = useState<boolean | null>(null);
    const [cuponMensaje, setCuponMensaje] = useState('');
    const [cuponDescuento, setCuponDescuento] = useState(0);
    const [cuponAplicado, setCuponAplicado] = useState(false);
    const [descuentoManualActivo, setDescuentoManualActivo] = useState(false);
    const [tipoDescuentoManual, setTipoDescuentoManual] = useState<
        'fijo' | 'porcentaje'
    >('porcentaje');
    const [valorDescuentoManual, setValorDescuentoManual] = useState(0);
    const [incluyeIva, setIncluyeIva] = useState(true);
    const [mostrarModalPagos, setMostrarModalPagos] = useState(false);
    const [mostrandoMetodosPago, setMostrandoMetodosPago] = useState(false);
    const [cartOpen, setCartOpen] = useState(false);

    const handleClienteChange = (clienteIdSeleccionado: string) => {
        setClienteId(clienteIdSeleccionado);
        const cliente = clientes.find(
            (item) => String(item.id) === clienteIdSeleccionado,
        );
        setDeliveryRecipientName(cliente?.nombre ?? '');
        setDeliveryPhone(cliente?.telefono ?? '');
        setDeliveryCommune(cliente?.comuna ?? '');
        setDeliveryAddress(
            cliente
                ? [
                      cliente.direccion,
                      cliente.comuna,
                      cliente.ciudad,
                      cliente.region,
                  ]
                      .filter(Boolean)
                      .join(', ')
                : '',
        );
    };

    const selectedDeliveryRate = delivery_rates.find(
        (rate) => String(rate.id) === deliveryRateId,
    );

    const handleDeliveryRateChange = (rateIdSeleccionado: string) => {
        setDeliveryRateId(rateIdSeleccionado);
        const rate = delivery_rates.find(
            (item) => String(item.id) === rateIdSeleccionado,
        );

        if (rate?.calculation_type === 'comuna' && rate.commune) {
            setDeliveryCommune(rate.commune);
        }
    };

    const cajeroActualNombre = useMemo(() => {
        if (cajero_actual?.nombre) {
            return cajero_actual.nombre;
        }

        const actual = empleados.find(
            (empleado) => String(empleado.id) === empleadoId,
        );

        return actual
            ? `${actual.nombre} ${actual.apellido}`.trim()
            : 'Cajero actual';
    }, [cajero_actual, empleados, empleadoId]);
    const deliveryCost = useMemo(() => {
        if (!hasDelivery) return 0;
        if (!selectedDeliveryRate) return Number(manualDeliveryCost) || 0;
        if (selectedDeliveryRate.calculation_type === 'valor_fijo_km') {
            return Math.round(
                Number(selectedDeliveryRate.cost) *
                    (Number(deliveryDistanceKm) || 0),
            );
        }

        return Math.round(Number(selectedDeliveryRate.cost));
    }, [
        deliveryDistanceKm,
        hasDelivery,
        manualDeliveryCost,
        selectedDeliveryRate,
    ]);

    const subtotal = useMemo(() => {
        return cart.reduce((acc, item) => {
            let envaseCost = 0;
            if (item.envase_retornable && item.envase_precio) {
                const pend = Math.max(
                    0,
                    item.cantidad - (item.cantidad_retornada || 0),
                );
                envaseCost = pend * item.envase_precio;
            }
            return acc + calcularSubtotalLinea(item) + envaseCost;
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

        const csrfToken =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';

        fetch('/validar-cupon', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ codigo: codigoCupon, monto, items }),
        })
            .then(async (res) => {
                if (!res.ok) {
                    if (res.status === 419)
                        throw new Error(
                            'Sesión expirada. Por favor recarga la página.',
                        );
                    if (res.status === 429)
                        throw new Error(
                            'Demasiados intentos. Intenta en unos minutos.',
                        );
                    throw new Error('Error al validar el cupón.');
                }
                return res.json();
            })
            .then(
                (data: {
                    valido: boolean;
                    descuento?: number;
                    mensaje?: string;
                    productos_ids?: number[];
                    productos_nombres?: string[];
                }) => {
                    setCuponValido(data.valido);
                    setCuponMensaje(data.mensaje ?? '');
                    setCuponDescuento(data.valido ? (data.descuento ?? 0) : 0);
                    setCuponAplicado(data.valido);
                    if (data.valido) {
                        toast.success('Cupón aplicado correctamente');
                    }
                },
            )
            .catch((error) => {
                setCuponValido(null);
                setCuponMensaje(
                    error instanceof Error
                        ? error.message
                        : 'Error al validar el cupón.',
                );
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

    const buscarProductoSku = useCallback(
        (codigo: string) => {
            const codigoLimpio = codigo.trim();
            const producto =
                productos.find(
                    (p) =>
                        p.codigo?.trim().toLocaleLowerCase() ===
                            codigoLimpio.toLocaleLowerCase() ||
                        String(p.id) === codigoLimpio,
                ) ??
                productos.find((p) =>
                    p.skus.some(
                        (sku) =>
                            sku.sku.trim().toLocaleLowerCase() ===
                            codigoLimpio.toLocaleLowerCase(),
                    ),
                );

            if (producto) {
                if (producto.tiene_variantes && producto.skus.length > 0) {
                    const sku =
                        producto.skus.find(
                            (s) =>
                                s.sku.trim().toLocaleLowerCase() ===
                                codigoLimpio.toLocaleLowerCase(),
                        ) || producto.skus[0];
                    return { producto, sku };
                }
                return { producto, sku: null };
            }
            return null;
        },
        [productos],
    );

    const agregarAlCarrito = useCallback(
        (producto: Producto, sku: Sku | null = null) => {
            const precio = sku?.precio_venta ?? producto.precio_venta;
            const stock = sku?.stock ?? producto.stock;
            const esPesable =
                (producto.medida_pesable ?? false) &&
                (producto.unidad_medida === 'kg' ||
                    producto.unidad_medida === 'lt');
            const step = esPesable ? 0.1 : 1;
            const cartId = Math.random().toString(36).substr(2, 9);

            setCart((prev) => {
                const existingIndex = prev.findIndex(
                    (item) =>
                        item.productoId === producto.id &&
                        item.skuId === (sku?.id ?? null),
                );
                if (existingIndex >= 0) {
                    return prev.map((item, idx) => {
                        if (idx !== existingIndex) {
                            return item;
                        }

                        const nuevaCantidad =
                            Math.round((item.cantidad + step) * 1000) / 1000;

                        return {
                            ...item,
                            cantidad: nuevaCantidad,
                            montoIngresado: String(
                                montoDesdeCantidad(
                                    item.precio_venta,
                                    nuevaCantidad,
                                ),
                            ),
                            cantidad_retornada: item.envase_retornable
                                ? Math.min(
                                      Math.floor(nuevaCantidad),
                                      (item.cantidad_retornada || 0) +
                                          Math.ceil(step),
                                  )
                                : item.cantidad_retornada,
                        };
                    });
                }
                const skuDesc = sku
                    ? sku.variantes
                          .map((v) => `${v.variante}: ${v.valor}`)
                          .join(', ')
                    : null;
                return [
                    ...prev,
                    {
                        cartId,
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
                        contenido_por_unidad:
                            producto.contenido_por_unidad ?? 1,
                        medida_pesable: producto.medida_pesable,
                        montoIngresado: String(
                            montoDesdeCantidad(precio, step),
                        ),
                        envase_retornable: (producto as any).envase_retornable,
                        envase_precio: (producto as any).envase_precio,
                        // Para cilindros de gas (retornables con unidad_medida='unidad' y medida_pesable=false),
                        // cantidad_retornada se inicializa igual a la cantidad del producto
                        // El operador puede ajustar manualmente si el cliente no devuelve todos los envases
                        cantidad_retornada: (producto as any).envase_retornable
                            ? step
                            : 0,
                    },
                ];
            });

            // Al agregar un producto pesable, el foco pasa directo al monto de
            // balanza para que el cajero digite lo que marca la balanza.
            if (esPesable) {
                setMontoFocusId(cartId);
            }
        },
        [],
    );

    const procesarCodigoBarra = useCallback(
        (codigo: string) => {
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
        },
        [buscarProductoSku, agregarAlCarrito],
    );

    useEffect(() => {
        if (!cameraScannerOpen) {
            return;
        }

        let disposed = false;
        let scanner: Html5Qrcode | null = null;
        let startPromise: Promise<null> | null = null;

        const getCameraErrorMessage = (error: unknown): string => {
            const errorName = error instanceof DOMException ? error.name : '';
            const message =
                error instanceof Error ? error.message : String(error);
            const detail = `${errorName} ${message}`;

            if (/NotAllowedError|Permission|denied/i.test(detail)) {
                return 'Se denegó el permiso para usar la cámara. Habilítalo en la configuración del navegador e inténtalo nuevamente.';
            }

            if (
                /NotFoundError|OverconstrainedError|device not found|camera.*not found/i.test(
                    detail,
                )
            ) {
                return 'No se encontró una cámara trasera disponible en este dispositivo.';
            }

            if (/NotReadableError/i.test(detail)) {
                return 'No se pudo abrir la cámara porque está ocupada por otra aplicación. Ciérrala e inténtalo nuevamente.';
            }

            if (!window.isSecureContext) {
                return 'El acceso a la cámara requiere HTTPS o un entorno local seguro como localhost.';
            }

            return `No se pudo iniciar la cámara. ${message}`;
        };

        const iniciarEscaner = async () => {
            if (!window.isSecureContext) {
                setCameraScannerError(
                    'El acceso a la cámara requiere HTTPS o un entorno local seguro como localhost.',
                );
                setCameraScannerStatus('error');
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia) {
                setCameraScannerError(
                    'Este navegador no permite acceder a la cámara. Prueba con un navegador actualizado en el dispositivo.',
                );
                setCameraScannerStatus('error');
                return;
            }

            try {
                const { Html5Qrcode, Html5QrcodeSupportedFormats } =
                    await import('html5-qrcode');

                if (disposed) {
                    return;
                }

                scanner = new Html5Qrcode('pos-barcode-camera', {
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                        Html5QrcodeSupportedFormats.ITF,
                        Html5QrcodeSupportedFormats.CODABAR,
                    ],
                    verbose: false,
                });
                cameraScannerRef.current = scanner;

                startPromise = scanner.start(
                    { facingMode: { exact: 'environment' } },
                    {
                        fps: 10,
                        qrbox: { width: 260, height: 140 },
                        aspectRatio: 16 / 9,
                    },
                    (codigoDetectado) => {
                        if (disposed || cameraScanHandledRef.current) {
                            return;
                        }

                        cameraScanHandledRef.current = true;

                        void (async () => {
                            try {
                                if (scanner?.isScanning) {
                                    await scanner.stop();
                                }
                            } catch (error) {
                                console.error(
                                    'No se pudo detener el escáner después de leer el código.',
                                    error,
                                );
                            } finally {
                                if (!disposed) {
                                    cameraScannerRef.current = null;
                                    setCameraScannerOpen(false);
                                    navigator.vibrate?.(80);
                                    procesarCodigoBarra(codigoDetectado);
                                }
                            }
                        })();
                    },
                    () => {},
                );

                await startPromise;

                if (disposed) {
                    if (scanner.isScanning) {
                        await scanner.stop();
                    }

                    return;
                }

                setCameraScannerStatus('ready');
            } catch (error) {
                if (disposed) {
                    return;
                }

                setCameraScannerError(getCameraErrorMessage(error));
                setCameraScannerStatus('error');
            }
        };

        void iniciarEscaner();

        return () => {
            disposed = true;
            const scannerToStop = scanner;

            if (cameraScannerRef.current === scannerToStop) {
                cameraScannerRef.current = null;
            }

            void (async () => {
                try {
                    await startPromise?.catch(() => null);

                    if (scannerToStop?.isScanning) {
                        await scannerToStop.stop();
                    }
                } catch (error) {
                    console.error(
                        'No se pudo cerrar el escáner de cámara.',
                        error,
                    );
                }
            })();
        };
    }, [cameraScannerOpen, procesarCodigoBarra]);

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

    const esGrameable = esProductoGrameable;

    const actualizarCantidad = (cartId: string, delta: number) => {
        setCart((prev) =>
            prev.map((item) => {
                if (item.cartId !== cartId) {
                    return item;
                }

                const step = esGrameable(item) ? 0.1 : 1;
                const nuevaCantidad = Math.max(
                    step,
                    Math.round((item.cantidad + delta * step) * 1000) / 1000,
                );
                const maxRetornada = Math.floor(nuevaCantidad);

                return {
                    ...item,
                    cantidad: nuevaCantidad,
                    montoIngresado: String(
                        montoDesdeCantidad(item.precio_venta, nuevaCantidad),
                    ),
                    cantidad_retornada: item.envase_retornable
                        ? Math.min(
                              maxRetornada,
                              (item.cantidad_retornada || 0) +
                                  (delta > 0 ? Math.ceil(step) : 0),
                          )
                        : item.cantidad_retornada,
                };
            }),
        );
    };

    const setCantidadItem = (cartId: string, value: number) => {
        setCart((prev) =>
            prev.map((item) => {
                if (item.cartId !== cartId) {
                    return item;
                }

                const step = esGrameable(item) ? 0.001 : 1;
                const nuevaCantidad = Math.max(
                    step,
                    Math.round((value || 0) * 1000) / 1000,
                );
                const maxRetornada = Math.floor(nuevaCantidad);

                return {
                    ...item,
                    cantidad: nuevaCantidad,
                    montoIngresado: String(
                        montoDesdeCantidad(item.precio_venta, nuevaCantidad),
                    ),
                    cantidad_retornada: item.envase_retornable
                        ? Math.min(
                              maxRetornada,
                              Math.max(
                                  item.cantidad_retornada || 0,
                                  maxRetornada,
                              ),
                          )
                        : item.cantidad_retornada,
                };
            }),
        );
    };

    const setMontoItem = (cartId: string, value: string) => {
        setCart((prev) =>
            prev.map((item) => {
                if (item.cartId !== cartId) {
                    return item;
                }

                const monto = Number(value);
                const cantidad = cantidadDesdeMonto(monto, item.precio_venta);

                return {
                    ...item,
                    montoIngresado: value,
                    cantidad: cantidad > 0 ? cantidad : item.cantidad,
                };
            }),
        );
    };

    const normalizarMontoItem = (cartId: string) => {
        setCart((prev) =>
            prev.map((item) => {
                if (item.cartId !== cartId) {
                    return item;
                }

                const monto = Number(item.montoIngresado);
                const montoFinal =
                    Number.isFinite(monto) && monto > 0
                        ? Math.round(monto)
                        : calcularSubtotalLinea(item);
                const cantidad = cantidadDesdeMonto(
                    montoFinal,
                    item.precio_venta,
                );

                return {
                    ...item,
                    montoIngresado: String(montoFinal),
                    cantidad: cantidad > 0 ? cantidad : item.cantidad,
                };
            }),
        );
    };

    const setCantidadRetornada = (cartId: string, value: number) => {
        setCart((prev) =>
            prev.map((item) => {
                if (item.cartId === cartId) {
                    const max = Math.max(0, Math.floor(item.cantidad));
                    const cantidadRetornada = Math.min(
                        max,
                        Math.max(0, Math.floor(value || 0)),
                    );
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
            (acc, item) => acc + calcularSubtotalLinea(item),
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

    const descuentoManual = useMemo(() => {
        if (
            !descuentoManualActivo ||
            !valorDescuentoManual ||
            valorDescuentoManual <= 0
        )
            return 0;
        if (tipoDescuentoManual === 'porcentaje') {
            return subtotal * (Math.min(valorDescuentoManual, 100) / 100);
        }
        return Math.min(valorDescuentoManual, subtotal);
    }, [
        descuentoManualActivo,
        valorDescuentoManual,
        tipoDescuentoManual,
        subtotal,
    ]);
    const totalCuponAplicado = cuponAplicado ? cuponDescuento : 0;
    const baseImponible = useMemo(
        () =>
            Math.max(
                0,
                subtotal -
                    Math.round(
                        Math.min(
                            subtotal,
                            descuento + descuentoManual + totalCuponAplicado,
                        ),
                    ),
            ) + deliveryCost,
        [
            deliveryCost,
            descuento,
            descuentoManual,
            subtotal,
            totalCuponAplicado,
        ],
    );
    const iva = useMemo(
        () =>
            incluyeIva
                ? baseImponible - Math.round(baseImponible / (1 + iva_tasa))
                : 0,
        [baseImponible, iva_tasa, incluyeIva],
    );
    const total = baseImponible;

    const getStockForAlmacen = (
        producto: Producto,
        almacenId: string,
    ): number => {
        if (!almacenId) return producto.stock || 0;
        const inv = producto.inventarios?.find(
            (inv) => inv.almacen_id === parseInt(almacenId),
        );
        return inv?.cantidad || 0;
    };

    const getStockMinimoForAlmacen = (
        producto: Producto,
        almacenId: string,
    ): number => {
        if (!almacenId) return producto.stock_minimo || 0;
        const inv = producto.inventarios?.find(
            (inv) => inv.almacen_id === parseInt(almacenId),
        );
        return inv?.cantidad_minima || producto.stock_minimo || 0;
    };

    const productosFiltrados = useMemo(() => {
        let filtered = productos;

        // Filter by selected almacen (warehouse)
        if (almacenId) {
            filtered = filtered.filter((p) => {
                const inv = p.inventarios?.find(
                    (inv) => inv.almacen_id === parseInt(almacenId),
                );
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
        has_delivery: false,
        delivery_rate_id: null as number | null,
        delivery_cost: 0,
        delivery_distance_km: null as number | null,
        delivery_recipient_name: '',
        delivery_phone: '',
        delivery_address: '',
        delivery_commune: '',
        incluye_iva: true,
        descuento: 0,
        descuento_manual: 0,
        tipo_descuento_manual: null as 'fijo' | 'porcentaje' | null,
        valor_descuento_manual: 0,
        almacen_id: '',
        empleado_id: null as string | number | null,
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

        const itemPesableSinMonto = cart.find((item) => {
            if (
                !esProductoGrameable(item) ||
                item.montoIngresado === undefined
            ) {
                return false;
            }

            const monto = Number(item.montoIngresado);

            return !Number.isFinite(monto) || monto <= 0;
        });

        if (itemPesableSinMonto) {
            toast.error(
                `Ingrese el monto de balanza para ${itemPesableSinMonto.nombre}.`,
            );
            return;
        }

        if (hasDelivery && !deliveryRecipientName.trim()) {
            toast.error(
                'Ingrese el nombre del cliente que recibe el despacho.',
            );
            return;
        }
        if (hasDelivery && !deliveryPhone.trim()) {
            toast.error('Ingrese el teléfono de contacto del despacho.');
            return;
        }
        if (hasDelivery && !deliveryAddress.trim()) {
            toast.error('Ingrese la dirección de entrega.');
            return;
        }
        if (
            hasDelivery &&
            selectedDeliveryRate?.calculation_type === 'valor_fijo_km' &&
            Number(deliveryDistanceKm) <= 0
        ) {
            toast.error('Ingrese una distancia válida en kilómetros.');
            return;
        }
        if (
            hasDelivery &&
            !selectedDeliveryRate &&
            manualDeliveryCost.trim() === ''
        ) {
            toast.error('Ingrese el monto del despacho.');
            return;
        }

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
            subtotal: subtotal + deliveryCost,
            iva: iva,
            total: total,
            has_delivery: hasDelivery,
            delivery_rate_id:
                hasDelivery && deliveryRateId ? Number(deliveryRateId) : null,
            delivery_cost: deliveryCost,
            delivery_distance_km:
                hasDelivery && deliveryDistanceKm
                    ? Number(deliveryDistanceKm)
                    : null,
            delivery_recipient_name: hasDelivery ? deliveryRecipientName : '',
            delivery_phone: hasDelivery ? deliveryPhone : '',
            delivery_address: hasDelivery ? deliveryAddress : '',
            delivery_commune: hasDelivery ? deliveryCommune : '',
            incluye_iva: incluyeIva,
            descuento: descuento,
            descuento_manual: descuentoManual,
            tipo_descuento_manual:
                descuentoManualActivo && valorDescuentoManual > 0
                    ? tipoDescuentoManual
                    : null,
            valor_descuento_manual: descuentoManualActivo
                ? valorDescuentoManual
                : 0,
            almacen_id: almacenId,
            empleado_id: empleadoId || null,
            cupon_codigo: codigoCupon || undefined,
        });

        posForm.post('/pos', {
            preserveScroll: true,
            onStart: () => setProcesando(true),
            onFinish: () => setProcesando(false),
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
                    hasDelivery,
                    deliveryCost,
                    deliveryRecipientName,
                    deliveryPhone,
                    deliveryAddress,
                    deliveryCommune,
                    iva: iva,
                    total: total,
                    metodoPago: metodo,
                    tipoDocumento: tipoDocumento,
                    cupon:
                        cuponAplicado && cuponDescuento > 0
                            ? {
                                  codigo: codigoCupon,
                                  nombre: codigoCupon,
                                  descuento: cuponDescuento,
                              }
                            : null,
                });
                vaciarCarrito();
                limpiarCupon();
                posForm.reset();
                setHasDelivery(false);
                setDeliveryRateId('');
                setDeliveryDistanceKm('');
                setManualDeliveryCost('');
                const clienteSeleccionado = clientes.find(
                    (cliente) => String(cliente.id) === clienteId,
                );
                setDeliveryRecipientName(clienteSeleccionado?.nombre ?? '');
                setDeliveryPhone(clienteSeleccionado?.telefono ?? '');
                setDeliveryCommune(clienteSeleccionado?.comuna ?? '');
                setDeliveryAddress(
                    clienteSeleccionado
                        ? [
                              clienteSeleccionado.direccion,
                              clienteSeleccionado.comuna,
                              clienteSeleccionado.ciudad,
                              clienteSeleccionado.region,
                          ]
                              .filter(Boolean)
                              .join(', ')
                        : '',
                );
                setMostrandoMetodosPago(false);
                toast.success('Venta registrada exitosamente');
            },
            onError: (errors) => {
                // El backend es la fuente de verdad (lockForUpdate + transacciones)
                // Si hay error de stock, mostramos el mensaje del servidor
                const mensaje =
                    Object.values(errors).join(' ') ||
                    'Error al procesar la venta';
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
                        <Button
                            type="button"
                            variant="outline"
                            className="h-12 shrink-0 gap-2 px-3"
                            aria-label="Abrir lector de código de barras con cámara"
                            onClick={() => {
                                cameraScanHandledRef.current = false;
                                setCameraScannerError(null);
                                setCameraScannerStatus('starting');
                                setCameraScannerOpen(true);
                            }}
                        >
                            <Camera className="h-5 w-5" />
                            <span className="hidden sm:inline">Escanear</span>
                        </Button>
                    </div>

                    <div className="flex-1 overflow-y-auto pr-2">
                        {productosFiltrados.length === 0 ? (
                            <div className="flex h-full flex-col items-center justify-center text-muted-foreground">
                                <ShoppingCart className="mb-4 h-16 w-16 opacity-20" />
                                <p>No se encontraron productos.</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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
                                            {getStockForAlmacen(
                                                prod,
                                                almacenId,
                                            ) > 0 &&
                                                getStockForAlmacen(
                                                    prod,
                                                    almacenId,
                                                ) <=
                                                    getStockMinimoForAlmacen(
                                                        prod,
                                                        almacenId,
                                                    ) && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="absolute top-2 right-2 text-[10px]"
                                                    >
                                                        Bajo stock
                                                    </Badge>
                                                )}
                                            {getStockForAlmacen(
                                                prod,
                                                almacenId,
                                            ) === 0 && (
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
                                                {getStockForAlmacen(
                                                    prod,
                                                    almacenId,
                                                ) > 0
                                                    ? getStockForAlmacen(
                                                          prod,
                                                          almacenId,
                                                      )
                                                    : 'Sin stock'}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <div className="hidden w-[400px] flex-col bg-muted/10 lg:flex">
                    <div className="space-y-4 border-b bg-background p-4 shadow-sm">
                        <div className="flex items-center gap-2">
                            <UserIcon className="h-4 w-4 text-muted-foreground" />
                            <Select
                                value={clienteId}
                                onValueChange={handleClienteChange}
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

                        <div className="space-y-2">
                            <Label className="flex items-center gap-1 text-xs font-black text-primary/70 uppercase">
                                <UserCheck className="h-3.5 w-3.5" />
                                Cajero / Vendedor
                            </Label>
                            {puede_seleccionar_cajero ? (
                                <Select
                                    value={empleadoId || undefined}
                                    onValueChange={setEmpleadoId}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="Seleccionar Cajero" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {empleados.map((empleado) => (
                                            <SelectItem
                                                key={empleado.id}
                                                value={String(empleado.id)}
                                            >
                                                {empleado.nombre}{' '}
                                                {empleado.apellido}
                                                {empleado.cargo
                                                    ? ` — ${empleado.cargo}`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <div className="flex h-9 items-center gap-2 rounded-md border bg-muted/50 px-3 text-sm font-semibold">
                                    <UserCheck className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                    <span className="truncate">
                                        {cajeroActualNombre}
                                    </span>
                                    <Badge
                                        variant="secondary"
                                        className="ml-auto shrink-0 text-[10px]"
                                    >
                                        Bloqueado
                                    </Badge>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-1 flex-col gap-2 overflow-y-auto p-4">
                        {cart.length > 0 && (
                            <div className="flex items-center justify-between px-1">
                                <span className="text-xs font-medium text-muted-foreground">
                                    {cart.length}{' '}
                                    {cart.length === 1
                                        ? 'producto'
                                        : 'productos'}
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
                                                {esGrameable(item) ? (
                                                    <>
                                                        {formatCurrency(
                                                            item.precio_venta,
                                                        )}{' '}
                                                        / {item.unidad_medida}
                                                        <span className="ml-1 text-[10px]">
                                                            ·{' '}
                                                            {item.cantidad.toFixed(
                                                                3,
                                                            )}{' '}
                                                            {item.unidad_medida}
                                                        </span>
                                                    </>
                                                ) : (
                                                    <>
                                                        {formatCurrency(
                                                            item.precio_venta,
                                                        )}{' '}
                                                        x {item.cantidad}
                                                        {(item.peso_base ?? 0) >
                                                            0 ||
                                                        (item.contenido_por_unidad ??
                                                            1) !== 1 ? (
                                                            <span className="ml-1 text-[10px]">
                                                                (
                                                                {(
                                                                    item.cantidad *
                                                                        (item.contenido_por_unidad ??
                                                                            1) +
                                                                    item.cantidad *
                                                                        (item.peso_base ??
                                                                            0)
                                                                ).toFixed(
                                                                    3,
                                                                )}{' '}
                                                                {
                                                                    item.unidad_medida
                                                                }{' '}
                                                                reales)
                                                            </span>
                                                        ) : null}
                                                    </>
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
                                                    calcularSubtotalLinea(
                                                        item,
                                                    ) +
                                                        (item.envase_retornable &&
                                                        item.envase_precio
                                                            ? Math.max(
                                                                  0,
                                                                  item.cantidad -
                                                                      (item.cantidad_retornada ||
                                                                          0),
                                                              ) *
                                                              item.envase_precio
                                                            : 0),
                                                )}
                                            </div>
                                            <div className="mt-2 flex items-center justify-end gap-1">
                                                {!esGrameable(item) && (
                                                    <>
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
                                                            step="1"
                                                            min="1"
                                                            value={
                                                                item.cantidad
                                                            }
                                                            onChange={(e) =>
                                                                setCantidadItem(
                                                                    item.cartId,
                                                                    parseFloat(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0,
                                                                )
                                                            }
                                                            className="h-7 w-16 [appearance:textfield] text-center text-xs font-bold [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                        />
                                                        <span className="text-[8px] uppercase">
                                                            {item.unidad_medida ===
                                                                'kg' ||
                                                            item.unidad_medida ===
                                                                'lt'
                                                                ? item.unidad_medida
                                                                : 'u'}
                                                        </span>
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
                                                    </>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className={
                                                        esGrameable(item)
                                                            ? 'h-7 w-7 text-destructive hover:bg-destructive/10'
                                                            : 'ml-2 h-7 w-7 text-destructive hover:bg-destructive/10'
                                                    }
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
                                    {esGrameable(item) && (
                                        <MontoPorMedida
                                            item={item}
                                            autoFocus={
                                                montoFocusId === item.cartId
                                            }
                                            onFocus={() =>
                                                setMontoFocusId(null)
                                            }
                                            onChange={(value) =>
                                                setMontoItem(item.cartId, value)
                                            }
                                            onBlur={() =>
                                                normalizarMontoItem(item.cartId)
                                            }
                                            onCommit={() =>
                                                inputRef.current?.focus()
                                            }
                                        />
                                    )}
                                    {item.envase_retornable && (
                                        <div className="flex items-center justify-between gap-2 border-t bg-orange-50/50 p-2 dark:bg-orange-900/10">
                                            <div className="text-[10px] leading-tight font-semibold text-muted-foreground uppercase">
                                                Envases Devueltos
                                                <br />
                                                <span className="text-[9px] text-orange-500 normal-case">
                                                    Faltantes:{' '}
                                                    {Math.max(
                                                        0,
                                                        item.cantidad -
                                                            (item.cantidad_retornada ||
                                                                0),
                                                    )}{' '}
                                                    se cobran a{' '}
                                                    {formatCurrency(
                                                        item.envase_precio || 0,
                                                    )}{' '}
                                                    c/u
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 rounded-full bg-muted"
                                                    onClick={() =>
                                                        setCantidadRetornada(
                                                            item.cartId,
                                                            Math.max(
                                                                0,
                                                                (item.cantidad_retornada ||
                                                                    0) - 1,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Minus className="h-3 w-3" />
                                                </Button>
                                                <Input
                                                    type="number"
                                                    value={
                                                        item.cantidad_retornada ===
                                                        undefined
                                                            ? 0
                                                            : item.cantidad_retornada
                                                    }
                                                    onChange={(e) =>
                                                        setCantidadRetornada(
                                                            item.cartId,
                                                            parseFloat(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="h-7 w-16 [appearance:textfield] p-1 text-center text-xs [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                    min="0"
                                                    max={Math.ceil(
                                                        item.cantidad,
                                                    )}
                                                />
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 rounded-full bg-muted"
                                                    onClick={() =>
                                                        setCantidadRetornada(
                                                            item.cartId,
                                                            Math.min(
                                                                Math.ceil(
                                                                    item.cantidad,
                                                                ),
                                                                (item.cantidad_retornada ||
                                                                    0) + 1,
                                                            ),
                                                        )
                                                    }
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
                                    <span>
                                        -{formatCurrency(cuponDescuento)}
                                    </span>
                                </div>
                            )}
                            {descuentoManual > 0 && (
                                <div className="flex justify-between text-sm font-medium text-purple-600">
                                    <span>
                                        Dto. Manual (
                                        {tipoDescuentoManual === 'porcentaje'
                                            ? `${valorDescuentoManual}%`
                                            : `$${formatCurrency(valorDescuentoManual)}`}
                                        )
                                    </span>
                                    <span>
                                        -{formatCurrency(descuentoManual)}
                                    </span>
                                </div>
                            )}
                            <DeliveryControls
                                enabled={hasDelivery}
                                onEnabledChange={setHasDelivery}
                                rates={delivery_rates}
                                rateId={deliveryRateId}
                                onRateChange={handleDeliveryRateChange}
                                distanceKm={deliveryDistanceKm}
                                onDistanceChange={setDeliveryDistanceKm}
                                manualCost={manualDeliveryCost}
                                onManualCostChange={setManualDeliveryCost}
                                recipientName={deliveryRecipientName}
                                onRecipientNameChange={setDeliveryRecipientName}
                                phone={deliveryPhone}
                                onPhoneChange={setDeliveryPhone}
                                commune={deliveryCommune}
                                onCommuneChange={setDeliveryCommune}
                                address={deliveryAddress}
                                onAddressChange={setDeliveryAddress}
                                suffix="desktop"
                            />
                            {hasDelivery && (
                                <div className="flex justify-between text-sm font-medium text-emerald-600">
                                    <span>Despacho</span>
                                    <span>{formatCurrency(deliveryCost)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-sm font-medium text-muted-foreground">
                                <span>
                                    IVA ({(iva_tasa * 100).toFixed(0)}%)
                                </span>
                                <span>{formatCurrency(iva)}</span>
                            </div>
                            <div className="flex items-center gap-2 py-1">
                                <Checkbox
                                    id="incluye-iva"
                                    checked={incluyeIva}
                                    onCheckedChange={(checked) =>
                                        setIncluyeIva(!!checked)
                                    }
                                />
                                <Label
                                    htmlFor="incluye-iva"
                                    className="cursor-pointer text-xs font-medium text-muted-foreground"
                                >
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
                                        if (!checked)
                                            setValorDescuentoManual(0);
                                    }}
                                />
                                <Label
                                    htmlFor="descuento-manual"
                                    className="cursor-pointer text-sm font-medium"
                                >
                                    Descuento manual
                                </Label>
                            </div>
                            {descuentoManualActivo && (
                                <div className="flex items-center gap-2">
                                    <div className="flex-1">
                                        <Input
                                            type="number"
                                            min="0"
                                            step={
                                                tipoDescuentoManual ===
                                                'porcentaje'
                                                    ? '1'
                                                    : '0.01'
                                            }
                                            placeholder={
                                                tipoDescuentoManual ===
                                                'porcentaje'
                                                    ? '% descuento'
                                                    : 'Monto descuento'
                                            }
                                            value={valorDescuentoManual || ''}
                                            onChange={(e) =>
                                                setValorDescuentoManual(
                                                    Number(e.target.value),
                                                )
                                            }
                                            className="h-9 text-sm"
                                            disabled={procesando}
                                        />
                                    </div>
                                    <Select
                                        value={tipoDescuentoManual}
                                        onValueChange={(
                                            v: 'fijo' | 'porcentaje',
                                        ) => setTipoDescuentoManual(v)}
                                    >
                                        <SelectTrigger className="h-9 w-[130px] text-sm">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="porcentaje">
                                                %
                                            </SelectItem>
                                            <SelectItem value="fijo">
                                                Precio fijo
                                            </SelectItem>
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
                                        setCodigoCupon(
                                            e.target.value.toUpperCase(),
                                        );
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
                                    className={
                                        'h-9 flex-1 text-sm ' +
                                        (cuponAplicado
                                            ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20'
                                            : cuponValido === false
                                              ? 'border-red-500'
                                              : '')
                                    }
                                    disabled={procesando || cuponAplicado}
                                />
                                {!cuponAplicado ? (
                                    <Button
                                        id="validate-coupon-btn"
                                        variant="default"
                                        size="sm"
                                        className="h-9 shrink-0 bg-violet-600 text-xs font-bold uppercase hover:bg-violet-700"
                                        onClick={validarCupon}
                                        disabled={
                                            procesando ||
                                            cuponValidando ||
                                            !codigoCupon ||
                                            codigoCupon.length < 3
                                        }
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
                                <p
                                    className={
                                        'flex items-center gap-1 text-xs ' +
                                        (cuponValido
                                            ? 'text-emerald-600'
                                            : 'text-red-500')
                                    }
                                >
                                    {cuponValido ? (
                                        <>
                                            <CheckCircle2 className="h-3 w-3 shrink-0" />
                                            {cuponMensaje}
                                        </>
                                    ) : (
                                        <>
                                            <AlertTriangle className="h-3 w-3 shrink-0" />
                                            {cuponMensaje}
                                        </>
                                    )}
                                </p>
                            )}
                        </div>

                        <div className="space-y-3">
                            {!mostrandoMetodosPago ? (
                                <>
                                    <Button
                                        className="flex h-16 w-full items-center justify-center gap-2 bg-emerald-600 text-lg font-bold shadow-lg shadow-emerald-500/20 hover:bg-emerald-700"
                                        disabled={
                                            cart.length === 0 ||
                                            posForm.processing
                                        }
                                        onClick={() =>
                                            setMostrandoMetodosPago(true)
                                        }
                                    >
                                        <CreditCard className="h-6 w-6" />
                                        <span>Procesar Venta</span>
                                        <span className="font-mono text-xl">
                                            {formatCurrency(total)}
                                        </span>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        className="h-12 w-full bg-gray-100 font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                        disabled={
                                            cart.length === 0 ||
                                            posForm.processing
                                        }
                                        onClick={vaciarCarrito}
                                    >
                                        <Trash2 className="mr-2 h-5 w-5" />
                                        <span>Limpiar Caja</span>
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <Button
                                            className="h-16 flex-col gap-1 bg-emerald-600 shadow-lg shadow-emerald-500/20 hover:bg-emerald-700"
                                            disabled={
                                                cart.length === 0 ||
                                                posForm.processing
                                            }
                                            onClick={() =>
                                                procesarPago('tarjeta')
                                            }
                                        >
                                            <CreditCard className="h-6 w-6" />
                                            <span className="text-[10px] font-bold uppercase">
                                                Tarjeta
                                            </span>
                                        </Button>
                                        <Button
                                            className="h-16 flex-col gap-1 bg-emerald-600 shadow-lg shadow-emerald-500/20 hover:bg-emerald-700"
                                            disabled={
                                                cart.length === 0 ||
                                                posForm.processing
                                            }
                                            onClick={() =>
                                                procesarPago('efectivo')
                                            }
                                        >
                                            <Banknote className="h-6 w-6" />
                                            <span className="text-[10px] font-bold uppercase">
                                                Efectivo
                                            </span>
                                        </Button>
                                    </div>

                                    <Button
                                        className="mt-3 h-12 w-full bg-slate-600 hover:bg-slate-700"
                                        disabled={
                                            cart.length === 0 ||
                                            posForm.processing
                                        }
                                        onClick={() =>
                                            setMostrarModalPagos(true)
                                        }
                                    >
                                        <Wallet className="mr-2 h-5 w-5" />
                                        <span className="font-bold uppercase">
                                            Otros Métodos
                                        </span>
                                    </Button>

                                    <Button
                                        variant="ghost"
                                        className="mt-2 h-10 w-full text-sm text-muted-foreground hover:text-foreground"
                                        onClick={() =>
                                            setMostrandoMetodosPago(false)
                                        }
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
                    <SheetContent
                        aria-describedby={undefined}
                        side="right"
                        className="flex w-full flex-col p-0 sm:max-w-md"
                    >
                        <SheetHeader className="border-b px-4 py-3">
                            <SheetTitle>Carrito de Compras</SheetTitle>
                        </SheetHeader>
                        <div className="flex flex-1 flex-col bg-muted/10">
                            <div className="space-y-4 border-b bg-background p-4 shadow-sm">
                                <div className="flex items-center gap-2">
                                    <UserIcon className="h-4 w-4 text-muted-foreground" />
                                    <Select
                                        value={clienteId}
                                        onValueChange={handleClienteChange}
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
                                        variant={
                                            tipoDocumento === 'boleta'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        className="flex-1"
                                        onClick={() =>
                                            setTipoDocumento('boleta')
                                        }
                                    >
                                        <Receipt className="mr-2 h-4 w-4" />{' '}
                                        Boleta
                                    </Button>
                                    <Button
                                        variant={
                                            tipoDocumento === 'factura'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        className="flex-1"
                                        onClick={() =>
                                            setTipoDocumento('factura')
                                        }
                                    >
                                        <FileText className="mr-2 h-4 w-4" />{' '}
                                        Factura
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

                                <div className="space-y-2">
                                    <Label className="flex items-center gap-1 text-xs font-black text-primary/70 uppercase">
                                        <UserCheck className="h-3.5 w-3.5" />
                                        Cajero / Vendedor
                                    </Label>
                                    {puede_seleccionar_cajero ? (
                                        <Select
                                            value={empleadoId || undefined}
                                            onValueChange={setEmpleadoId}
                                        >
                                            <SelectTrigger className="h-9">
                                                <SelectValue placeholder="Seleccionar Cajero" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {empleados.map((empleado) => (
                                                    <SelectItem
                                                        key={empleado.id}
                                                        value={String(
                                                            empleado.id,
                                                        )}
                                                    >
                                                        {empleado.nombre}{' '}
                                                        {empleado.apellido}
                                                        {empleado.cargo
                                                            ? ` — ${empleado.cargo}`
                                                            : ''}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <div className="flex h-9 items-center gap-2 rounded-md border bg-muted/50 px-3 text-sm font-semibold">
                                            <UserCheck className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                            <span className="truncate">
                                                {cajeroActualNombre}
                                            </span>
                                            <Badge
                                                variant="secondary"
                                                className="ml-auto shrink-0 text-[10px]"
                                            >
                                                Bloqueado
                                            </Badge>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="flex flex-1 flex-col gap-2 overflow-y-auto p-4">
                                {cart.length > 0 && (
                                    <div className="flex items-center justify-between px-1">
                                        <span className="text-xs font-medium text-muted-foreground">
                                            {cart.length}{' '}
                                            {cart.length === 1
                                                ? 'producto'
                                                : 'productos'}
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
                                                        {esGrameable(item) ? (
                                                            <>
                                                                {formatCurrency(
                                                                    item.precio_venta,
                                                                )}{' '}
                                                                /{' '}
                                                                {
                                                                    item.unidad_medida
                                                                }
                                                                <span className="ml-1 text-[10px]">
                                                                    ·{' '}
                                                                    {item.cantidad.toFixed(
                                                                        3,
                                                                    )}{' '}
                                                                    {
                                                                        item.unidad_medida
                                                                    }
                                                                </span>
                                                            </>
                                                        ) : (
                                                            <>
                                                                {formatCurrency(
                                                                    item.precio_venta,
                                                                )}{' '}
                                                                x{' '}
                                                                {item.cantidad}
                                                            </>
                                                        )}
                                                        {item.stock > 0 &&
                                                            item.stock <= 5 && (
                                                                <span className="ml-1 text-orange-500">
                                                                    (Stock:{' '}
                                                                    {item.stock}
                                                                    )
                                                                </span>
                                                            )}
                                                    </div>
                                                </div>
                                                <div className="flex flex-col items-end justify-between text-right">
                                                    <div className="font-black text-emerald-600">
                                                        {formatCurrency(
                                                            calcularSubtotalLinea(
                                                                item,
                                                            ) +
                                                                (item.envase_retornable &&
                                                                item.envase_precio
                                                                    ? Math.max(
                                                                          0,
                                                                          item.cantidad -
                                                                              (item.cantidad_retornada ||
                                                                                  0),
                                                                      ) *
                                                                      item.envase_precio
                                                                    : 0),
                                                        )}
                                                    </div>
                                                    <div className="mt-2 flex items-center justify-end gap-1">
                                                        {!esGrameable(item) && (
                                                            <>
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
                                                                    step="1"
                                                                    min="1"
                                                                    value={
                                                                        item.cantidad
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setCantidadItem(
                                                                            item.cartId,
                                                                            parseFloat(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            ) ||
                                                                                0,
                                                                        )
                                                                    }
                                                                    className="h-7 w-16 [appearance:textfield] text-center text-xs font-bold [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                                />
                                                                <span className="text-[8px] uppercase">
                                                                    {item.unidad_medida ===
                                                                        'kg' ||
                                                                    item.unidad_medida ===
                                                                        'lt'
                                                                        ? item.unidad_medida
                                                                        : 'u'}
                                                                </span>
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
                                                            </>
                                                        )}
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className={
                                                                esGrameable(
                                                                    item,
                                                                )
                                                                    ? 'h-7 w-7 text-destructive hover:bg-destructive/10'
                                                                    : 'ml-2 h-7 w-7 text-destructive hover:bg-destructive/10'
                                                            }
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
                                            {esGrameable(item) && (
                                                <MontoPorMedida
                                                    item={item}
                                                    autoFocus={
                                                        montoFocusId ===
                                                        item.cartId
                                                    }
                                                    onFocus={() =>
                                                        setMontoFocusId(null)
                                                    }
                                                    onChange={(value) =>
                                                        setMontoItem(
                                                            item.cartId,
                                                            value,
                                                        )
                                                    }
                                                    onBlur={() =>
                                                        normalizarMontoItem(
                                                            item.cartId,
                                                        )
                                                    }
                                                    onCommit={() =>
                                                        inputRef.current?.focus()
                                                    }
                                                />
                                            )}
                                            {item.envase_retornable && (
                                                <div className="flex items-center justify-between gap-2 border-t bg-orange-50/50 p-2 dark:bg-orange-900/10">
                                                    <div className="text-[10px] leading-tight font-semibold text-muted-foreground uppercase">
                                                        Envases Devueltos
                                                        <br />
                                                        <span className="text-[9px] text-orange-500 normal-case">
                                                            Faltantes:{' '}
                                                            {Math.max(
                                                                0,
                                                                item.cantidad -
                                                                    (item.cantidad_retornada ||
                                                                        0),
                                                            )}{' '}
                                                            se cobran a{' '}
                                                            {formatCurrency(
                                                                item.envase_precio ||
                                                                    0,
                                                            )}{' '}
                                                            c/u
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-7 w-7 rounded-full bg-muted"
                                                            onClick={() =>
                                                                setCantidadRetornada(
                                                                    item.cartId,
                                                                    Math.max(
                                                                        0,
                                                                        (item.cantidad_retornada ||
                                                                            0) -
                                                                            1,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <Minus className="h-3 w-3" />
                                                        </Button>
                                                        <Input
                                                            type="number"
                                                            value={
                                                                item.cantidad_retornada ===
                                                                undefined
                                                                    ? 0
                                                                    : item.cantidad_retornada
                                                            }
                                                            onChange={(e) =>
                                                                setCantidadRetornada(
                                                                    item.cartId,
                                                                    parseFloat(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                                )
                                                            }
                                                            className="h-7 w-16 [appearance:textfield] p-1 text-center text-xs [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                            min="0"
                                                            max={Math.ceil(
                                                                item.cantidad,
                                                            )}
                                                        />
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-7 w-7 rounded-full bg-muted"
                                                            onClick={() =>
                                                                setCantidadRetornada(
                                                                    item.cartId,
                                                                    Math.min(
                                                                        Math.ceil(
                                                                            item.cantidad,
                                                                        ),
                                                                        (item.cantidad_retornada ||
                                                                            0) +
                                                                            1,
                                                                    ),
                                                                )
                                                            }
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
                                            <span>
                                                -{formatCurrency(descuento)}
                                            </span>
                                        </div>
                                    )}
                                    {cuponAplicado && cuponDescuento > 0 && (
                                        <div className="flex justify-between text-sm font-medium text-emerald-600">
                                            <span>Dto. Cupón</span>
                                            <span>
                                                -
                                                {formatCurrency(cuponDescuento)}
                                            </span>
                                        </div>
                                    )}
                                    <DeliveryControls
                                        enabled={hasDelivery}
                                        onEnabledChange={setHasDelivery}
                                        rates={delivery_rates}
                                        rateId={deliveryRateId}
                                        onRateChange={handleDeliveryRateChange}
                                        distanceKm={deliveryDistanceKm}
                                        onDistanceChange={setDeliveryDistanceKm}
                                        manualCost={manualDeliveryCost}
                                        onManualCostChange={
                                            setManualDeliveryCost
                                        }
                                        recipientName={deliveryRecipientName}
                                        onRecipientNameChange={
                                            setDeliveryRecipientName
                                        }
                                        phone={deliveryPhone}
                                        onPhoneChange={setDeliveryPhone}
                                        commune={deliveryCommune}
                                        onCommuneChange={setDeliveryCommune}
                                        address={deliveryAddress}
                                        onAddressChange={setDeliveryAddress}
                                        suffix="mobile"
                                    />
                                    {hasDelivery && (
                                        <div className="flex justify-between text-sm font-medium text-emerald-600">
                                            <span>Despacho</span>
                                            <span>
                                                {formatCurrency(deliveryCost)}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-sm font-medium text-muted-foreground">
                                        <span>
                                            IVA ({(iva_tasa * 100).toFixed(0)}%)
                                        </span>
                                        <span>{formatCurrency(iva)}</span>
                                    </div>
                                    <div className="flex items-center gap-2 py-1">
                                        <Checkbox
                                            id="incluye-iva-mobile"
                                            checked={incluyeIva}
                                            onCheckedChange={(checked) =>
                                                setIncluyeIva(!!checked)
                                            }
                                        />
                                        <Label
                                            htmlFor="incluye-iva-mobile"
                                            className="cursor-pointer text-xs font-medium text-muted-foreground"
                                        >
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

                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id="coupon-code-input-mobile"
                                            placeholder="Código cupón"
                                            value={codigoCupon}
                                            onChange={(e) => {
                                                setCodigoCupon(
                                                    e.target.value.toUpperCase(),
                                                );
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
                                            className={
                                                'h-9 flex-1 text-sm ' +
                                                (cuponAplicado
                                                    ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20'
                                                    : cuponValido === false
                                                      ? 'border-red-500'
                                                      : '')
                                            }
                                            disabled={
                                                procesando || cuponAplicado
                                            }
                                        />
                                        {!cuponAplicado ? (
                                            <Button
                                                id="validate-coupon-btn-mobile"
                                                variant="default"
                                                size="sm"
                                                className="h-9 shrink-0 bg-violet-600 text-xs font-bold uppercase hover:bg-violet-700"
                                                onClick={validarCupon}
                                                disabled={
                                                    procesando ||
                                                    cuponValidando ||
                                                    !codigoCupon ||
                                                    codigoCupon.length < 3
                                                }
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
                                        <p
                                            className={
                                                'flex items-center gap-1 text-xs ' +
                                                (cuponValido
                                                    ? 'text-emerald-600'
                                                    : 'text-red-500')
                                            }
                                        >
                                            {cuponValido ? (
                                                <>
                                                    <CheckCircle2 className="h-3 w-3 shrink-0" />
                                                    {cuponMensaje}
                                                </>
                                            ) : (
                                                <>
                                                    <AlertTriangle className="h-3 w-3 shrink-0" />
                                                    {cuponMensaje}
                                                </>
                                            )}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <Button
                                        className="h-16 flex-col gap-1 bg-emerald-600 shadow-lg shadow-emerald-500/20 hover:bg-emerald-700"
                                        disabled={
                                            cart.length === 0 || procesando
                                        }
                                        onClick={() => {
                                            procesarPago('tarjeta');
                                            setCartOpen(false);
                                        }}
                                    >
                                        <CreditCard className="h-6 w-6" />
                                        <span className="text-[10px] font-bold uppercase">
                                            Tarjeta
                                        </span>
                                    </Button>
                                    <Button
                                        className="h-16 flex-col gap-1 bg-emerald-600 shadow-lg shadow-emerald-500/20 hover:bg-emerald-700"
                                        disabled={
                                            cart.length === 0 || procesando
                                        }
                                        onClick={() => {
                                            procesarPago('efectivo');
                                            setCartOpen(false);
                                        }}
                                    >
                                        <Banknote className="h-6 w-6" />
                                        <span className="text-[10px] font-bold uppercase">
                                            Efectivo
                                        </span>
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
                                    <span className="font-bold uppercase">
                                        Otros Métodos
                                    </span>
                                </Button>
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>

                <Dialog
                    open={cameraScannerOpen}
                    onOpenChange={setCameraScannerOpen}
                >
                    <DialogContent
                        aria-describedby={undefined}
                        className="w-[95vw] max-w-lg gap-4 p-4 sm:p-6"
                    >
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Camera className="h-5 w-5" />
                                Escanear código de barras
                            </DialogTitle>
                        </DialogHeader>

                        <div
                            id="pos-barcode-camera"
                            className="min-h-64 w-full overflow-hidden rounded-xl bg-black [&_video]:h-full [&_video]:w-full [&_video]:object-cover"
                        />

                        {cameraScannerError ? (
                            <div
                                role="alert"
                                className="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
                            >
                                {cameraScannerError}
                            </div>
                        ) : (
                            <p
                                className="text-center text-sm text-muted-foreground"
                                aria-live="polite"
                            >
                                {cameraScannerStatus === 'starting'
                                    ? 'Iniciando cámara trasera…'
                                    : 'Centra el código de barras dentro del recuadro.'}
                            </p>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                onClick={() => setCameraScannerOpen(false)}
                            >
                                Cerrar cámara
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={mostrarModalPagos}
                    onOpenChange={setMostrarModalPagos}
                >
                    <DialogContent
                        aria-describedby={undefined}
                        className="w-[95vw] max-w-md overflow-hidden p-6 sm:max-w-lg md:max-w-2xl lg:max-w-3xl"
                    >
                        <DialogHeader className="pb-4">
                            <DialogTitle className="flex items-center gap-2 text-lg">
                                <Wallet className="h-5 w-5" />
                                Seleccionar Método de Pago
                            </DialogTitle>
                        </DialogHeader>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
                            <Button
                                className="h-20 flex-col gap-2 rounded-xl bg-purple-600 hover:bg-purple-700 sm:h-24"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('vale');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <Ticket className="h-6 w-6 sm:h-8 sm:w-8" />
                                <span className="text-xs leading-tight font-bold tracking-wider uppercase sm:text-sm">
                                    Vales
                                </span>
                            </Button>

                            <Button
                                className="h-20 flex-col gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 sm:h-24"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('transferencia');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <Building2 className="h-6 w-6 sm:h-8 sm:w-8" />
                                <span className="text-xs leading-tight font-bold tracking-wider uppercase sm:text-sm">
                                    Transferencia
                                </span>
                            </Button>

                            <Button
                                className="h-20 flex-col gap-2 rounded-xl bg-orange-600 hover:bg-orange-700 sm:h-24"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('visa_transbank');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <CreditCard className="h-6 w-6 sm:h-8 sm:w-8" />
                                <span className="text-xs leading-tight font-bold tracking-wider uppercase sm:text-sm">
                                    Visa Transbank
                                </span>
                            </Button>

                            <Button
                                className="h-20 flex-col gap-2 rounded-xl bg-yellow-500 hover:bg-yellow-600 sm:h-24"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('binance');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    className="h-6 w-6 fill-current sm:h-8 sm:w-8"
                                >
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 13.53l-1.41-1.41-3.64 3.64-1.41-1.41-1.41 1.41 2.82 2.82 4.24-4.24-1.41-1.41z" />
                                </svg>
                                <span className="text-xs leading-tight font-bold tracking-wider uppercase sm:text-sm">
                                    Binance
                                </span>
                            </Button>

                            <Button
                                className="col-span-1 h-16 flex-col gap-2 rounded-xl bg-slate-700 hover:bg-slate-800 sm:col-span-2 sm:h-20 lg:col-span-1 xl:col-span-1"
                                disabled={cart.length === 0 || procesando}
                                onClick={() => {
                                    procesarPago('contactar');
                                    setMostrarModalPagos(false);
                                }}
                            >
                                <MessageCircle className="h-5 w-5 sm:h-6 sm:w-6" />
                                <span className="text-xs leading-tight font-bold tracking-wider uppercase sm:text-sm">
                                    Contactar con Administración
                                </span>
                            </Button>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setMostrarModalPagos(false)}
                                className="w-full sm:w-auto"
                            >
                                Cancelar
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={!!productoSeleccionado}
                    onOpenChange={(open) =>
                        !open && setProductoSeleccionado(null)
                    }
                >
                    <DialogContent
                        aria-describedby={undefined}
                        className="w-[95vw] max-w-md sm:max-w-lg md:max-w-xl lg:max-w-2xl"
                    >
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
                                        {productoSeleccionado.skus?.map(
                                            (sku) => (
                                                <div
                                                    key={sku.id}
                                                    className={`cursor-pointer rounded-lg border p-3 transition-all ${
                                                        skuSeleccionado?.id ===
                                                        sku.id
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
                                                                    .join(
                                                                        ' | ',
                                                                    )}
                                                            </p>
                                                            <p
                                                                className={`text-sm ${sku.stock > 0 ? 'text-emerald-600' : 'text-destructive'}`}
                                                            >
                                                                Stock:{' '}
                                                                {sku.stock}
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
                                            ),
                                        )}
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
                    <Dialog
                        open={true}
                        onOpenChange={() => setUltimaVenta(null)}
                    >
                        <DialogContent
                            aria-describedby={undefined}
                            className="sm:max-w-lg"
                        >
                            <DialogHeader className="px-6 pt-6">
                                <DialogTitle className="flex items-center gap-2">
                                    <Receipt className="h-5 w-5" />
                                    Venta Completada
                                </DialogTitle>
                            </DialogHeader>
                            <div
                                className="space-y-4 overflow-y-auto px-6 pb-6"
                                style={{ maxHeight: 'calc(85vh - 8rem)' }}
                            >
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
                                                    calcularSubtotalLinea(
                                                        item,
                                                    ) +
                                                        (item.envase_retornable &&
                                                        item.envase_precio
                                                            ? Math.max(
                                                                  0,
                                                                  item.cantidad -
                                                                      (item.cantidad_retornada ||
                                                                          0),
                                                              ) *
                                                              item.envase_precio
                                                            : 0),
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
                                    {ultimaVenta.hasDelivery && (
                                        <div className="flex justify-between text-emerald-600">
                                            <span>Despacho</span>
                                            <span>
                                                {formatCurrency(
                                                    ultimaVenta.deliveryCost,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    {ultimaVenta.cupon && (
                                        <div className="flex justify-between text-orange-600">
                                            <span>
                                                Cupón:{' '}
                                                {ultimaVenta.cupon.codigo}
                                            </span>
                                            <span>
                                                -
                                                {formatCurrency(
                                                    ultimaVenta.cupon.descuento,
                                                )}
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
                                    <p>
                                        Método de pago: {ultimaVenta.metodoPago}
                                    </p>
                                    <p>
                                        Documento:{' '}
                                        {ultimaVenta.tipoDocumento
                                            .charAt(0)
                                            .toUpperCase() +
                                            ultimaVenta.tipoDocumento.slice(1)}
                                    </p>
                                </div>

                                {canEmitDte &&
                                    ultimaVenta.tipoDocumento !==
                                        'cotizacion' &&
                                    ultimaVenta.ventaId && (
                                        <Button
                                            variant="default"
                                            className="w-full gap-2 bg-emerald-600 font-bold hover:bg-emerald-700"
                                            onClick={() => {
                                                setDteResultado(null);
                                                setEmitirDteOpen(true);
                                            }}
                                        >
                                            <FileText className="h-4 w-4" />
                                            Emitir DTE (
                                            {ultimaVenta.tipoDocumento ===
                                            'factura'
                                                ? 'Factura Electrónica'
                                                : 'Boleta Electrónica'}
                                            )
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
                                        onClick={() => {
                                            if (ultimaVenta.ventaId) {
                                                window.open(
                                                    `/pos/${ultimaVenta.ventaId}/ticket`,
                                                    '_blank',
                                                    'noopener,noreferrer',
                                                );
                                                return;
                                            }

                                            window.print();
                                        }}
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
                    <DialogContent
                        aria-describedby={undefined}
                        className="w-[95vw] max-w-md overflow-y-auto p-6 sm:max-w-md"
                    >
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Emitir DTE
                            </DialogTitle>
                        </DialogHeader>

                        {dteResultado ? (
                            <div className="space-y-4">
                                <div
                                    className={`flex items-center gap-3 rounded-lg p-4 ${
                                        dteResultado.success
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
                                        {ultimaVenta?.tipoDocumento ===
                                        'factura'
                                            ? 'Factura Electrónica (Tipo 33)'
                                            : 'Boleta Electrónica (Tipo 39)'}
                                    </strong>{' '}
                                    para esta venta. Se asignará un folio SII y
                                    se generará el XML firmado.
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
                                        {ultimaVenta?.tipoDocumento ===
                                        'factura'
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
                                        className="flex-1 gap-2 bg-emerald-600 font-bold hover:bg-emerald-700"
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
                                                    toast.success(data.message);
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

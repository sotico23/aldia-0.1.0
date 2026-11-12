import { Head, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import {
    Check,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    Package,
    Warehouse,
    Tag,
    AlertTriangle,
    Eye,
    Image as ImageIcon,
    Video,
    ArrowDownZA,
    LayoutGrid,
    List,
    Layers,
    Ruler,
    Palette,
    Hash,
    XCircle,
    Calendar,
    TrendingUp,
    Wallet,
    Camera,
    Tags,
    Info,
    AlertCircle,
    Loader2,
} from 'lucide-react';
import { useState, useEffect, useMemo, useRef } from 'react';
import { toast } from 'sonner';
import lookupProductByBarcode from '@/actions/App/Http/Controllers/Backend/ProductoBarcodeController';
import { BulkActions } from '@/components/shared/BulkActions';
import { SectionPanel } from '@/components/shared/SectionPanel';
import { TruncatedText } from '@/components/shared/TruncatedText';
import { Badge } from '@/components/ui/badge';
import { BarcodeScannerDialog } from '@/components/ui/barcode-scanner-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ImageUploader } from '@/components/ui/image-uploader';
import type { ImageUploaderImage } from '@/components/ui/image-uploader';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/ui/Pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { TooltipProvider } from '@/components/ui/tooltip';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface Categoria {
    id: number;
    nombre: string;
}
interface Inventario {
    id: number;
    cantidad: number;
    cantidad_minima: number;
    almacen?: {
        id: number;
        nombre: string;
    };
}
interface VarianteValor {
    id: number;
    valor: string;
    codigo: string | null;
}
interface VarianteTipo {
    id: number;
    nombre: string;
    tipo: string;
    activo: boolean;
    valores: VarianteValor[];
}
interface SkuRow {
    id?: number;
    sku: string;
    imagen: File | null;
    imagen_preview: string | null;
    precio_venta: number;
    precio_compra: number;
    stock: number;
    stock_minimo: number;
    almacen_id: string;
    variante_valores: (number | string)[];
    labels: string[];
}
interface Producto {
    id: number;
    codigo: string | null;
    nombre: string;
    descripcion: string | null;
    categoria_id: number | null;
    precio_compra: number;
    precio_venta: number;
    comision_cajero_por_unidad: number;
    stock_minimo: number;
    fecha_vencimiento: string | null;
    unidad_medida: 'unidad' | 'kg' | 'lt';
    activo: boolean;
    imagen: string | null;
    imagen2: string | null;
    imagen3: string | null;
    imagen4: string | null;
    imagen5: string | null;
    video: string | null;
    envase_retornable?: boolean;
    envase_producto_id?: number | null;
    envase_producto?: Producto;
    medida_pesable?: boolean;
    categoria?: Categoria;
    inventario?: Inventario;
    inventarios?: Inventario[];
    tiene_variantes?: boolean;
    talla?: string;
    variantes?: Producto[];
    skus?: {
        id: number;
        sku: string;
        imagen: string | null;
        precio_venta: number;
        precio_compra: number;
        stock: number;
        stock_minimo: number;
        valores: {
            id: number;
            variante_valor_id: number;
            varianteValor: {
                id: number;
                valor: string;
                variante_id: number;
            };
        }[];
    }[];
}

interface BarcodeProductData {
    nombre: string | null;
    marca: string | null;
    categoria: string | null;
    imagen: string | null;
    formato: string | null;
}

interface BarcodeLookupResponse {
    encontrado: boolean;
    origen: 'base_datos' | 'api_externa';
    datos: BarcodeProductData | null;
}

const PRODUCT_IMAGE_KEYS = [
    'imagen',
    'imagen2',
    'imagen3',
    'imagen4',
    'imagen5',
] as const;

function getProductImageUrl(path: string): string {
    return /^https?:\/\//i.test(path)
        ? path
        : `/storage/${path.replace(/^\/+/, '')}`;
}

function buildProductImageItems(
    producto: Producto | null,
): ImageUploaderImage[] {
    if (!producto) {
        return [];
    }

    const seenPaths = new Set<string>();

    return PRODUCT_IMAGE_KEYS.flatMap((key) => {
        const path = producto[key];

        if (!path || seenPaths.has(path)) {
            return [];
        }

        seenPaths.add(path);

        return [
            {
                id: `existing-${producto.id}-${key}`,
                preview: getProductImageUrl(path),
                existingPath: path,
                existingKey: key,
            },
        ];
    });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Productos', href: '/productos' },
];

function buildSkuRows(
    selectedVariants: {
        nombre: string;
        valores: { id: number | string; valor: string }[];
    }[],
    baseSku: string,
    precioVenta: number,
    precioCompra: number,
    stockMinimo: number,
    almacenId: string | undefined,
): SkuRow[] {
    if (selectedVariants.length === 0) {
        return [];
    }

    const valueSets = selectedVariants.map((v) =>
        v.valores.map((vl) => ({
            id: vl.id,
            valor: vl.valor,
            varianteNombre: v.nombre,
        })),
    );

    const cartesianProduct = <T,>(arrays: T[][]): T[][] => {
        return arrays.reduce<T[][]>(
            (acc, curr) => acc.flatMap((a) => curr.map((b) => [...a, b])),
            [[]],
        );
    };

    const combinations = cartesianProduct(valueSets);
    const skuBase = (baseSku || 'SKU').trim().replace(/\s+/g, '-') || 'SKU';

    return combinations.map((combo, i) => {
        const labels = combo.map((c) => c.varianteNombre + ': ' + c.valor);
        const suffix = combo.map((c) => c.valor).join('-');

        return {
            sku: i === 0 ? skuBase : `${skuBase}-${suffix}`,
            imagen: null,
            imagen_preview: null,
            precio_venta: precioVenta,
            precio_compra: precioCompra,
            stock: 0,
            stock_minimo: stockMinimo,
            almacen_id: almacenId || '',
            variante_valores: combo.map((c) => c.id),
            labels,
        };
    });
}

export default function Index({
    productos,
    categorias,
    almacenes = [],
    productosEnvase = [],
    variantesDisponibles = [],
    filters,
}: {
    productos: {
        data: Producto[];
        links: any[];
        from?: number;
        to?: number;
        total?: number;
        meta?: any;
    };
    categorias: Categoria[];
    almacenes: { id: number; nombre: string }[];
    productosEnvase: { id: number; nombre: string; codigo: string }[];
    variantesDisponibles: VarianteTipo[];
    filters: {
        search?: string;
        categoria_id?: string;
        stock_bajo?: string;
        almacen_id?: string;
    };
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('comercial.productos.create');
    const canEdit = hasPermission('comercial.productos.edit');
    const canDelete = hasPermission('comercial.productos.delete');
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Producto | null>(null);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [viendo, setViendo] = useState<Producto | null>(null);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [isSelfSelected, setIsSelfSelected] = useState(false);

    const [productImages, setProductImages] = useState<ImageUploaderImage[]>(
        [],
    );
    const [imageError, setImageError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const [categoriaFilter, setCategoriaFilter] = useState(
        filters.categoria_id || 'all',
    );
    const [stockBajoFilter, setStockBajoFilter] = useState(
        filters.stock_bajo === '1',
    );
    const [almacenFilter, setAlmacenFilter] = useState(
        filters.almacen_id || 'all',
    );

    // Variant UI state
    const [selectedVariantIds, setSelectedVariantIds] = useState<number[]>([]);
    const [newVariantName, setNewVariantName] = useState('');
    const [newVariantType, setNewVariantType] = useState<
        'texto' | 'color' | 'numero'
    >('texto');
    const [newVariantValues, setNewVariantValues] = useState<string[]>([]);
    const [newVariantValueInput, setNewVariantValueInput] = useState('');
    const [skuRows, setSkuRows] = useState<SkuRow[]>([]);
    const [skuSignature, setSkuSignature] = useState<string | null>(null);
    const [scannerOpen, setScannerOpen] = useState(false);
    const [scannerTarget, setScannerTarget] = useState<
        number | 'codigo' | null
    >(null);
    const [barcodeLookupLoading, setBarcodeLookupLoading] = useState(false);
    const [barcodeProduct, setBarcodeProduct] =
        useState<BarcodeProductData | null>(null);
    const barcodeLookupRequest = useRef(0);
    const skuLookupRequest = useRef(0);
    const [skuHydrationLoading, setSkuHydrationLoading] = useState(false);
    const [skuHydrationNotice, setSkuHydrationNotice] = useState<{
        type: 'info' | 'success';
        message: string;
    } | null>(null);
    const [pendingSkuData, setPendingSkuData] = useState<any>(null);
    const [showSkuConfirm, setShowSkuConfirm] = useState(false);

    const {
        data,
        setData,
        delete: destroy,
        reset,
        processing,
        errors,
        transform,
    } = useForm({
        codigo: '',
        nombre: '',
        descripcion: '',
        categoria_id: '' as string,
        precio_compra: 0,
        precio_venta: 0,
        comision_cajero_por_unidad: 0,
        stock_minimo: 0,
        fecha_vencimiento: '',
        stock: 0,
        warehouse_ids: [] as number[],
        unidad_medida: 'unidad' as 'unidad' | 'kg' | 'lt',
        activo: true,
        envase_retornable: false,
        envase_producto_id: null as number | null,
        medida_pesable: false,
        tipo_medida: 'unidad' as 'unidad' | 'kilo' | 'litro',
        cantidad_medida: 0,
        tipo_envase: '',
        video: null as File | null,
        mostrar_en_perfil: true,
        contenido_por_unidad: 1,
        peso_base: 0,
        tiene_variantes: false,
        variantes: [] as any[],
    });

    const lookupBarcode = async (rawCode: string) => {
        const code = rawCode.trim();
        const requestId = ++barcodeLookupRequest.current;

        if (!/^(?:\d{8}|\d{12,14})$/.test(code) || editando) {
            setBarcodeLookupLoading(false);
            return;
        }

        setBarcodeLookupLoading(true);
        setBarcodeProduct(null);

        try {
            const response = await axios.get<BarcodeLookupResponse>(
                lookupProductByBarcode.url({ ean: code }),
            );
            const result = response.data;

            if (requestId !== barcodeLookupRequest.current) {
                return;
            }

            if (!result.encontrado || !result.datos) {
                toast.info('No se encontró información para este código.');
                return;
            }

            const product = result.datos;
            setBarcodeProduct(product);

            if (product.nombre) {
                setData('nombre', product.nombre);
            }

            if (!data.descripcion.trim()) {
                const description = [
                    product.marca ? `Marca: ${product.marca}` : null,
                    product.categoria
                        ? `Categoría: ${product.categoria}`
                        : null,
                    product.formato ? `Formato: ${product.formato}` : null,
                ]
                    .filter(Boolean)
                    .join(' | ');

                if (description) {
                    setData('descripcion', description);
                }
            }

            if (product.categoria) {
                const matchingCategory = categorias.find(
                    (category) =>
                        category.nombre.trim().toLocaleLowerCase() ===
                        product.categoria?.trim().toLocaleLowerCase(),
                );

                if (matchingCategory) {
                    setData('categoria_id', matchingCategory.id.toString());
                }
            }

            toast.success(
                result.origen === 'base_datos'
                    ? 'Datos cargados desde tus productos.'
                    : 'Datos encontrados en Open Food Facts. Revísalos antes de guardar.',
            );
        } catch (error) {
            if (requestId !== barcodeLookupRequest.current) {
                return;
            }

            if (axios.isAxiosError(error) && error.response?.status === 404) {
                toast.info('No se encontró información para este código.');
            } else {
                toast.error(
                    'No se pudo consultar Open Food Facts. Puedes completar el producto manualmente.',
                );
            }
        } finally {
            if (requestId === barcodeLookupRequest.current) {
                setBarcodeLookupLoading(false);
            }
        }
    };

    /**
     * Consulta el SKU / código de barras en la base de datos del tenant y,
     * si existe, hidrata (autorrellena) el formulario con los datos del producto.
     * Si no existe, muestra un aviso sutil y deja el formulario listo para llenar manualmente.
     * Cuando el código es un EAN numérico y no está en el catálogo local, intenta
     * Open Food Facts como catálogo general de referencia.
     */
    const hydrateFromSku = async (rawCode: string) => {
        const code = rawCode.trim();
        const requestId = ++skuLookupRequest.current;

        if (!code) {
            setSkuHydrationLoading(false);
            return;
        }

        setSkuHydrationLoading(true);
        setSkuHydrationNotice(null);
        setBarcodeProduct(null);

        try {
            const response = await axios.get<{
                encontrado: boolean;
                origen: string;
                datos: any;
                mensaje: string;
            }>(`/api/v1/productos/lookup-sku/${encodeURIComponent(code)}`);

            const result = response.data;

            if (requestId !== skuLookupRequest.current) {
                return;
            }

            if (!result.encontrado || !result.datos) {
                setSkuHydrationNotice({
                    type: 'info',
                    message:
                        result.mensaje ||
                        'SKU no registrado en tu catálogo. Puedes completar el producto manualmente.',
                });

                // Fallback a catálogo general (Open Food Facts) solo para EAN numérico
                if (/^(?:\d{8}|\d{12,14})$/.test(code) && !editando) {
                    void lookupBarcode(code);
                }

                return;
            }

            const d = result.datos;

            const hasExistingData = formHasData;

            if (hasExistingData) {
                // No sobrescribir datos que el usuario ya digitó sin confirmación.
                setPendingSkuData(d);
                setShowSkuConfirm(true);
                return;
            }

            applySkuHydration(d);

            setSkuHydrationNotice({
                type: 'success',
                message:
                    result.mensaje ||
                    'Producto encontrado y datos precargados. Revisa y ajusta antes de guardar.',
            });
            toast.success(result.mensaje || 'Producto encontrado y datos precargados.');
        } catch (error) {
            if (requestId !== skuLookupRequest.current) {
                return;
            }

            setSkuHydrationNotice({
                type: 'info',
                message:
                    'No se pudo consultar el SKU. Puedes completar el producto manualmente.',
            });
            toast.error('No se pudo consultar el SKU.');
        } finally {
            if (requestId === skuLookupRequest.current) {
                setSkuHydrationLoading(false);
            }
        }
    };

    const handleCodigoLookup = (code: string) => {
        void hydrateFromSku(code);
    };

    /**
     * Detecta si el formulario ya tiene datos digitados por el usuario para
     * evitar sobrescribirlos sin confirmación al hacer autocompletado por SKU.
     */
    const formHasData = useMemo(() => {
        return Boolean(
            data.nombre.trim() ||
            data.descripcion.trim() ||
            data.categoria_id ||
            Number(data.precio_compra) > 0 ||
            Number(data.precio_venta) > 0 ||
            data.stock_minimo > 0 ||
            data.warehouse_ids.length > 0
        );
    }, [data]);

    /**
     * Aplica los datos de hidratación de un SKU encontrado al formulario,
     * sin tocar el campo código (ya está seteado).
     */
    const applySkuHydration = (d: any): void => {
        setData('nombre', d.nombre || data.nombre);
        if (!data.descripcion.trim()) {
            setData('descripcion', d.descripcion || '');
        }
        setData('categoria_id', d.categoria_id?.toString() ?? '');
        setData('precio_compra', d.precio_compra ?? 0);
        setData('precio_venta', d.precio_venta ?? 0);
        setData('stock_minimo', d.stock_minimo ?? 0);
        setData('unidad_medida', d.unidad_medida ?? 'unidad');
        setData('medida_pesable', d.medida_pesable ?? false);
        setData('tiene_variantes', d.tiene_variantes ?? false);

        // Almacenes con stock
        if (d.inventarios?.length) {
            setData(
                'warehouse_ids',
                d.inventarios
                    .map((inv: any) => inv.almacen_id)
                    .filter(Boolean),
            );
        }

        // Imágenes existentes del producto
        const images: ImageUploaderImage[] = [];
        ['imagen', 'imagen2', 'imagen3', 'imagen4', 'imagen5'].forEach(
            (key, idx) => {
                if (d[key]) {
                    images.push({
                        id: `existing-sku-${idx}`,
                        preview: d[key],
                        existingPath: d[key],
                        existingKey: key,
                    });
                }
            },
        );
        setProductImages(images);

        // Variantes / SKUs (System B)
        if (d.tiene_variantes && d.skus?.length) {
            const rows: SkuRow[] = d.skus.map((sku: any, i: number) => ({
                id: sku.id,
                sku: sku.sku,
                imagen: null,
                imagen_preview: sku.imagen,
                precio_venta: sku.precio_venta ?? d.precio_venta,
                precio_compra: sku.precio_compra ?? d.precio_compra,
                stock: sku.stock ?? 0,
                stock_minimo: sku.stock_minimo ?? d.stock_minimo,
                almacen_id: '',
                variante_valores: sku.variante_valores ?? [],
                labels: sku.labels ?? [],
            }));
            setSkuRows(rows);
        }
    };

    const getSelectedVariants = useMemo(() => {
        return variantesDisponibles.filter((v) =>
            selectedVariantIds.includes(v.id),
        );
    }, [selectedVariantIds, variantesDisponibles]);
    const skuVariantDefinitions = useMemo(
        () => [
            ...getSelectedVariants,
            ...(newVariantName.trim() && newVariantValues.length > 0
                ? [
                      {
                          nombre: newVariantName.trim(),
                          valores: newVariantValues.map((valor, index) => ({
                              id: `inline:0:${index}`,
                              valor,
                          })),
                      },
                  ]
                : []),
        ],
        [getSelectedVariants, newVariantName, newVariantValues],
    );

    const margenAbsoluto =
        Number(data.precio_venta) - Number(data.precio_compra);
    const margenPorcentual =
        Number(data.precio_venta) > 0
            ? (margenAbsoluto / Number(data.precio_venta)) * 100
            : 0;
    const productoSeVendePorMedida =
        data.medida_pesable || data.unidad_medida !== 'unidad';

    const inlineVariantSignature =
        newVariantName.trim() && newVariantValues.length > 0
            ? `|${newVariantName.trim()}:${newVariantType}:${newVariantValues.join('|')}`
            : '';
    const currentSkuSignature =
        data.tiene_variantes && skuVariantDefinitions.length > 0
            ? `${selectedVariantIds.join(',')}|${data.codigo || data.nombre}${inlineVariantSignature}`
            : null;

    if (currentSkuSignature !== skuSignature) {
        setSkuSignature(currentSkuSignature);
        setSkuRows(
            currentSkuSignature
                ? buildSkuRows(
                      skuVariantDefinitions,
                      data.codigo || data.nombre,
                      data.precio_venta,
                      data.precio_compra,
                      data.stock_minimo,
                      data.warehouse_ids[0]?.toString(),
                  )
                : [],
        );
    }

    const generateSkuCombinations = () => {
        setSkuSignature(currentSkuSignature);
        setSkuRows(
            currentSkuSignature
                ? buildSkuRows(
                      skuVariantDefinitions,
                      data.codigo || data.nombre,
                      data.precio_venta,
                      data.precio_compra,
                      data.stock_minimo,
                      data.warehouse_ids[0]?.toString(),
                  )
                : [],
        );
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            const query: any = {};
            if (searchTerm) query.search = searchTerm;
            if (categoriaFilter !== 'all') query.categoria_id = categoriaFilter;
            if (stockBajoFilter) query.stock_bajo = '1';
            if (almacenFilter !== 'all') query.almacen_id = almacenFilter;

            router.get('/productos', query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 500);

        return () => clearTimeout(timer);
    }, [searchTerm, categoriaFilter, stockBajoFilter, almacenFilter]);

    const limpiarFiltros = () => {
        setSearchTerm('');
        setCategoriaFilter('all');
        setStockBajoFilter(false);
        setAlmacenFilter('all');
    };

    const gruposPorCategoria = useMemo(() => {
        const agrupados = productos.data.reduce<Record<string, Producto[]>>(
            (acc, producto) => {
                const categoria = producto.categoria?.nombre || 'General';
                (acc[categoria] ??= []).push(producto);

                return acc;
            },
            {},
        );

        return Object.entries(agrupados)
            .map(([nombre, data]) => ({
                nombre,
                data,
                stockBajo: data.filter((producto) => {
                    const actual =
                        producto.inventarios?.reduce(
                            (sum, inv) => sum + inv.cantidad,
                            0,
                        ) || 0;
                    const minimo =
                        producto.inventarios?.reduce(
                            (sum, inv) => sum + inv.cantidad_minima,
                            0,
                        ) || 0;

                    return actual <= minimo;
                }).length,
            }))
            .sort((a, b) => a.nombre.localeCompare(b.nombre));
    }, [productos.data]);

    const appendProductImagesToFormData = (formData: FormData) => {
        formData.append('manage_images', '1');

        const usedKeys = new Set<string>();

        productImages.forEach((image) => {
            if (image.existingPath) {
                const key = image.existingKey ?? 'imagen';
                usedKeys.add(key);
                formData.append(
                    `imagenes_existentes[${key}]`,
                    image.existingPath,
                );
                return;
            }

            if (!image.file) {
                return;
            }

            let key = '';
            for (const candidate of PRODUCT_IMAGE_KEYS) {
                if (!usedKeys.has(candidate)) {
                    key = candidate;
                    break;
                }
            }

            if (!key) {
                return;
            }

            usedKeys.add(key);
            formData.append(key, image.file);
        });
    };

    const handleImageSubmitError = (submitErrors: Record<string, string>) => {
        const imageErrorKey = Object.keys(submitErrors).find(
            (key) =>
                key.startsWith('imagen') ||
                (key.startsWith('skus.') && key.endsWith('.imagen')),
        );

        if (imageErrorKey) {
            setImageError(submitErrors[imageErrorKey]);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isSubmitting) {
            return;
        }

        if (
            data.tiene_variantes &&
            (selectedVariantIds.length > 0 || newVariantName.trim()) &&
            skuRows.length === 0
        ) {
            toast.error('Agrega valores a las variantes antes de guardar.');
            return;
        }

        setImageError(null);

        transform((currentData) => ({
            ...currentData,
            envase_producto_id:
                currentData.envase_producto_id === 0
                    ? editando
                        ? editando.id
                        : null
                    : currentData.envase_producto_id,
        }));

        if (editando) {
            const formData = new FormData();
            Object.entries(data).forEach(([key, value]) => {
                if (key === 'envase_producto_id') {
                    if (value === null || value === 'none') {
                        formData.append(key, '');
                        return;
                    }
                    if (value === 'self') {
                        formData.append(
                            key,
                            editando ? String(editando.id) : '',
                        );
                        return;
                    }
                    if (typeof value === 'number') {
                        formData.append(key, String(value));
                        return;
                    }
                    if (typeof value === 'string' && /^\d+$/.test(value)) {
                        formData.append(key, String(parseInt(value, 10)));
                        return;
                    }
                    formData.append(key, '');
                    return;
                }
                if (key === 'variantes' && Array.isArray(value)) {
                    if (value.length > 0 && 'talla' in value[0]) {
                        value.forEach((v, index) => {
                            formData.append(
                                `variantes_legacy[${index}][talla]`,
                                String(v.talla || ''),
                            );
                            formData.append(
                                `variantes_legacy[${index}][codigo]`,
                                String(v.codigo || ''),
                            );
                            formData.append(
                                `variantes_legacy[${index}][stock]`,
                                String(v.stock || 0),
                            );
                            formData.append(
                                `variantes_legacy[${index}][almacen_id]`,
                                String(v.almacen_id || ''),
                            );
                        });
                    }

                    return;
                }
                if (key === 'warehouse_ids' && Array.isArray(value)) {
                    if (value.length > 0) {
                        value.forEach((id, i) => {
                            formData.append(`warehouse_ids[${i}]`, String(id));
                        });
                    }
                    return;
                }

                if (value === null) return;
                if (value === undefined) return;
                if (
                    typeof value === 'string' &&
                    value === '' &&
                    key !== 'descripcion' &&
                    key !== 'envase_producto_id' &&
                    key !== 'fecha_vencimiento'
                )
                    return;

                if (typeof value === 'boolean') {
                    formData.append(key, value ? '1' : '0');
                } else if (value instanceof File) {
                    formData.append(key, value);
                } else {
                    formData.append(key, String(value));
                }
            });

            // Append variant data
            if (data.tiene_variantes) {
                if (newVariantName.trim() && newVariantValues.length > 0) {
                    formData.append(
                        `variantes[0][nombre]`,
                        newVariantName.trim(),
                    );
                    formData.append(`variantes[0][tipo]`, newVariantType);
                    newVariantValues.forEach((vv, vi) => {
                        formData.append(`variantes[0][valores][${vi}]`, vv);
                    });
                }
                selectedVariantIds.forEach((vid, vi) => {
                    formData.append(`variante_ids[${vi}]`, String(vid));
                });
                skuRows.forEach((sku, si) => {
                    if (editando && sku.id !== undefined) {
                        formData.append(`skus[${si}][id]`, String(sku.id));
                    }
                    formData.append(`skus[${si}][sku]`, sku.sku);
                    if (sku.imagen) {
                        formData.append(`skus[${si}][imagen]`, sku.imagen);
                    }
                    formData.append(
                        `skus[${si}][precio_venta]`,
                        String(sku.precio_venta),
                    );
                    formData.append(
                        `skus[${si}][precio_compra]`,
                        String(sku.precio_compra),
                    );
                    formData.append(`skus[${si}][stock]`, String(sku.stock));
                    formData.append(
                        `skus[${si}][stock_minimo]`,
                        String(sku.stock_minimo),
                    );
                    if (sku.almacen_id) {
                        formData.append(
                            `skus[${si}][almacen_id]`,
                            sku.almacen_id,
                        );
                    }
                    sku.variante_valores.forEach((vvId, vi) => {
                        formData.append(
                            `skus[${si}][variante_valores][${vi}]`,
                            String(vvId),
                        );
                    });
                });
            }

            appendProductImagesToFormData(formData);
            formData.append('_method', 'PUT');

            router.post(`/productos/${editando.id}`, formData, {
                onStart: () => setIsSubmitting(true),
                onFinish: () => setIsSubmitting(false),
                onSuccess: () => {
                    setIsOpen(false);
                    setEditando(null);
                    reset();
                    setProductImages([]);
                    setImageError(null);
                    setSelectedVariantIds([]);
                    setSkuRows([]);
                    setNewVariantName('');
                    setNewVariantValues([]);
                },
                onError: (submitErrors: Record<string, string>) => {
                    handleImageSubmitError(submitErrors);
                    console.error('Error:', submitErrors);
                },
            });
        } else {
            const formData = new FormData();
            Object.entries(data).forEach(([key, value]) => {
                if (key === 'envase_producto_id') {
                    if (value === null || value === 'none') {
                        formData.append(key, '');
                        return;
                    }
                    if (value === 'self') {
                        return;
                    }
                    if (typeof value === 'number') {
                        formData.append(key, String(value));
                        return;
                    }
                    formData.append(key, '');
                    return;
                }
                if (key === 'variantes' && Array.isArray(value)) {
                    return;
                }
                if (key === 'warehouse_ids' && Array.isArray(value)) {
                    if (value.length > 0) {
                        value.forEach((id, i) => {
                            formData.append(`warehouse_ids[${i}]`, String(id));
                        });
                    }
                    return;
                }
                if (value === null) return;
                if (value === undefined) return;
                if (
                    typeof value === 'string' &&
                    value === '' &&
                    key !== 'descripcion' &&
                    key !== 'envase_producto_id' &&
                    key !== 'fecha_vencimiento'
                )
                    return;

                if (typeof value === 'boolean') {
                    formData.append(key, value ? '1' : '0');
                } else if (value instanceof File) {
                    formData.append(key, value);
                } else {
                    formData.append(key, String(value));
                }
            });

            if (data.tiene_variantes) {
                if (newVariantName.trim() && newVariantValues.length > 0) {
                    formData.append(
                        `variantes[0][nombre]`,
                        newVariantName.trim(),
                    );
                    formData.append(`variantes[0][tipo]`, newVariantType);
                    newVariantValues.forEach((vv, vi) => {
                        formData.append(`variantes[0][valores][${vi}]`, vv);
                    });
                }
                selectedVariantIds.forEach((vid, vi) => {
                    formData.append(`variante_ids[${vi}]`, String(vid));
                });
                skuRows.forEach((sku, si) => {
                    formData.append(`skus[${si}][sku]`, sku.sku);
                    if (sku.imagen) {
                        formData.append(`skus[${si}][imagen]`, sku.imagen);
                    }
                    formData.append(
                        `skus[${si}][precio_venta]`,
                        String(sku.precio_venta),
                    );
                    formData.append(
                        `skus[${si}][precio_compra]`,
                        String(sku.precio_compra),
                    );
                    formData.append(`skus[${si}][stock]`, String(sku.stock));
                    formData.append(
                        `skus[${si}][stock_minimo]`,
                        String(sku.stock_minimo),
                    );
                    if (sku.almacen_id) {
                        formData.append(
                            `skus[${si}][almacen_id]`,
                            sku.almacen_id,
                        );
                    }
                    sku.variante_valores.forEach((vvId, vi) => {
                        formData.append(
                            `skus[${si}][variante_valores][${vi}]`,
                            String(vvId),
                        );
                    });
                });
            }

            appendProductImagesToFormData(formData);

            router.post('/productos', formData, {
                onStart: () => setIsSubmitting(true),
                onFinish: () => setIsSubmitting(false),
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                    setProductImages([]);
                    setImageError(null);
                    setSelectedVariantIds([]);
                    setSkuRows([]);
                    setNewVariantName('');
                    setNewVariantValues([]);
                },
                onError: (submitErrors: Record<string, string>) => {
                    handleImageSubmitError(submitErrors);
                    console.error('Error:', submitErrors);
                },
            });
        }
    };

    const handleEdit = (producto: Producto) => {
        const envaseProductoId = (producto as any).envase_producto_id;
        let envaseProductoValue = 'none';
        let isSelf = false;

        if (envaseProductoId !== null) {
            if (Number(envaseProductoId) === producto.id) {
                envaseProductoValue = 'self';
                isSelf = true;
            } else {
                envaseProductoValue = String(envaseProductoId);
            }
        }

        setEditando(producto);
        (setData as any)({
            codigo: producto.codigo || '',
            nombre: producto.nombre,
            descripcion: producto.descripcion || '',
            categoria_id: producto.categoria_id?.toString() || '',
            precio_compra: producto.precio_compra,
            precio_venta: producto.precio_venta,
            comision_cajero_por_unidad:
                Number(producto.comision_cajero_por_unidad) || 0,
            stock_minimo: producto.stock_minimo,
            fecha_vencimiento: (producto as any).fecha_vencimiento || '',
            stock: producto.inventario?.cantidad || 0,
            warehouse_ids: producto.inventarios
                ? (producto.inventarios
                      .map((inv) => inv.almacen?.id)
                      .filter(Boolean) as number[])
                : [],
            unidad_medida: producto.unidad_medida || 'unidad',
            activo: producto.activo,
            envase_retornable: Boolean((producto as any).envase_retornable),
            envase_producto_id: envaseProductoValue as any,
            medida_pesable: Boolean((producto as any).medida_pesable),
            tipo_medida:
                ((producto as any).tipo_medida as
                    'unidad' | 'kilo' | 'litro') || 'unidad',
            cantidad_medida: Number((producto as any).cantidad_medida) || 0,
            tipo_envase: (producto as any).tipo_envase || '',
            video: null,
            mostrar_en_perfil: (producto as any).mostrar_en_perfil ?? true,
            contenido_por_unidad:
                Number((producto as any).contenido_por_unidad) || 1,
            peso_base: Number((producto as any).peso_base) || 0,
            tiene_variantes: Boolean((producto as any).tiene_variantes),
            variantes:
                producto.variantes?.map((v: any) => ({
                    talla: v.talla,
                    color: v.color || '',
                    codigo: v.codigo,
                    stock: v.inventarios?.[0]?.cantidad || 0,
                    almacen_id: v.inventarios?.[0]?.almacen_id || '',
                })) || [],
        });

        // Load SKU data if exists (System B)
        if (producto.skus && producto.skus.length > 0) {
            const rows: SkuRow[] = producto.skus.map((sku) => {
                const vvIds = sku.valores.map((v) => v.variante_valor_id);
                return {
                    id: sku.id,
                    sku: sku.sku,
                    imagen: null,
                    imagen_preview: sku.imagen
                        ? getProductImageUrl(sku.imagen)
                        : null,
                    precio_venta: sku.precio_venta || producto.precio_venta,
                    precio_compra: sku.precio_compra || producto.precio_compra,
                    stock: sku.stock,
                    stock_minimo: sku.stock_minimo || producto.stock_minimo,
                    almacen_id: '',
                    variante_valores: vvIds,
                    labels: sku.valores.map(
                        (v) => v.varianteValor?.valor || '',
                    ),
                };
            });
            setSkuRows(rows);

            // Find which variant types are used by these SKU values
            const usedVariantTypeIds = new Set<number>();
            producto.skus.forEach((sku) => {
                sku.valores.forEach((v) => {
                    const vl = variantesDisponibles
                        .flatMap((vt) => vt.valores)
                        .find((vl2) => vl2.id === v.variante_valor_id);
                    if (vl) {
                        const parent = variantesDisponibles.find((vt) =>
                            vt.valores.some((v2) => v2.id === vl.id),
                        );
                        if (parent) usedVariantTypeIds.add(parent.id);
                    }
                });
            });
            const usedVariantIds = Array.from(usedVariantTypeIds);
            setSelectedVariantIds(usedVariantIds);
            setSkuSignature(
                Boolean((producto as any).tiene_variantes) &&
                    usedVariantIds.length > 0
                    ? `${usedVariantIds.join(',')}|${producto.codigo}`
                    : null,
            );
        }

        setProductImages(buildProductImageItems(producto));
        setImageError(null);
        setIsSelfSelected(isSelf);
        setIsOpen(true);
    };

    const handleNew = () => {
        setEditando(null);
        reset();
        setProductImages([]);
        setImageError(null);
        setSelectedVariantIds([]);
        setSkuRows([]);
        setNewVariantName('');
        setNewVariantValues([]);
        setIsSelfSelected(false);
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Está seguro de eliminar este producto?')) {
            destroy(`/productos/${id}`);
        }
    };

    const toggleVariantType = (id: number) => {
        setSelectedVariantIds((prev) =>
            prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id],
        );
    };

    const addNewVariantValue = () => {
        const val = newVariantValueInput.trim();
        if (!val) {
            return;
        }

        setNewVariantValues((values) =>
            values.some(
                (existingValue) =>
                    existingValue.toLocaleLowerCase() ===
                    val.toLocaleLowerCase(),
            )
                ? values
                : [...values, val],
        );
        setNewVariantValueInput('');
    };

    const removeNewVariantValue = (val: string) => {
        setNewVariantValues((values) =>
            values.filter((value) => value !== val),
        );
    };

    const updateSkuRow = (index: number, field: keyof SkuRow, value: any) => {
        setSkuRows((prev) =>
            prev.map((row, i) =>
                i === index ? { ...row, [field]: value } : row,
            ),
        );
    };

    const updateSkuImage = (index: number, image: File | null) => {
        if (!image) {
            return;
        }

        const previousPreview = skuRows[index]?.imagen_preview;
        if (previousPreview?.startsWith('blob:')) {
            URL.revokeObjectURL(previousPreview);
        }

        setSkuRows((rows) =>
            rows.map((row, rowIndex) =>
                rowIndex === index
                    ? {
                          ...row,
                          imagen: image,
                          imagen_preview: URL.createObjectURL(image),
                      }
                    : row,
            ),
        );
    };

    const openSkuScanner = (target: number | 'codigo') => {
        setScannerTarget(target);
        setScannerOpen(true);
    };

    const handleBarcodeDetected = (code: string) => {
        const scannedCode = code.trim();

        if (scannedCode === '') {
            return;
        }

        if (scannerTarget === 'codigo') {
            setData('codigo', scannedCode);
            setBarcodeProduct(null);
            setSkuHydrationNotice(null);
            void hydrateFromSku(scannedCode);

            return;
        }

        if (typeof scannerTarget === 'number') {
            updateSkuRow(scannerTarget, 'sku', scannedCode);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestión de Productos e Inventario" />

            <TooltipProvider delayDuration={200}>
                <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                    {/* Header Section */}
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="text-3xl font-black tracking-tight text-foreground">
                                Catálogo
                            </h1>
                            <p className="text-sm font-medium text-muted-foreground">
                                Administración de productos, precios y niveles
                                de inventario maestro
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            <BulkActions
                                baseUrl="/productos"
                                filters={{
                                    search: searchTerm,
                                    categoria_id: categoriaFilter,
                                    stock_bajo: stockBajoFilter ? '1' : '',
                                    almacen_id:
                                        almacenFilter !== 'all'
                                            ? almacenFilter
                                            : '',
                                }}
                                modelName="Productos"
                            />

                            {canCreate && (
                                <Button
                                    onClick={handleNew}
                                    className="h-9 rounded-full bg-primary px-5 font-bold shadow-lg shadow-primary/20 transition-all hover:bg-primary/90"
                                >
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo
                                    Producto
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-6">
                        <Card className="overflow-hidden border-none shadow-xl shadow-foreground/5">
                            <CardHeader className="bg-gradient-to-r from-muted/50 to-transparent pb-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Package className="h-5 w-5 text-primary" />
                                        <CardTitle>
                                            Productos Registrados
                                        </CardTitle>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="text-xs font-bold tracking-widest text-muted-foreground uppercase">
                                            {productos.total} SKUs
                                        </div>
                                        <div className="flex items-center gap-1 rounded-lg border bg-muted/30 p-0.5">
                                            <button
                                                onClick={() =>
                                                    setViewMode('table')
                                                }
                                                className={`rounded-md p-1.5 transition-colors ${
                                                    viewMode === 'table'
                                                        ? 'bg-white text-primary shadow-sm'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                }`}
                                                title="Vista tabla"
                                            >
                                                <List className="h-4 w-4" />
                                            </button>
                                            <button
                                                onClick={() =>
                                                    setViewMode('cards')
                                                }
                                                className={`rounded-md p-1.5 transition-colors ${
                                                    viewMode === 'cards'
                                                        ? 'bg-white text-primary shadow-sm'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                }`}
                                                title="Vista tarjetas"
                                            >
                                                <LayoutGrid className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                {/* Filters Bar */}
                                <div className="flex flex-col gap-4 border-b border-muted/30 bg-muted/20 p-4 md:flex-row md:items-center">
                                    <div className="relative flex-1">
                                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar por nombre, SKU o descripción..."
                                            value={searchTerm}
                                            onChange={(e) =>
                                                setSearchTerm(e.target.value)
                                            }
                                            className="h-10 border-none bg-background/50 pr-10 pl-10 focus-visible:ring-primary/20"
                                        />
                                    </div>
                                    <div className="flex gap-2">
                                        <Select
                                            value={categoriaFilter}
                                            onValueChange={setCategoriaFilter}
                                        >
                                            <SelectTrigger className="h-10 w-full border-none bg-background/50 sm:w-[200px]">
                                                <SelectValue placeholder="Categoría" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Todas las categorías
                                                </SelectItem>
                                                {categorias.map((c) => (
                                                    <SelectItem
                                                        key={c.id}
                                                        value={c.id.toString()}
                                                    >
                                                        {c.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Select
                                            value={almacenFilter}
                                            onValueChange={setAlmacenFilter}
                                        >
                                            <SelectTrigger className="h-10 w-full border-none bg-background/50 sm:w-[200px]">
                                                <SelectValue placeholder="Almacén" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Todos los almacenes
                                                </SelectItem>
                                                {almacenes.map((a) => (
                                                    <SelectItem
                                                        key={a.id}
                                                        value={a.id.toString()}
                                                    >
                                                        {a.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Button
                                            variant={
                                                stockBajoFilter
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                            className={`h-10 gap-2 border-none px-4 shadow-sm ${stockBajoFilter ? 'bg-destructive text-destructive-foreground' : 'bg-background/50 text-muted-foreground'}`}
                                            onClick={() =>
                                                setStockBajoFilter(
                                                    !stockBajoFilter,
                                                )
                                            }
                                        >
                                            <AlertTriangle className="h-4 w-4" />
                                            <span className="hidden sm:inline">
                                                Stock Bajo
                                            </span>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="h-10 w-10 border-none bg-background/50"
                                            onClick={limpiarFiltros}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                {gruposPorCategoria.length === 0 ? (
                                    <div className="py-12 text-center text-muted-foreground">
                                        <Package className="mx-auto mb-3 h-12 w-12 opacity-30" />
                                        <p>No se encontraron productos.</p>
                                    </div>
                                ) : (
                                    <div className="flex flex-col gap-3 p-3 sm:p-4">
                                        {gruposPorCategoria.map((grupo) => (
                                            <SectionPanel
                                                key={grupo.nombre}
                                                title={grupo.nombre}
                                                description={`Panel independiente de productos en la categoría ${grupo.nombre}.`}
                                                icon={Tags}
                                                tone="blue"
                                                count={grupo.data.length}
                                                badges={
                                                    grupo.stockBajo > 0 ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-200 bg-amber-50 text-[10px] text-amber-700"
                                                        >
                                                            <AlertTriangle className="h-3 w-3" />
                                                            {grupo.stockBajo}{' '}
                                                            stock bajo
                                                        </Badge>
                                                    ) : null
                                                }
                                            >
                                                {viewMode === 'table' ? (
                                                    <>
                                                        <div className="hidden overflow-x-auto md:block">
                                                            <table className="w-full">
                                                                <thead>
                                                                    <tr className="border-b bg-muted/5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                                                        <th className="px-6 py-4 text-left">
                                                                            SKU
                                                                            /
                                                                            Producto
                                                                        </th>
                                                                        <th className="px-6 py-4 text-left">
                                                                            Categoría
                                                                        </th>
                                                                        <th className="px-6 py-4 text-left">
                                                                            Descripción
                                                                        </th>
                                                                        <th className="px-6 py-4 text-right">
                                                                            Precio
                                                                            Venta
                                                                        </th>
                                                                        <th className="px-6 py-4 text-center">
                                                                            Stock
                                                                            Actual
                                                                        </th>
                                                                        <th className="px-6 py-4 text-left">
                                                                            Estado
                                                                        </th>
                                                                        <th className="px-6 py-4 text-right">
                                                                            Acciones
                                                                        </th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody className="divide-y divide-muted/50">
                                                                    {grupo.data.map(
                                                                        (p) => (
                                                                            <tr
                                                                                key={
                                                                                    p.id
                                                                                }
                                                                                className="group transition-colors hover:bg-muted/30"
                                                                            >
                                                                                <td className="px-6 py-4">
                                                                                    <div className="flex items-center gap-3">
                                                                                        <div className="relative h-10 w-10 overflow-hidden rounded-lg border border-muted-foreground/10 bg-muted shadow-sm">
                                                                                            {p.imagen ? (
                                                                                                <img
                                                                                                    src={`/storage/${p.imagen}`}
                                                                                                    className="h-full w-full object-cover"
                                                                                                    alt={
                                                                                                        p.nombre
                                                                                                    }
                                                                                                />
                                                                                            ) : (
                                                                                                <ImageIcon className="h-full w-full p-2 text-muted-foreground/30" />
                                                                                            )}
                                                                                        </div>
                                                                                        <div>
                                                                                            <div className="text-sm font-bold tracking-tight">
                                                                                                {
                                                                                                    p.nombre
                                                                                                }
                                                                                            </div>
                                                                                            <div className="flex items-center gap-1.5 font-mono text-[10px] leading-none text-muted-foreground">
                                                                                                <ArrowDownZA className="h-2.5 w-2.5" />
                                                                                                {
                                                                                                    p.codigo
                                                                                                }
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </td>
                                                                                <td className="px-6 py-4">
                                                                                    <Badge
                                                                                        variant="outline"
                                                                                        className="border-primary/20 bg-primary/5 text-[10px] font-bold text-primary"
                                                                                    >
                                                                                        {p
                                                                                            .categoria
                                                                                            ?.nombre ||
                                                                                            'General'}
                                                                                    </Badge>
                                                                                </td>
                                                                                <td className="max-w-[260px] px-6 py-4">
                                                                                    <TruncatedText
                                                                                        text={
                                                                                            p.descripcion
                                                                                        }
                                                                                        emptyLabel="Sin descripción"
                                                                                        onExpand={() => {
                                                                                            setViendo(
                                                                                                p,
                                                                                            );
                                                                                            setIsViewOpen(
                                                                                                true,
                                                                                            );
                                                                                        }}
                                                                                    />
                                                                                </td>
                                                                                <td className="px-6 py-4 text-right font-mono font-black text-foreground">
                                                                                    {formatCurrency(
                                                                                        p.precio_venta,
                                                                                    )}
                                                                                </td>
                                                                                <td className="px-6 py-4 text-center">
                                                                                    <div
                                                                                        className={`inline-flex items-center gap-1 text-sm font-black ${(p.inventarios?.reduce((sum, inv) => sum + inv.cantidad, 0) || 0) <= (p.inventarios?.reduce((sum, inv) => sum + inv.cantidad_minima, 0) || 0) ? 'text-red-500' : 'text-green-600'}`}
                                                                                    >
                                                                                        {Math.round(
                                                                                            p.inventarios?.reduce(
                                                                                                (
                                                                                                    sum,
                                                                                                    inv,
                                                                                                ) =>
                                                                                                    sum +
                                                                                                    inv.cantidad,
                                                                                                0,
                                                                                            ) ||
                                                                                                0,
                                                                                        )}
                                                                                        <span className="text-[10px] font-medium text-muted-foreground lowercase italic">
                                                                                            /{' '}
                                                                                            {Math.round(
                                                                                                p.inventarios?.reduce(
                                                                                                    (
                                                                                                        sum,
                                                                                                        inv,
                                                                                                    ) =>
                                                                                                        sum +
                                                                                                        inv.cantidad_minima,
                                                                                                    0,
                                                                                                ) ||
                                                                                                    0,
                                                                                            )}
                                                                                        </span>
                                                                                    </div>
                                                                                </td>
                                                                                <td className="px-6 py-4">
                                                                                    {p.activo ? (
                                                                                        <Badge className="rounded-full border border-green-200 bg-green-500/10 px-2 py-0.5 text-[10px] font-black text-green-600 uppercase">
                                                                                            Activo
                                                                                        </Badge>
                                                                                    ) : (
                                                                                        <Badge className="rounded-full border border-gray-200 bg-gray-500/10 px-2 py-0.5 text-[10px] font-black text-gray-500 uppercase">
                                                                                            Inactivo
                                                                                        </Badge>
                                                                                    )}
                                                                                </td>
                                                                                <td className="px-6 py-4 text-right">
                                                                                    <div className="flex justify-end gap-1">
                                                                                        <Button
                                                                                            variant="ghost"
                                                                                            size="icon"
                                                                                            className="h-8 w-8 text-emerald-600 hover:bg-emerald-50"
                                                                                            onClick={() => {
                                                                                                setViendo(
                                                                                                    p,
                                                                                                );
                                                                                                setIsViewOpen(
                                                                                                    true,
                                                                                                );
                                                                                            }}
                                                                                        >
                                                                                            <Eye className="h-4 w-4" />
                                                                                        </Button>
                                                                                        {canEdit && (
                                                                                            <Button
                                                                                                variant="ghost"
                                                                                                size="icon"
                                                                                                className="h-8 w-8 text-primary hover:bg-primary/10"
                                                                                                onClick={() =>
                                                                                                    handleEdit(
                                                                                                        p,
                                                                                                    )
                                                                                                }
                                                                                            >
                                                                                                <Pencil className="h-4 w-4" />
                                                                                            </Button>
                                                                                        )}
                                                                                        {canDelete && (
                                                                                            <Button
                                                                                                variant="ghost"
                                                                                                size="icon"
                                                                                                className="h-8 w-8 text-destructive hover:bg-destructive/10"
                                                                                                onClick={() =>
                                                                                                    handleDelete(
                                                                                                        p.id,
                                                                                                    )
                                                                                                }
                                                                                            >
                                                                                                <Trash2 className="h-4 w-4" />
                                                                                            </Button>
                                                                                        )}
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        ),
                                                                    )}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <div className="grid gap-2 p-2 sm:p-3 md:hidden">
                                                            {grupo.data.map(
                                                                (p) => (
                                                                    <div
                                                                        key={
                                                                            p.id
                                                                        }
                                                                        className="rounded-xl border bg-card p-3 shadow-sm"
                                                                    >
                                                                        <div className="flex items-start gap-3">
                                                                            <div className="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-muted">
                                                                                {p.imagen ? (
                                                                                    <img
                                                                                        src={`/storage/${p.imagen}`}
                                                                                        alt={
                                                                                            p.nombre
                                                                                        }
                                                                                        className="h-full w-full object-cover"
                                                                                    />
                                                                                ) : (
                                                                                    <div className="flex h-full w-full items-center justify-center">
                                                                                        <ImageIcon className="h-5 w-5 text-muted-foreground" />
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                            <div className="min-w-0 flex-1">
                                                                                <div className="flex items-start justify-between gap-2">
                                                                                    <div className="min-w-0">
                                                                                        <p className="truncate text-sm font-bold">
                                                                                            {
                                                                                                p.nombre
                                                                                            }
                                                                                        </p>
                                                                                        <p className="font-mono text-[10px] text-muted-foreground">
                                                                                            {
                                                                                                p.codigo
                                                                                            }
                                                                                        </p>
                                                                                    </div>
                                                                                    {p.activo ? (
                                                                                        <Badge className="border-green-200 bg-green-500/10 px-2 py-0.5 text-[10px] font-black text-green-600 uppercase">
                                                                                            Activo
                                                                                        </Badge>
                                                                                    ) : (
                                                                                        <Badge className="border-gray-200 bg-gray-500/10 px-2 py-0.5 text-[10px] font-black text-gray-500 uppercase">
                                                                                            Inactivo
                                                                                        </Badge>
                                                                                    )}
                                                                                </div>
                                                                                <div className="mt-1">
                                                                                    <TruncatedText
                                                                                        text={
                                                                                            p.descripcion
                                                                                        }
                                                                                        emptyLabel="Sin descripción"
                                                                                        lines={
                                                                                            2
                                                                                        }
                                                                                        onExpand={() => {
                                                                                            setViendo(
                                                                                                p,
                                                                                            );
                                                                                            setIsViewOpen(
                                                                                                true,
                                                                                            );
                                                                                        }}
                                                                                    />
                                                                                </div>
                                                                                <div className="mt-2 flex items-center justify-between border-t pt-2">
                                                                                    <span className="text-xs font-black">
                                                                                        {formatCurrency(
                                                                                            p.precio_venta,
                                                                                        )}
                                                                                    </span>
                                                                                    <div className="flex items-center gap-1">
                                                                                        <Button
                                                                                            variant="ghost"
                                                                                            size="sm"
                                                                                            className="h-8 px-2 text-xs"
                                                                                            onClick={() => {
                                                                                                setViendo(
                                                                                                    p,
                                                                                                );
                                                                                                setIsViewOpen(
                                                                                                    true,
                                                                                                );
                                                                                            }}
                                                                                        >
                                                                                            <Eye className="mr-1 h-3.5 w-3.5" />
                                                                                            Ver
                                                                                        </Button>
                                                                                        {canEdit && (
                                                                                            <Button
                                                                                                variant="ghost"
                                                                                                size="sm"
                                                                                                className="h-8 px-2 text-xs"
                                                                                                onClick={() =>
                                                                                                    handleEdit(
                                                                                                        p,
                                                                                                    )
                                                                                                }
                                                                                            >
                                                                                                <Pencil className="mr-1 h-3.5 w-3.5" />
                                                                                                Editar
                                                                                            </Button>
                                                                                        )}
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    </>
                                                ) : (
                                                    <div className="grid grid-cols-1 gap-4 p-2 sm:grid-cols-2 sm:p-3 lg:grid-cols-3">
                                                        {grupo.data.map((p) => (
                                                            <div
                                                                key={p.id}
                                                                className="group relative overflow-hidden rounded-xl border bg-card transition-all hover:shadow-lg"
                                                            >
                                                                <div className="relative h-36 overflow-hidden bg-muted">
                                                                    {p.imagen ? (
                                                                        <img
                                                                            src={`/storage/${p.imagen}`}
                                                                            alt={
                                                                                p.nombre
                                                                            }
                                                                            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                                        />
                                                                    ) : (
                                                                        <div className="flex h-full items-center justify-center">
                                                                            <Package className="h-12 w-12 text-muted-foreground/30" />
                                                                        </div>
                                                                    )}
                                                                    <div className="absolute top-2 right-2">
                                                                        {p.activo ? (
                                                                            <Badge className="border-green-200 bg-green-500/10 px-2 py-0.5 text-[10px] font-black text-green-600 uppercase">
                                                                                Activo
                                                                            </Badge>
                                                                        ) : (
                                                                            <Badge className="border-gray-200 bg-gray-500/10 px-2 py-0.5 text-[10px] font-black text-gray-500 uppercase">
                                                                                Inactivo
                                                                            </Badge>
                                                                        )}
                                                                    </div>
                                                                    {p.categoria && (
                                                                        <div className="absolute top-2 left-2">
                                                                            <Badge
                                                                                variant="secondary"
                                                                                className="px-2 py-0.5 text-[10px]"
                                                                            >
                                                                                {
                                                                                    p
                                                                                        .categoria
                                                                                        .nombre
                                                                                }
                                                                            </Badge>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                                <div className="space-y-2 p-4">
                                                                    <div className="flex items-start justify-between gap-2">
                                                                        <div>
                                                                            <h3 className="leading-tight font-semibold">
                                                                                {
                                                                                    p.nombre
                                                                                }
                                                                            </h3>
                                                                            <p className="font-mono text-xs text-muted-foreground">
                                                                                {
                                                                                    p.codigo
                                                                                }
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <TruncatedText
                                                                        text={
                                                                            p.descripcion
                                                                        }
                                                                        lines={
                                                                            2
                                                                        }
                                                                        emptyLabel="Sin descripción"
                                                                        onExpand={() => {
                                                                            setViendo(
                                                                                p,
                                                                            );
                                                                            setIsViewOpen(
                                                                                true,
                                                                            );
                                                                        }}
                                                                    />
                                                                    <div className="space-y-1 text-xs text-muted-foreground">
                                                                        <div className="flex items-center gap-1.5">
                                                                            <Tag className="h-3.5 w-3.5 shrink-0" />
                                                                            <span>
                                                                                {formatCurrency(
                                                                                    p.precio_venta,
                                                                                )}
                                                                            </span>
                                                                        </div>
                                                                        <div className="flex items-center gap-1.5">
                                                                            <Package className="h-3.5 w-3.5 shrink-0" />
                                                                            <span
                                                                                className={
                                                                                    (p.inventarios?.reduce(
                                                                                        (
                                                                                            sum,
                                                                                            inv,
                                                                                        ) =>
                                                                                            sum +
                                                                                            inv.cantidad,
                                                                                        0,
                                                                                    ) ||
                                                                                        0) <=
                                                                                    (p.inventarios?.reduce(
                                                                                        (
                                                                                            sum,
                                                                                            inv,
                                                                                        ) =>
                                                                                            sum +
                                                                                            inv.cantidad_minima,
                                                                                        0,
                                                                                    ) ||
                                                                                        0)
                                                                                        ? 'font-medium text-red-500'
                                                                                        : ''
                                                                                }
                                                                            >
                                                                                Stock:{' '}
                                                                                {Math.round(
                                                                                    p.inventarios?.reduce(
                                                                                        (
                                                                                            sum,
                                                                                            inv,
                                                                                        ) =>
                                                                                            sum +
                                                                                            inv.cantidad,
                                                                                        0,
                                                                                    ) ||
                                                                                        0,
                                                                                )}{' '}
                                                                                /{' '}
                                                                                {Math.round(
                                                                                    p.inventarios?.reduce(
                                                                                        (
                                                                                            sum,
                                                                                            inv,
                                                                                        ) =>
                                                                                            sum +
                                                                                            inv.cantidad_minima,
                                                                                        0,
                                                                                    ) ||
                                                                                        0,
                                                                                )}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div className="flex items-center gap-1 pt-1">
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            className="h-8 px-2 text-xs"
                                                                            onClick={() => {
                                                                                setViendo(
                                                                                    p,
                                                                                );
                                                                                setIsViewOpen(
                                                                                    true,
                                                                                );
                                                                            }}
                                                                        >
                                                                            <Eye className="mr-1 h-3.5 w-3.5" />{' '}
                                                                            Ver
                                                                        </Button>
                                                                        {canEdit && (
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                className="h-8 px-2 text-xs"
                                                                                onClick={() =>
                                                                                    handleEdit(
                                                                                        p,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Pencil className="mr-1 h-3.5 w-3.5" />{' '}
                                                                                Editar
                                                                            </Button>
                                                                        )}
                                                                        {canDelete && (
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                className="h-8 px-2 text-xs text-destructive hover:text-destructive"
                                                                                onClick={() =>
                                                                                    handleDelete(
                                                                                        p.id,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Trash2 className="mr-1 h-3.5 w-3.5" />{' '}
                                                                                Eliminar
                                                                            </Button>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </SectionPanel>
                                        ))}
                                    </div>
                                )}
                                <div className="border-t border-muted/50 p-4">
                                    <Pagination
                                        links={productos.links}
                                        meta={productos.meta || productos}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Create/Edit dialog */}
                <Dialog open={isOpen} onOpenChange={setIsOpen}>
                    <DialogContent className="flex max-h-[90vh] max-w-[95vw] flex-col overflow-y-auto border-none p-0 shadow-2xl md:max-w-5xl">
                        <DialogHeader className="shrink-0 bg-gradient-to-r from-primary/10 to-transparent p-6 pb-4 text-left">
                            <div className="mb-1 flex items-center gap-2">
                                <Tag className="h-5 w-5 text-primary" />
                                <span className="text-[10px] font-black tracking-widest text-primary/70 uppercase">
                                    Módulo de Catálogo
                                </span>
                            </div>
                            <DialogTitle className="text-2xl font-black tracking-tight text-primary">
                                {editando
                                    ? 'Modificar Ficha de Producto'
                                    : 'Alta de Nuevo Producto'}
                            </DialogTitle>
                        </DialogHeader>

                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-1 flex-col overflow-hidden"
                        >
                            <div className="flex-1 overflow-y-auto p-6 pt-2">
                                <div className="grid gap-8 py-4">
                                    <div className="grid grid-cols-1 gap-8 md:grid-cols-12">
                                        {/* Left Column: Info */}
                                        <div className="space-y-6 md:col-span-8">
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                        Código SKU (opcional)
                                                    </Label>
                                                    <div className="flex gap-2">
                                                        <Input
                                                            value={data.codigo}
                                                            onChange={(e) => {
                                                                setData(
                                                                    'codigo',
                                                                    e.target
                                                                        .value,
                                                                );
                                                                setBarcodeProduct(
                                                                    null,
                                                                );
                                                                barcodeLookupRequest.current += 1;
                                                                setBarcodeLookupLoading(
                                                                    false,
                                                                );
                                                            }}
                                                            onBlur={() =>
                                                                void handleCodigoLookup(
                                                                    data.codigo,
                                                                )
                                                            }
                                                            onKeyDown={(e) => {
                                                                if (e.key === 'Enter') {
                                                                    e.preventDefault();
                                                                    void handleCodigoLookup(
                                                                        data.codigo,
                                                                    );
                                                                }
                                                            }}
                                                            placeholder="Ej: PRO-001"
                                                            className="h-11 flex-1 border-none bg-muted/30 font-black focus-visible:ring-primary/20"
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            className="h-11 w-11 shrink-0"
                                                            aria-label="Escanear código SKU con la cámara"
                                                            title="Escanear SKU con la cámara"
                                                            onClick={() =>
                                                                openSkuScanner(
                                                                    'codigo',
                                                                )
                                                            }
                                                        >
                                                            <Camera className="h-5 w-5" />
                                                        </Button>
                                                    </div>
                                                    {errors.codigo && (
                                                        <p className="text-[10px] font-bold text-destructive">
                                                            {errors.codigo}
                                                        </p>
                                                    )}
                                                    <p className="text-xs text-muted-foreground">
                                                        Ingresa un UPC/EAN o
                                                        escanéalo para buscar
                                                        datos del producto.
                                                    </p>
                                                    {skuHydrationNotice && (
                                                        <p
                                                            className={`text-xs ${
                                                                skuHydrationNotice.type === 'success'
                                                                    ? 'text-emerald-700 font-bold'
                                                                    : 'text-amber-700 font-bold'
                                                            } flex items-center gap-1.5 mb-1`}
                                                        >
                                                            {skuHydrationNotice.type === 'success' ? (
                                                                <Info className="h-3.5 w-3.5" />
                                                            ) : (
                                                                <AlertCircle className="h-3.5 w-3.5" />
                                                            )}
                                                            {skuHydrationNotice.message}
                                                        </p>
                                                    )}
                                                    {skuHydrationLoading && (
                                                        <p
                                                            className="text-xs text-muted-foreground flex items-center gap-1.5 mb-1"
                                                            role="status"
                                                        >
                                                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                            Consultando SKU...
                                                        </p>
                                                    )}
                                                    {barcodeLookupLoading && (
                                                        <p
                                                            className="text-xs text-muted-foreground"
                                                            role="status"
                                                        >
                                                            Buscando producto...
                                                        </p>
                                                    )}
                                                    {barcodeProduct && (
                                                        <div className="flex items-start gap-3 rounded-lg border bg-background p-3">
                                                            {barcodeProduct.imagen && (
                                                                <img
                                                                    src={
                                                                        barcodeProduct.imagen
                                                                    }
                                                                    alt={
                                                                        barcodeProduct.nombre ??
                                                                        'Producto encontrado'
                                                                    }
                                                                    className="h-16 w-16 rounded object-contain"
                                                                    loading="lazy"
                                                                />
                                                            )}
                                                            <div className="min-w-0 text-sm">
                                                                <p className="font-semibold">
                                                                    Datos
                                                                    sugeridos
                                                                </p>
                                                                {barcodeProduct.marca && (
                                                                    <p>
                                                                        Marca:{' '}
                                                                        {
                                                                            barcodeProduct.marca
                                                                        }
                                                                    </p>
                                                                )}
                                                                {barcodeProduct.categoria && (
                                                                    <p>
                                                                        Categoría:{' '}
                                                                        {
                                                                            barcodeProduct.categoria
                                                                        }
                                                                    </p>
                                                                )}
                                                                {barcodeProduct.formato && (
                                                                    <p>
                                                                        Formato:{' '}
                                                                        {
                                                                            barcodeProduct.formato
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="space-y-2">
                                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                        Nombre Comercial *
                                                    </Label>
                                                    <Input
                                                        value={data.nombre}
                                                        onChange={(e) =>
                                                            setData(
                                                                'nombre',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                        className="h-11 border-none bg-muted/30 font-bold"
                                                    />
                                                    {errors.nombre && (
                                                        <p className="text-[10px] font-bold text-destructive">
                                                            {errors.nombre}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                        Categoría Principal
                                                    </Label>
                                                    <Select
                                                        value={
                                                            data.categoria_id
                                                        }
                                                        onValueChange={(v) =>
                                                            setData(
                                                                'categoria_id',
                                                                v,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="h-11 border-none bg-muted/30 font-bold">
                                                            <SelectValue placeholder="Seleccione..." />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {categorias.map(
                                                                (c) => (
                                                                    <SelectItem
                                                                        key={
                                                                            c.id
                                                                        }
                                                                        value={c.id.toString()}
                                                                    >
                                                                        {
                                                                            c.nombre
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label className="text-sm font-semibold text-slate-700">
                                                        Almacenes Disponibles
                                                    </Label>
                                                    <div className="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                                        {almacenes.map(
                                                            (almacen) => (
                                                                <label
                                                                    key={
                                                                        almacen.id
                                                                    }
                                                                    className="flex cursor-pointer items-center gap-2.5 rounded-lg p-2 transition-colors select-none hover:bg-white"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={data.warehouse_ids.includes(
                                                                            almacen.id,
                                                                        )}
                                                                        onChange={(
                                                                            e,
                                                                        ) => {
                                                                            const checked =
                                                                                e
                                                                                    .target
                                                                                    .checked;
                                                                            const newIds =
                                                                                checked
                                                                                    ? [
                                                                                          ...data.warehouse_ids,
                                                                                          almacen.id,
                                                                                      ]
                                                                                    : data.warehouse_ids.filter(
                                                                                          (
                                                                                              id,
                                                                                          ) =>
                                                                                              id !==
                                                                                              almacen.id,
                                                                                      );
                                                                            setData(
                                                                                'warehouse_ids',
                                                                                newIds,
                                                                            );
                                                                        }}
                                                                        className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                                                                    />
                                                                    <span className="text-xs font-medium text-slate-700">
                                                                        {
                                                                            almacen.nombre
                                                                        }
                                                                    </span>
                                                                </label>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="space-y-4 rounded-xl border border-emerald-500/10 bg-emerald-50 p-4">
                                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label className="text-xs font-bold tracking-wider text-emerald-600 uppercase">
                                                            Unidad de Medida *
                                                        </Label>
                                                        <Select
                                                            value={
                                                                data.unidad_medida
                                                            }
                                                            onValueChange={(
                                                                v: any,
                                                            ) => {
                                                                setData(
                                                                    'unidad_medida',
                                                                    v,
                                                                );
                                                                if (
                                                                    v !==
                                                                    'unidad'
                                                                ) {
                                                                    setData(
                                                                        'medida_pesable',
                                                                        true,
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            <SelectTrigger className="h-11 border-none bg-background font-bold">
                                                                <SelectValue placeholder="Seleccione..." />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="unidad">
                                                                    Unidad (Pza
                                                                    / Cilindro)
                                                                </SelectItem>
                                                                <SelectItem value="kg">
                                                                    Kilogramo
                                                                    (KG)
                                                                </SelectItem>
                                                                <SelectItem value="lt">
                                                                    Litro (LT)
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="flex items-center justify-between pt-6">
                                                        <div>
                                                            <Label className="text-xs font-bold text-emerald-600 uppercase">
                                                                ¿Es pesable /
                                                                métrico?
                                                            </Label>
                                                        </div>
                                                        <Switch
                                                            checked={
                                                                data.medida_pesable ||
                                                                data.unidad_medida !==
                                                                    'unidad'
                                                            }
                                                            onCheckedChange={(
                                                                v,
                                                            ) =>
                                                                setData(
                                                                    'medida_pesable',
                                                                    v,
                                                                )
                                                            }
                                                            disabled={
                                                                data.unidad_medida !==
                                                                'unidad'
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                                {(data.unidad_medida !==
                                                    'unidad' ||
                                                    data.medida_pesable) && (
                                                    <div className="mt-2 grid grid-cols-1 gap-4 md:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label className="text-xs font-bold text-emerald-600">
                                                                ¿Cuántos
                                                                kilos/litros
                                                                contiene cada
                                                                unidad?
                                                            </Label>
                                                            <Input
                                                                type="number"
                                                                value={
                                                                    data.contenido_por_unidad
                                                                }
                                                                onChange={(e) =>
                                                                    setData(
                                                                        'contenido_por_unidad',
                                                                        parseFloat(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ) || 0,
                                                                    )
                                                                }
                                                                className="h-10 border-none bg-background font-black"
                                                                placeholder="Ej: 15"
                                                            />
                                                        </div>
                                                    </div>
                                                )}
                                                {/* Envase retornable section */}
                                                <div className="space-y-4 rounded-xl border border-primary/10 bg-primary/5 p-4">
                                                    <div className="flex items-center justify-between pt-2">
                                                        <div>
                                                            <Label className="text-xs font-bold tracking-wider text-primary uppercase">
                                                                Envase
                                                                Retornable
                                                            </Label>
                                                        </div>
                                                        <Switch
                                                            checked={
                                                                data.envase_retornable
                                                            }
                                                            onCheckedChange={(
                                                                v,
                                                            ) => {
                                                                setData(
                                                                    'envase_retornable',
                                                                    v,
                                                                );
                                                                if (!v) {
                                                                    setData(
                                                                        'envase_producto_id',
                                                                        null,
                                                                    );
                                                                }
                                                            }}
                                                        />
                                                    </div>
                                                    {data.envase_retornable && (
                                                        <div className="mt-4">
                                                            <Label className="text-xs font-bold tracking-wider text-primary uppercase">
                                                                Envase Físico
                                                                Asociado
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    data.envase_producto_id ===
                                                                    null
                                                                        ? 'none'
                                                                        : data.envase_producto_id ===
                                                                            0
                                                                          ? 'self'
                                                                          : String(
                                                                                data.envase_producto_id,
                                                                            )
                                                                }
                                                                onValueChange={(
                                                                    v,
                                                                ) => {
                                                                    const isSelf =
                                                                        v ===
                                                                        'self';
                                                                    setIsSelfSelected(
                                                                        isSelf,
                                                                    );
                                                                    setData(
                                                                        'envase_producto_id',
                                                                        v ===
                                                                            'none'
                                                                            ? null
                                                                            : isSelf
                                                                              ? 0
                                                                              : Number(
                                                                                    v,
                                                                                ),
                                                                    );
                                                                }}
                                                            >
                                                                <SelectTrigger className="h-10 border-none bg-background font-black">
                                                                    <SelectValue
                                                                        className={
                                                                            isSelfSelected
                                                                                ? 'font-semibold text-primary'
                                                                                : ''
                                                                        }
                                                                        placeholder="Seleccione el envase físico..."
                                                                    />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="none">
                                                                        Ninguno
                                                                        (producto
                                                                        no
                                                                        retornable)
                                                                    </SelectItem>
                                                                    {(!editando &&
                                                                        data.envase_retornable) ||
                                                                    data.envase_producto_id ===
                                                                        0 ? (
                                                                        <SelectItem value="self">
                                                                            Seleccione
                                                                            este
                                                                            producto
                                                                            como
                                                                            envase
                                                                            físico
                                                                        </SelectItem>
                                                                    ) : null}
                                                                    {productosEnvase.map(
                                                                        (
                                                                            envase,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    envase.id
                                                                                }
                                                                                value={envase.id.toString()}
                                                                            >
                                                                                {
                                                                                    envase.nombre
                                                                                }{' '}
                                                                                (
                                                                                {
                                                                                    envase.codigo
                                                                                }

                                                                                )
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                    Descripción Detallada
                                                </Label>
                                                <textarea
                                                    value={data.descripcion}
                                                    onChange={(e) =>
                                                        setData(
                                                            'descripcion',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="flex min-h-[100px] w-full rounded-xl border-none bg-muted/30 px-3 py-2 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                                                    placeholder="Detalles técnicos, características, beneficios..."
                                                />
                                            </div>

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                                <div className="space-y-2 rounded-2xl border border-primary/10 bg-primary/5 p-4 transition-all hover:bg-primary/10">
                                                    <Label className="flex items-center gap-2 text-[10px] font-black tracking-widest text-primary uppercase">
                                                        <ArrowDownZA className="h-3 w-3" />{' '}
                                                        {productoSeVendePorMedida &&
                                                        data.unidad_medida !==
                                                            'unidad'
                                                            ? `Costo por ${data.unidad_medida === 'kg' ? 'Kilo' : 'Litro'}`
                                                            : 'Costo Adq.'}
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        step={
                                                            productoSeVendePorMedida &&
                                                            data.unidad_medida !==
                                                                'unidad'
                                                                ? '0.01'
                                                                : '1'
                                                        }
                                                        value={
                                                            data.precio_compra
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'precio_compra',
                                                                productoSeVendePorMedida &&
                                                                    data.unidad_medida !==
                                                                        'unidad'
                                                                    ? parseFloat(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      ) || 0
                                                                    : Math.floor(
                                                                          parseFloat(
                                                                              e
                                                                                  .target
                                                                                  .value,
                                                                          ),
                                                                      ) || 0,
                                                            )
                                                        }
                                                        className="h-10 border-none bg-background text-lg font-black shadow-inner focus-visible:ring-primary/20"
                                                    />
                                                </div>
                                                <div className="space-y-2 rounded-2xl border border-green-500/10 bg-green-500/5 p-4 transition-all hover:bg-green-500/10">
                                                    <Label className="flex items-center gap-2 text-[10px] font-black tracking-widest text-green-600 uppercase">
                                                        <Tag className="h-3 w-3" />{' '}
                                                        {productoSeVendePorMedida &&
                                                        data.unidad_medida !==
                                                            'unidad'
                                                            ? `Precio por ${data.unidad_medida === 'kg' ? 'Kilo' : 'Litro'}`
                                                            : 'Precio Venta'}
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        step={
                                                            productoSeVendePorMedida &&
                                                            data.unidad_medida !==
                                                                'unidad'
                                                                ? '0.01'
                                                                : '1'
                                                        }
                                                        value={
                                                            data.precio_venta
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'precio_venta',
                                                                productoSeVendePorMedida &&
                                                                    data.unidad_medida !==
                                                                        'unidad'
                                                                    ? parseFloat(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      ) || 0
                                                                    : Math.floor(
                                                                          parseFloat(
                                                                              e
                                                                                  .target
                                                                                  .value,
                                                                          ),
                                                                      ) || 0,
                                                            )
                                                        }
                                                        className="h-10 border-none bg-background text-lg font-black text-green-600 shadow-inner focus-visible:ring-green-500/20"
                                                    />
                                                    {productoSeVendePorMedida &&
                                                        data.unidad_medida !==
                                                            'unidad' && (
                                                            <p className="text-xs text-muted-foreground">
                                                                Valor por{' '}
                                                                {data.unidad_medida ===
                                                                'kg'
                                                                    ? 'kilogramo'
                                                                    : 'litro'}
                                                                ; el POS calcula
                                                                el total según
                                                                la cantidad.
                                                            </p>
                                                        )}
                                                </div>
                                                <div className="space-y-2 rounded-2xl border border-emerald-500/10 bg-emerald-500/5 p-4">
                                                    <Label className="flex items-center gap-2 text-[10px] font-black tracking-widest text-emerald-700 uppercase">
                                                        <TrendingUp className="h-3 w-3" />
                                                        Margen de Ganancia
                                                        {productoSeVendePorMedida &&
                                                            data.unidad_medida !==
                                                                'unidad' &&
                                                            ` / ${data.unidad_medida === 'kg' ? 'kg' : 'lt'}`}
                                                    </Label>
                                                    <p className="h-10 truncate text-lg font-black text-emerald-700">
                                                        {formatCurrency(
                                                            margenAbsoluto,
                                                        )}
                                                    </p>
                                                    <p className="text-xs font-semibold text-muted-foreground">
                                                        {margenPorcentual.toFixed(
                                                            2,
                                                        )}
                                                        % sobre precio de venta
                                                    </p>
                                                </div>
                                                <div className="space-y-2 rounded-2xl border border-violet-500/10 bg-violet-500/5 p-4 transition-all hover:bg-violet-500/10">
                                                    <Label className="flex items-center gap-2 text-[10px] font-black tracking-widest text-violet-700 uppercase">
                                                        <Wallet className="h-3 w-3" />
                                                        Comisión Cajero /{' '}
                                                        {productoSeVendePorMedida &&
                                                        data.unidad_medida !==
                                                            'unidad'
                                                            ? data.unidad_medida ===
                                                              'kg'
                                                                ? 'kg'
                                                                : 'litro'
                                                            : 'Unidad'}
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={
                                                            data.comision_cajero_por_unidad
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'comision_cajero_por_unidad',
                                                                parseFloat(
                                                                    e.target
                                                                        .value,
                                                                ) || 0,
                                                            )
                                                        }
                                                        className="h-10 border-none bg-background text-lg font-black text-violet-700 shadow-inner focus-visible:ring-violet-500/20"
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        {productoSeVendePorMedida &&
                                                        data.unidad_medida !==
                                                            'unidad'
                                                            ? 'Se prorratea según la fracción de medida vendida.'
                                                            : 'Incentivo fijo por cada unidad vendida.'}
                                                    </p>
                                                </div>
                                                <div className="space-y-2 rounded-2xl border border-amber-500/10 bg-amber-500/5 p-4 transition-all hover:bg-amber-500/10">
                                                    <Label className="flex items-center gap-2 text-[10px] font-black tracking-widest text-amber-600 uppercase">
                                                        <AlertTriangle className="h-3 w-3" />{' '}
                                                        Stock Mín.
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        step="1"
                                                        value={
                                                            data.stock_minimo
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'stock_minimo',
                                                                Math.floor(
                                                                    parseFloat(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                                ) || 0,
                                                            )
                                                        }
                                                        className="h-10 border-none bg-background text-lg font-black text-amber-600 shadow-inner focus-visible:ring-amber-500/20"
                                                    />
                                                </div>
                                                <div className="space-y-2 rounded-2xl border border-purple-500/10 bg-purple-500/5 p-4 transition-all hover:bg-purple-500/10">
                                                    <Label className="flex items-center gap-2 text-[10px] font-black tracking-widest text-purple-600 uppercase">
                                                        <Calendar className="h-3 w-3" />{' '}
                                                        Vencimiento
                                                    </Label>
                                                    <Input
                                                        type="date"
                                                        value={
                                                            data.fecha_vencimiento
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'fecha_vencimiento',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-10 border-none bg-background text-lg font-black text-purple-600 shadow-inner focus-visible:ring-purple-500/20"
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                {!data.tiene_variantes && (
                                                    <div className="space-y-2 rounded-2xl border border-emerald-500/10 bg-emerald-500/5 p-4 transition-all hover:bg-emerald-500/10">
                                                        <Label className="flex items-center gap-2 text-[10px] font-black tracking-widest text-emerald-600 uppercase">
                                                            <Package className="h-3 w-3" />{' '}
                                                            Stock Inicial
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            step="1"
                                                            value={data.stock}
                                                            onChange={(e) =>
                                                                setData(
                                                                    'stock',
                                                                    Math.floor(
                                                                        parseFloat(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    ) || 0,
                                                                )
                                                            }
                                                            className="h-10 border-none bg-background text-lg font-black text-emerald-600 shadow-inner focus-visible:ring-emerald-500/20"
                                                        />
                                                    </div>
                                                )}
                                                {/* Tiene Variantes Toggle */}
                                                <div className="space-y-2 rounded-2xl border border-emerald-500/10 bg-emerald-500/5 p-4 transition-all hover:bg-emerald-500/10">
                                                    <div className="flex items-center justify-between">
                                                        <Label className="flex items-center gap-2 text-[10px] font-black tracking-widest text-emerald-600 uppercase">
                                                            <Layers className="h-3 w-3" />{' '}
                                                            Tiene Variantes
                                                        </Label>
                                                        <Switch
                                                            checked={
                                                                data.tiene_variantes
                                                            }
                                                            onCheckedChange={(
                                                                v,
                                                            ) =>
                                                                setData(
                                                                    'tiene_variantes',
                                                                    v,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <p className="text-[10px] text-muted-foreground">
                                                        Tallas, colores,
                                                        sabores, numeración,
                                                        etc.
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Variant Configuration Section */}
                                            {data.tiene_variantes && (
                                                <div className="space-y-6 rounded-xl border border-emerald-500/20 bg-emerald-50/50 p-4">
                                                    <div className="flex items-center gap-2">
                                                        <Layers className="h-5 w-5 text-emerald-600" />
                                                        <h3 className="text-sm font-black tracking-wider text-emerald-700 uppercase">
                                                            Configuración de
                                                            Variantes
                                                        </h3>
                                                    </div>

                                                    {/* Existing Variant Types */}
                                                    {variantesDisponibles.length >
                                                        0 && (
                                                        <div className="space-y-3">
                                                            <Label className="text-xs font-bold text-emerald-600">
                                                                Seleccionar
                                                                variantes
                                                                existentes
                                                            </Label>
                                                            <div className="flex flex-wrap gap-2">
                                                                {variantesDisponibles.map(
                                                                    (vt) => {
                                                                        const isSelected =
                                                                            selectedVariantIds.includes(
                                                                                vt.id,
                                                                            );
                                                                        return (
                                                                            <button
                                                                                key={
                                                                                    vt.id
                                                                                }
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    toggleVariantType(
                                                                                        vt.id,
                                                                                    )
                                                                                }
                                                                                className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold transition-all ${
                                                                                    isSelected
                                                                                        ? 'bg-emerald-600 text-white shadow-md'
                                                                                        : 'border border-emerald-200 bg-white text-emerald-600 hover:border-emerald-400'
                                                                                }`}
                                                                            >
                                                                                {vt.tipo ===
                                                                                'color' ? (
                                                                                    <Palette className="h-3 w-3" />
                                                                                ) : vt.tipo ===
                                                                                  'numero' ? (
                                                                                    <Hash className="h-3 w-3" />
                                                                                ) : (
                                                                                    <Ruler className="h-3 w-3" />
                                                                                )}
                                                                                {
                                                                                    vt.nombre
                                                                                }
                                                                                {isSelected && (
                                                                                    <span className="ml-1 rounded-full bg-white/20 px-1.5 text-[9px]">
                                                                                        {
                                                                                            vt
                                                                                                .valores
                                                                                                .length
                                                                                        }
                                                                                    </span>
                                                                                )}
                                                                            </button>
                                                                        );
                                                                    },
                                                                )}
                                                            </div>
                                                            {/* Show values for selected variants */}
                                                            {getSelectedVariants.map(
                                                                (vt) => (
                                                                    <div
                                                                        key={
                                                                            vt.id
                                                                        }
                                                                        className="rounded-lg border border-emerald-200 bg-white p-3"
                                                                    >
                                                                        <p className="mb-1 text-[10px] font-bold text-emerald-600 uppercase">
                                                                            {
                                                                                vt.nombre
                                                                            }
                                                                        </p>
                                                                        <div className="flex flex-wrap gap-1">
                                                                            {vt.valores.map(
                                                                                (
                                                                                    vl,
                                                                                ) => (
                                                                                    <Badge
                                                                                        key={
                                                                                            vl.id
                                                                                        }
                                                                                        variant="secondary"
                                                                                        className="text-[10px]"
                                                                                    >
                                                                                        {
                                                                                            vl.valor
                                                                                        }
                                                                                    </Badge>
                                                                                ),
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}

                                                    {/* Inline New Variant Creator */}
                                                    <div className="space-y-3 rounded-lg border border-dashed border-emerald-300 bg-white p-4">
                                                        <Label className="text-xs font-bold text-emerald-600">
                                                            {variantesDisponibles.length >
                                                            0
                                                                ? 'O crear nueva variante'
                                                                : 'Crear nueva variante'}
                                                        </Label>
                                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                            <div>
                                                                <Label className="text-[9px] font-bold text-muted-foreground">
                                                                    Nombre
                                                                </Label>
                                                                <Input
                                                                    value={
                                                                        newVariantName
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setNewVariantName(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Ej: Talla"
                                                                    className="h-9 border-none bg-emerald-50/50 text-xs"
                                                                />
                                                            </div>
                                                            <div>
                                                                <Label className="text-[9px] font-bold text-muted-foreground">
                                                                    Tipo
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        newVariantType
                                                                    }
                                                                    onValueChange={(
                                                                        v: any,
                                                                    ) =>
                                                                        setNewVariantType(
                                                                            v,
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="h-9 border-none bg-emerald-50/50 text-xs">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="texto">
                                                                            Texto
                                                                        </SelectItem>
                                                                        <SelectItem value="color">
                                                                            Color
                                                                        </SelectItem>
                                                                        <SelectItem value="numero">
                                                                            Número
                                                                        </SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div>
                                                                <Label className="text-[9px] font-bold text-muted-foreground">
                                                                    Valores
                                                                </Label>
                                                                <div className="flex gap-1">
                                                                    <Input
                                                                        value={
                                                                            newVariantValueInput
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            setNewVariantValueInput(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        onKeyDown={(
                                                                            e,
                                                                        ) =>
                                                                            e.key ===
                                                                                'Enter' &&
                                                                            (e.preventDefault(),
                                                                            addNewVariantValue())
                                                                        }
                                                                        placeholder="Agregar valor"
                                                                        className="h-9 border-none bg-emerald-50/50 text-xs"
                                                                    />
                                                                    <Button
                                                                        type="button"
                                                                        size="icon"
                                                                        variant="outline"
                                                                        className="h-9 w-9 shrink-0"
                                                                        onClick={
                                                                            addNewVariantValue
                                                                        }
                                                                    >
                                                                        <Plus className="h-3 w-3" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        {newVariantValues.length >
                                                            0 && (
                                                            <div className="flex flex-wrap gap-1.5">
                                                                {newVariantValues.map(
                                                                    (val) => (
                                                                        <Badge
                                                                            key={
                                                                                val
                                                                            }
                                                                            variant="secondary"
                                                                            className="flex items-center gap-1 pr-1 text-[10px]"
                                                                        >
                                                                            {
                                                                                val
                                                                            }
                                                                            <button
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    removeNewVariantValue(
                                                                                        val,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <XCircle className="h-3 w-3 text-muted-foreground hover:text-destructive" />
                                                                            </button>
                                                                        </Badge>
                                                                    ),
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* SKU Combinations Table */}
                                                    {skuRows.length > 0 && (
                                                        <div className="space-y-3">
                                                            <div className="flex items-center justify-between">
                                                                <Label className="text-xs font-bold text-emerald-600">
                                                                    Combinaciones
                                                                    SKU
                                                                    generadas (
                                                                    {
                                                                        skuRows.length
                                                                    }
                                                                    )
                                                                </Label>
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="h-7 text-[10px]"
                                                                    onClick={
                                                                        generateSkuCombinations
                                                                    }
                                                                >
                                                                    <Layers className="mr-1 h-3 w-3" />{' '}
                                                                    Regenerar
                                                                </Button>
                                                            </div>
                                                            <div className="overflow-x-auto rounded-lg border border-emerald-200">
                                                                <table className="w-full text-left text-xs">
                                                                    <thead className="bg-emerald-100 text-[9px] font-bold text-emerald-700 uppercase">
                                                                        <tr>
                                                                            {skuVariantDefinitions.map(
                                                                                (
                                                                                    vt,
                                                                                    variantIndex,
                                                                                ) => (
                                                                                    <th
                                                                                        key={
                                                                                            'id' in
                                                                                            vt
                                                                                                ? vt.id
                                                                                                : `${vt.nombre}-${variantIndex}`
                                                                                        }
                                                                                        className="px-3 py-2"
                                                                                    >
                                                                                        {
                                                                                            vt.nombre
                                                                                        }
                                                                                    </th>
                                                                                ),
                                                                            )}
                                                                            <th className="px-3 py-2">
                                                                                Imagen
                                                                            </th>
                                                                            <th className="px-3 py-2">
                                                                                SKU
                                                                            </th>
                                                                            <th className="px-3 py-2 text-right">
                                                                                Precio
                                                                                Vta
                                                                            </th>
                                                                            <th className="px-3 py-2 text-right">
                                                                                Precio
                                                                                Comp
                                                                            </th>
                                                                            <th className="px-3 py-2 text-right">
                                                                                Stock
                                                                            </th>
                                                                            <th className="px-3 py-2 text-right">
                                                                                Stock
                                                                                Mín
                                                                            </th>
                                                                            <th className="px-3 py-2">
                                                                                Almacén
                                                                            </th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody className="divide-y divide-emerald-100 bg-white">
                                                                        {skuRows.map(
                                                                            (
                                                                                row,
                                                                                i,
                                                                            ) => (
                                                                                <tr
                                                                                    key={
                                                                                        i
                                                                                    }
                                                                                    className="hover:bg-emerald-50/50"
                                                                                >
                                                                                    {row.labels.map(
                                                                                        (
                                                                                            label,
                                                                                            j,
                                                                                        ) => (
                                                                                            <td
                                                                                                key={
                                                                                                    j
                                                                                                }
                                                                                                className="px-3 py-2 font-medium text-emerald-800"
                                                                                            >
                                                                                                {label.split(
                                                                                                    ': ',
                                                                                                )[1] ||
                                                                                                    label}
                                                                                            </td>
                                                                                        ),
                                                                                    )}
                                                                                    <td className="px-3 py-2">
                                                                                        <div className="flex min-w-36 items-center gap-2">
                                                                                            {row.imagen_preview && (
                                                                                                <img
                                                                                                    src={
                                                                                                        row.imagen_preview
                                                                                                    }
                                                                                                    alt={`Imagen de ${row.labels.join(', ')}`}
                                                                                                    className="h-10 w-10 shrink-0 rounded border object-contain"
                                                                                                />
                                                                                            )}
                                                                                            <Input
                                                                                                type="file"
                                                                                                accept="image/jpeg,image/png,image/webp"
                                                                                                aria-label={`Imagen para ${row.labels.join(', ')}`}
                                                                                                className="h-8 min-w-28 text-[10px]"
                                                                                                onChange={(
                                                                                                    event,
                                                                                                ) =>
                                                                                                    updateSkuImage(
                                                                                                        i,
                                                                                                        event
                                                                                                            .target
                                                                                                            .files?.[0] ??
                                                                                                            null,
                                                                                                    )
                                                                                                }
                                                                                            />
                                                                                        </div>
                                                                                    </td>
                                                                                    <td className="px-3 py-2">
                                                                                        <div className="flex items-center gap-1">
                                                                                            <Input
                                                                                                value={
                                                                                                    row.sku
                                                                                                }
                                                                                                onChange={(
                                                                                                    e,
                                                                                                ) =>
                                                                                                    updateSkuRow(
                                                                                                        i,
                                                                                                        'sku',
                                                                                                        e
                                                                                                            .target
                                                                                                            .value,
                                                                                                    )
                                                                                                }
                                                                                                className="h-7 w-28 border-none bg-emerald-50/50 font-mono text-[10px] font-bold"
                                                                                            />
                                                                                            <Button
                                                                                                type="button"
                                                                                                variant="ghost"
                                                                                                size="icon"
                                                                                                className="h-7 w-7 shrink-0"
                                                                                                aria-label={`Escanear SKU de la variante ${row.labels.join(', ')}`}
                                                                                                title="Escanear SKU con la cámara"
                                                                                                onClick={() =>
                                                                                                    openSkuScanner(
                                                                                                        i,
                                                                                                    )
                                                                                                }
                                                                                            >
                                                                                                <Camera className="h-3.5 w-3.5" />
                                                                                            </Button>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td className="px-3 py-2 text-right">
                                                                                        <Input
                                                                                            type="number"
                                                                                            value={
                                                                                                row.precio_venta
                                                                                            }
                                                                                            onChange={(
                                                                                                e,
                                                                                            ) =>
                                                                                                updateSkuRow(
                                                                                                    i,
                                                                                                    'precio_venta',
                                                                                                    parseInt(
                                                                                                        e
                                                                                                            .target
                                                                                                            .value,
                                                                                                    ) ||
                                                                                                        0,
                                                                                                )
                                                                                            }
                                                                                            className="ml-auto h-7 w-20 border-none bg-green-50/50 text-right text-[10px] font-bold"
                                                                                        />
                                                                                    </td>
                                                                                    <td className="px-3 py-2 text-right">
                                                                                        <Input
                                                                                            type="number"
                                                                                            value={
                                                                                                row.precio_compra
                                                                                            }
                                                                                            onChange={(
                                                                                                e,
                                                                                            ) =>
                                                                                                updateSkuRow(
                                                                                                    i,
                                                                                                    'precio_compra',
                                                                                                    parseInt(
                                                                                                        e
                                                                                                            .target
                                                                                                            .value,
                                                                                                    ) ||
                                                                                                        0,
                                                                                                )
                                                                                            }
                                                                                            className="ml-auto h-7 w-20 border-none bg-primary/5 text-right text-[10px] font-bold"
                                                                                        />
                                                                                    </td>
                                                                                    <td className="px-3 py-2 text-right">
                                                                                        <Input
                                                                                            type="number"
                                                                                            value={
                                                                                                row.stock
                                                                                            }
                                                                                            onChange={(
                                                                                                e,
                                                                                            ) =>
                                                                                                updateSkuRow(
                                                                                                    i,
                                                                                                    'stock',
                                                                                                    parseInt(
                                                                                                        e
                                                                                                            .target
                                                                                                            .value,
                                                                                                    ) ||
                                                                                                        0,
                                                                                                )
                                                                                            }
                                                                                            className="ml-auto h-7 w-16 border-none bg-emerald-50/50 text-right text-[10px] font-bold"
                                                                                        />
                                                                                    </td>
                                                                                    <td className="px-3 py-2 text-right">
                                                                                        <Input
                                                                                            type="number"
                                                                                            value={
                                                                                                row.stock_minimo
                                                                                            }
                                                                                            onChange={(
                                                                                                e,
                                                                                            ) =>
                                                                                                updateSkuRow(
                                                                                                    i,
                                                                                                    'stock_minimo',
                                                                                                    parseInt(
                                                                                                        e
                                                                                                            .target
                                                                                                            .value,
                                                                                                    ) ||
                                                                                                        0,
                                                                                                )
                                                                                            }
                                                                                            className="ml-auto h-7 w-16 border-none bg-amber-50/50 text-right text-[10px] font-bold"
                                                                                        />
                                                                                    </td>
                                                                                    <td className="px-3 py-2">
                                                                                        <Select
                                                                                            value={
                                                                                                row.almacen_id
                                                                                            }
                                                                                            onValueChange={(
                                                                                                v,
                                                                                            ) =>
                                                                                                updateSkuRow(
                                                                                                    i,
                                                                                                    'almacen_id',
                                                                                                    v,
                                                                                                )
                                                                                            }
                                                                                        >
                                                                                            <SelectTrigger className="h-7 w-32 border-none bg-muted/30 text-[10px]">
                                                                                                <SelectValue placeholder="Almacén" />
                                                                                            </SelectTrigger>
                                                                                            <SelectContent>
                                                                                                {almacenes.map(
                                                                                                    (
                                                                                                        a,
                                                                                                    ) => (
                                                                                                        <SelectItem
                                                                                                            key={
                                                                                                                a.id
                                                                                                            }
                                                                                                            value={a.id.toString()}
                                                                                                        >
                                                                                                            {
                                                                                                                a.nombre
                                                                                                            }
                                                                                                        </SelectItem>
                                                                                                    ),
                                                                                                )}
                                                                                            </SelectContent>
                                                                                        </Select>
                                                                                    </td>
                                                                                </tr>
                                                                            ),
                                                                        )}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    )}

                                                    {getSelectedVariants.length ===
                                                        0 &&
                                                        !newVariantName.trim() && (
                                                            <p className="py-4 text-center text-[11px] text-muted-foreground italic">
                                                                Seleccione
                                                                variantes
                                                                existentes o
                                                                cree una nueva
                                                                para generar
                                                                combinaciones
                                                                SKU.
                                                            </p>
                                                        )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Right Column: Media */}
                                        <div className="space-y-6 md:col-span-4">
                                            <div className="space-y-4">
                                                <Label className="flex items-center gap-2 text-xs font-black tracking-wider text-muted-foreground uppercase">
                                                    <ImageIcon className="h-4 w-4" />{' '}
                                                    Fotografías del Producto
                                                </Label>

                                                <ImageUploader
                                                    images={productImages}
                                                    onChange={setProductImages}
                                                    maxImages={
                                                        PRODUCT_IMAGE_KEYS.length
                                                    }
                                                    maxFileSizeMb={2}
                                                    disabled={isSubmitting}
                                                    isSubmitting={isSubmitting}
                                                    error={imageError}
                                                    onError={setImageError}
                                                />

                                                <div className="space-y-4 rounded-2xl border border-muted-foreground/5 bg-muted/30 p-4">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <Video className="h-4 w-4 text-primary" />
                                                            <span className="text-[10px] font-bold tracking-wide uppercase">
                                                                Video Demo
                                                            </span>
                                                        </div>
                                                        {editando?.video && (
                                                            <Badge className="bg-primary/20 text-[8px] text-primary">
                                                                Preexistente
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <Input
                                                        type="file"
                                                        accept="video/*"
                                                        onChange={(e) =>
                                                            setData(
                                                                'video',
                                                                e.target
                                                                    .files?.[0] ||
                                                                    null,
                                                            )
                                                        }
                                                        className="h-8 border-none bg-transparent p-0 text-[10px]"
                                                    />
                                                </div>

                                                <div className="flex flex-col gap-2">
                                                    <div className="flex items-center justify-between px-2">
                                                        <Label className="text-[10px] font-bold uppercase">
                                                            Estado de
                                                            Visibilidad
                                                        </Label>
                                                        <Select
                                                            value={
                                                                data.activo
                                                                    ? '1'
                                                                    : '0'
                                                            }
                                                            onValueChange={(
                                                                v,
                                                            ) =>
                                                                setData(
                                                                    'activo',
                                                                    v === '1',
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="h-7 w-24 rounded-full border-none bg-muted/50 text-[10px] font-black uppercase">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="1">
                                                                    Activo
                                                                </SelectItem>
                                                                <SelectItem value="0">
                                                                    Inactivo
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="flex items-center justify-between px-2">
                                                        <Label className="text-[10px] font-bold uppercase">
                                                            Mostrar en Landing
                                                        </Label>
                                                        <Select
                                                            value={
                                                                data.mostrar_en_perfil
                                                                    ? '1'
                                                                    : '0'
                                                            }
                                                            onValueChange={(
                                                                v,
                                                            ) =>
                                                                setData(
                                                                    'mostrar_en_perfil',
                                                                    v === '1',
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="h-7 w-24 rounded-full border-none bg-muted/50 text-[10px] font-black uppercase">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="1">
                                                                    Sí
                                                                </SelectItem>
                                                                <SelectItem value="0">
                                                                    No
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter className="shrink-0 gap-2 border-t bg-muted/10 p-6">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setIsOpen(false)}
                                    className="font-bold"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing || isSubmitting}
                                    className="rounded-full bg-primary px-10 font-bold shadow-lg shadow-primary/20 hover:bg-primary/90"
                                >
                                    <Check className="mr-2 h-4 w-4" />{' '}
                                    {isSubmitting
                                        ? 'Guardando...'
                                        : editando
                                          ? 'Actualizar Ficha'
                                          : 'Guardar Producto'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <BarcodeScannerDialog
                    open={scannerOpen}
                    onOpenChange={(open) => {
                        setScannerOpen(open);

                        if (!open) {
                            setScannerTarget(null);
                        }
                    }}
                    onDetected={handleBarcodeDetected}
                    title={
                        scannerTarget === 'codigo'
                            ? 'Escanear SKU del producto'
                            : 'Escanear SKU de la variante'
                    }
                    description="Centra el código de barras del SKU dentro del recuadro."
                />

                {/* View Dialog */}
                <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
                    <DialogContent className="max-h-[85vh] max-w-[90vw] overflow-y-auto border-none bg-white p-0 shadow-xl md:max-w-2xl">
                        <DialogHeader className="relative overflow-hidden px-4 py-4 md:px-6 md:py-5">
                            <div className="absolute inset-0 bg-gradient-to-br from-emerald-600 via-emerald-700 to-emerald-900 opacity-90" />
                            <div className="absolute top-0 right-0 p-3 text-white opacity-15 md:p-4">
                                <Eye className="h-12 w-12 rotate-12 md:h-16 md:w-16" />
                            </div>

                            <div className="relative z-10 flex items-center justify-between pr-8 text-white">
                                <div>
                                    <Badge className="mb-1 w-fit border-none bg-white/20 px-2 py-0.5 text-[9px] font-bold tracking-widest text-white uppercase">
                                        Producto
                                    </Badge>
                                    <DialogTitle className="text-lg font-black tracking-tight text-white uppercase md:text-xl">
                                        {viendo?.nombre}
                                    </DialogTitle>
                                </div>
                                <div
                                    className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${viendo?.activo ? 'bg-green-500' : 'bg-red-500'}`}
                                >
                                    {viendo?.activo ? 'Activo' : 'Inactivo'}
                                </div>
                            </div>
                        </DialogHeader>

                        {viendo && (
                            <div className="relative z-20 -mt-2 mb-3 flex max-h-[calc(85vh-100px)] flex-col gap-3 overflow-y-auto px-4 md:-mt-4 md:mb-4 md:gap-4 md:px-6">
                                <div className="grid grid-cols-2 gap-2 md:grid-cols-5">
                                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-2">
                                        <p className="text-[8px] font-bold text-emerald-600 uppercase">
                                            SKU
                                        </p>
                                        <p className="truncate text-xs font-semibold text-emerald-800">
                                            {viendo.codigo}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-2">
                                        <p className="text-[8px] font-bold text-gray-600 uppercase">
                                            Categoría
                                        </p>
                                        <p className="truncate text-xs font-semibold text-gray-800">
                                            {viendo.categoria?.nombre ||
                                                'Sin categoría'}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-2">
                                        <p className="text-[8px] font-bold text-emerald-600 uppercase">
                                            Precio
                                        </p>
                                        <p className="truncate text-xs font-semibold text-emerald-800">
                                            {formatCurrency(
                                                viendo.precio_venta || 0,
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-2">
                                        <p className="text-[8px] font-bold text-amber-600 uppercase">
                                            Stock Mín
                                        </p>
                                        <p className="truncate text-xs font-semibold text-amber-800">
                                            {viendo.stock_minimo ?? 0}
                                        </p>
                                    </div>
                                    {viendo.fecha_vencimiento && (
                                        <div className="rounded-lg border border-purple-200 bg-purple-50 p-2">
                                            <p className="text-[8px] font-bold text-purple-600 uppercase">
                                                Vencimiento
                                            </p>
                                            <p className="truncate text-xs font-semibold text-purple-800">
                                                {viendo.fecha_vencimiento}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        {viendo.imagen ? (
                                            <div className="aspect-square overflow-hidden rounded-lg border bg-gray-50">
                                                <img
                                                    src={`/storage/${viendo.imagen}`}
                                                    alt={viendo.nombre}
                                                    className="h-full w-full object-contain"
                                                />
                                            </div>
                                        ) : (
                                            <div className="flex aspect-video flex-col items-center justify-center rounded-xl border bg-gray-50 text-muted-foreground opacity-40">
                                                <ImageIcon className="h-12 w-12" />
                                                <span className="mt-2 text-[10px] font-bold tracking-widest uppercase">
                                                    Sin Imagen
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    <div className="space-y-3">
                                        <Card className="border-none bg-gray-50/50 shadow-sm">
                                            <CardHeader className="border-b border-gray-100 pb-2">
                                                <div className="flex items-center gap-2">
                                                    <div className="rounded-md bg-emerald-100 p-1 text-emerald-700">
                                                        <Package className="h-3 w-3" />
                                                    </div>
                                                    <CardTitle className="text-xs font-bold text-gray-800">
                                                        Descripción
                                                    </CardTitle>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="p-3 text-xs leading-relaxed text-gray-600 italic">
                                                {viendo.descripcion ||
                                                    'Sin descripción'}
                                            </CardContent>
                                        </Card>

                                        <div className="flex items-start gap-2 rounded-lg border border-amber-100 bg-amber-50/50 p-3">
                                            <div className="mt-0.5 rounded-full bg-amber-200 p-0.5">
                                                <Check className="h-2 w-2 text-amber-700" />
                                            </div>
                                            <div className="space-y-0.5 text-[10px] font-medium text-amber-800/70">
                                                <p>
                                                    Unidad:{' '}
                                                    <strong>
                                                        {viendo.unidad_medida?.toUpperCase()}
                                                    </strong>
                                                </p>
                                                <p>
                                                    Stock Mín:{' '}
                                                    <strong>
                                                        {Math.round(
                                                            viendo.stock_minimo ||
                                                                0,
                                                        )}
                                                    </strong>
                                                </p>
                                                <p>
                                                    Precio Compra:{' '}
                                                    <strong>
                                                        {formatCurrency(
                                                            Math.round(
                                                                viendo.precio_compra ||
                                                                    0,
                                                            ),
                                                        )}
                                                    </strong>
                                                </p>
                                                {viendo.envase_retornable && (
                                                    <p className="mt-1 flex items-center gap-1 text-primary">
                                                        <Warehouse className="h-3 w-3" />
                                                        Requiere Envase:{' '}
                                                        <strong>
                                                            {viendo
                                                                .envase_producto
                                                                ?.nombre ||
                                                                'Envase Físico (No definido)'}
                                                        </strong>
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Warehouse Stock Section */}
                                <div className="space-y-2">
                                    <h4 className="text-[10px] font-black tracking-widest text-gray-500 uppercase">
                                        Stock por Almacén
                                    </h4>
                                    <div className="overflow-x-auto rounded-xl border border-gray-100 bg-white">
                                        <table className="w-full text-left">
                                            <thead className="bg-gray-50 text-[9px] font-bold text-gray-500 uppercase">
                                                <tr>
                                                    <th className="px-4 py-2">
                                                        Almacén
                                                    </th>
                                                    <th className="px-4 py-2 text-right">
                                                        Cantidad
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-50 text-xs">
                                                {viendo.inventarios &&
                                                viendo.inventarios.length >
                                                    0 ? (
                                                    viendo.inventarios.map(
                                                        (inv) => (
                                                            <tr key={inv.id}>
                                                                <td className="px-4 py-2 font-medium text-gray-700">
                                                                    {inv.almacen
                                                                        ?.nombre ||
                                                                        'General'}
                                                                </td>
                                                                <td className="px-4 py-2 text-right font-black text-emerald-600">
                                                                    {Math.round(
                                                                        inv.cantidad,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )
                                                ) : (
                                                    <tr>
                                                        <td
                                                            colSpan={2}
                                                            className="px-4 py-4 text-center text-gray-400 italic"
                                                        >
                                                            No hay información
                                                            de stock disponible
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Legacy Variants Section */}
                                {viendo.variantes &&
                                    viendo.variantes.length > 0 && (
                                        <div className="mt-4 space-y-2">
                                            <h4 className="text-[10px] font-black tracking-widest text-purple-600 uppercase">
                                                Tallas y Variantes Disponibles
                                            </h4>
                                            <div className="overflow-x-auto rounded-xl border border-purple-100 bg-white">
                                                <table className="w-full text-left">
                                                    <thead className="bg-purple-50 text-[9px] font-bold text-purple-600 uppercase">
                                                        <tr>
                                                            <th className="px-4 py-2">
                                                                Talla
                                                            </th>
                                                            <th className="px-4 py-2">
                                                                SKU
                                                            </th>
                                                            <th className="px-4 py-2 text-right">
                                                                Stock
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-purple-50 text-xs">
                                                        {viendo.variantes.map(
                                                            (v) => (
                                                                <tr key={v.id}>
                                                                    <td className="px-4 py-2 font-black text-purple-800">
                                                                        {
                                                                            v.talla
                                                                        }
                                                                    </td>
                                                                    <td className="px-4 py-2 text-gray-500">
                                                                        {
                                                                            v.codigo
                                                                        }
                                                                    </td>
                                                                    <td className="px-4 py-2 text-right font-black text-emerald-600">
                                                                        {Math.round(
                                                                            v.inventarios?.reduce(
                                                                                (
                                                                                    sum,
                                                                                    inv,
                                                                                ) =>
                                                                                    sum +
                                                                                    inv.cantidad,
                                                                                0,
                                                                            ) ||
                                                                                0,
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}
                            </div>
                        )}

                        <DialogFooter className="border-t bg-gray-50 p-3">
                            <Button
                                variant="outline"
                                onClick={() => setIsViewOpen(false)}
                                className="w-full font-bold sm:w-auto"
                            >
                                Cerrar
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Confirmación antes de sobrescribir datos por SKU */}
                <Dialog open={showSkuConfirm} onOpenChange={setShowSkuConfirm}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>¿Aplicar datos del producto encontrado?</DialogTitle>
                            <DialogDescription>
                                Ya hay datos digitados en este formulario. Al aplicar
                                se sobrescribirán nombre, categoría, precios, stock,
                                unidad de medida, imágenes y variantes del SKU{' '}
                                <strong>{pendingSkuData?.codigo}</strong>. Los campos
                                que ya tengas llenados se reemplazarán.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setPendingSkuData(null);
                                    setShowSkuConfirm(false);
                                }}
                            >
                                Cancelar
                            </Button>
                            <Button
                                onClick={() => {
                                    if (pendingSkuData) {
                                        applySkuHydration(pendingSkuData);
                                        setSkuHydrationNotice({
                                            type: 'success',
                                            message:
                                                'Datos aplicados desde el SKU encontrado. Revisa y ajusta antes de guardar.',
                                        });
                                        toast.success(
                                            'Datos aplicados desde el SKU encontrado.',
                                        );
                                    }
                                    setPendingSkuData(null);
                                    setShowSkuConfirm(false);
                                }}
                            >
                                Aplicar datos
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </TooltipProvider>
        </AppLayout>
    );
}

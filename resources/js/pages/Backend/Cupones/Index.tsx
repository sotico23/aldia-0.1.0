import { Head, useForm, router } from '@inertiajs/react';
import {
    Gift,
    Plus,
    Search,
    Pencil,
    Trash2,
    Percent,
    DollarSign,
    Truck,
    Package,
    Copy,
    LayoutGrid,
    List,
    X,
    Check,
} from 'lucide-react';
import { useState, useCallback, useRef, useMemo } from 'react';
import { useCountry } from '@/hooks/use-country';
import TipTapEditorWithSource from '@/components/TipTapEditorWithSource';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface CuponProducto {
    id: number;
    nombre: string;
    codigo?: string;
    precio_venta?: number;
    pivot: {
        descuento_tipo: 'porcentaje' | 'precio_fijo';
        descuento_valor: number;
    };
}

interface Cupon {
    id: number;
    codigo: string;
    tipo: 'porcentaje' | 'precio_fijo' | 'envio_gratis' | 'vale_producto';
    valor: number;
    descripcion: string | null;
    plantilla_html: string | null;
    variables_ejemplo: Record<string, string> | null;
    max_usos: number;
    usos_actuales: number;
    usos_por_cliente: number;
    compra_minima: number | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    activa: boolean;
    created_at: string;
    usuario?: { id: number; name: string };
    productos?: CuponProducto[];
}

interface Producto {
    id: number;
    nombre: string;
    codigo?: string;
    precio_venta?: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationData {
    data: Cupon[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
    links: PaginationLink[];
}

interface Props {
    cupones: PaginationData;
    productos: Producto[];
    filters: { search?: string };
    flash?: { success?: string; error?: string };
}

const FieldError = ({ error }: { error?: string }) =>
    error ? <p className="mt-1 text-xs text-red-500">{error}</p> : null;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pagos en Línea', href: '/cupones' },
    { title: 'Cupones', href: '/cupones' },
];

const tipoLabels: Record<string, string> = {
    porcentaje: 'Porcentaje',
    precio_fijo: 'Precio Fijo',
    envio_gratis: 'Envío Gratis',
    vale_producto: 'Vale por Producto',
};

const tipoIcons: Record<string, React.ReactNode> = {
    porcentaje: <Percent className="h-3.5 w-3.5" />,
    precio_fijo: <DollarSign className="h-3.5 w-3.5" />,
    envio_gratis: <Truck className="h-3.5 w-3.5" />,
    vale_producto: <Package className="h-3.5 w-3.5" />,
};

export default function CuponesIndex({ cupones, productos, filters, flash }: Props) {
    const { hasPermission } = usePermissions();
    const { code: countryCode, currency } = useCountry();
    const canAccess = hasPermission('ventas.cupones.viewAny');
    const canCreate = hasPermission('ventas.cupones.create');
    const canEdit = hasPermission('ventas.cupones.edit');
    const canDelete = hasPermission('ventas.cupones.delete');
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Cupon | null>(null);
    const [search, setSearch] = useState(filters.search || '');
    const [showPreview, setShowPreview] = useState(false);
    const [previewMode, setPreviewMode] = useState<'html' | 'text'>('html');
    const searchTimeout = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
    const [productoSearch, setProductoSearch] = useState('');
    const [showProductoDropdown, setShowProductoDropdown] = useState(false);

    const handleSearch = useCallback((value: string) => {
        setSearch(value);
        if (searchTimeout.current) clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(() => {
            router.get('/cupones', { search: value || undefined }, {
                preserveState: true,
                replace: true,
            });
        }, 400);
    }, []);

    const form = useForm({
        codigo: '',
        tipo: 'porcentaje' as 'porcentaje' | 'precio_fijo' | 'envio_gratis' | 'vale_producto',
        valor: '',
        descripcion: '',
        plantilla_html: '',
        variables_ejemplo: null as Record<string, string> | null,
        max_usos: '0',
        usos_por_cliente: '1',
        compra_minima: '',
        fecha_inicio: '',
        fecha_fin: '',
        activa: true,
        productos: [] as Array<{ id: number; descuento_tipo: 'porcentaje' | 'precio_fijo'; descuento_valor: number }>,
    });

    const defaultVariables: Record<string, string> = {
        codigo: 'VERANO2025',
        valor: '25',
        tipo: 'Porcentaje',
        vencimiento: '31-12-2025',
        descripcion: 'Descuento especial de temporada',
        tienda: 'Mi Tienda',
        logo_url: '/logo.png',
        compra_minima: '$20.000',
    };

    const generateCodigo = () => {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const random = Array.from({ length: 4 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
        const num = Math.floor(Math.random() * 900) + 100;
        form.setData('codigo', `${random}${num}`);
    };

    const handleOpenNew = () => {
        form.reset();
        form.setData('tipo', 'porcentaje');
        form.setData('activa', true);
        form.setData('max_usos', '0');
        form.setData('usos_por_cliente', '1');
        form.setData('productos', []);
        form.setData('variables_ejemplo', defaultVariables);
        setEditando(null);
        setShowPreview(false);
        setIsOpen(true);
    };

    const handleEdit = (c: Cupon) => {
        setEditando(c);
        form.setData({
            codigo: c.codigo,
            tipo: c.tipo,
            valor: String(c.valor),
            descripcion: c.descripcion || '',
            plantilla_html: c.plantilla_html || '',
            variables_ejemplo: c.variables_ejemplo || defaultVariables,
            max_usos: String(c.max_usos),
            usos_por_cliente: String(c.usos_por_cliente),
            compra_minima: c.compra_minima ? String(c.compra_minima) : '',
            fecha_inicio: c.fecha_inicio ? c.fecha_inicio.split(' ')[0] : '',
            fecha_fin: c.fecha_fin ? c.fecha_fin.split(' ')[0] : '',
            activa: c.activa,
            productos: c.productos?.map((p) => ({
                id: p.id,
                descuento_tipo: p.pivot.descuento_tipo,
                descuento_valor: Number(p.pivot.descuento_valor),
            })) || [],
        });
        setShowPreview(false);
        setIsOpen(true);
    };

    const handleDelete = (id: number, codigo: string) => {
        if (confirm(`¿Eliminar el cupón "${codigo}"?`)) {
            router.delete(`/cupones/${id}`);
        }
    };

    const handleToggle = (cupon: Cupon) => {
        router.patch(`/cupones/${cupon.id}/toggle`);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            form.put(`/cupones/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    form.reset();
                },
            });
        } else {
            form.post('/cupones', {
                onSuccess: () => {
                    setIsOpen(false);
                    form.reset();
                },
            });
        }
    };

    const addProducto = (producto: Producto) => {
        const exists = form.data.productos.find((p) => p.id === producto.id);
        if (!exists) {
            form.setData('productos', [
                ...form.data.productos,
                {
                    id: producto.id,
                    descuento_tipo: 'porcentaje',
                    descuento_valor: 0,
                },
            ]);
        }
        setProductoSearch('');
        setShowProductoDropdown(false);
    };

    const removeProducto = (id: number) => {
        form.setData('productos', form.data.productos.filter((p) => p.id !== id));
    };

    const updateProductoConfig = (id: number, field: 'descuento_tipo' | 'descuento_valor', value: string | number) => {
        form.setData(
            'productos',
            form.data.productos.map((p) => (p.id === id ? { ...p, [field]: value } : p)),
        );
    };

    const filteredProductos = useMemo(() => {
        if (!productoSearch) return [];
        const selectedIds = form.data.productos.map((p) => p.id);
        return productos
            .filter((p) => !selectedIds.includes(p.id))
            .filter(
                (p) =>
                    p.nombre.toLowerCase().includes(productoSearch.toLowerCase()) ||
                    (p.codigo && p.codigo.toLowerCase().includes(productoSearch.toLowerCase())),
            )
            .slice(0, 10);
    }, [productoSearch, productos, form.data.productos]);

    const getProductoById = (id: number) => productos.find((p) => p.id === id);

    const previewVariables = useMemo((): Record<string, string> => {
        const tipoLabel: Record<string, string> = {
            porcentaje: 'Porcentaje',
            precio_fijo: 'Precio Fijo',
            envio_gratis: 'Envío Gratis',
            vale_producto: 'Vale por Producto',
        };
        const valorStr =
            form.data.tipo === 'porcentaje'
                ? `${form.data.valor || '0'}`
                : form.data.tipo === 'envio_gratis'
                    ? 'Gratis'
                    : form.data.tipo === 'vale_producto'
                        ? `${form.data.productos.length} productos`
                        : `$${Number(form.data.valor || 0).toLocaleString(currency.locale)}`;

        return {
            codigo: form.data.codigo || 'CODIGO',
            valor: valorStr,
            tipo: tipoLabel[form.data.tipo],
            vencimiento: form.data.fecha_fin
                ? new Date(form.data.fecha_fin).toLocaleDateString(currency.locale)
                : 'Sin vencimiento',
            descripcion: form.data.descripcion || '',
            tienda: 'Mi Tienda',
            logo_url: '/logo.png',
            compra_minima: form.data.compra_minima
                ? `$${Number(form.data.compra_minima).toLocaleString(currency.locale)}`
                : 'Sin mínimo',
        };
    }, [form.data.codigo, form.data.tipo, form.data.valor, form.data.descripcion, form.data.fecha_fin, form.data.compra_minima, form.data.productos]);

    const substituteVariables = useCallback(
        (template: string): string => {
            if (!template) return '';
            let result = template;
            for (const [key, value] of Object.entries(previewVariables)) {
                result = result.replaceAll(`{{${key}}}`, value);
            }
            return result;
        },
        [previewVariables],
    );

    const stripHtml = (html: string): string => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        return doc.body.textContent || '';
    };

    const renderedPreview = useMemo(() => {
        if (!form.data.plantilla_html) return '';
        return substituteVariables(form.data.plantilla_html);
    }, [form.data.plantilla_html, substituteVariables]);

    const formatValor = (c: Cupon) => {
        if (c.tipo === 'porcentaje') return `${c.valor}%`;
        if (c.tipo === 'envio_gratis') return 'Gratis';
        if (c.tipo === 'vale_producto') return `${c.productos?.length ?? 0} productos`;
        return `$${Number(c.valor).toLocaleString(currency.locale)}`;
    };

    if (!canAccess) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cupones de Descuento" />

            {flash?.success && (
                <div className="mx-4 mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}

            {flash?.error && (
                <div className="mx-4 mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    {flash.error}
                </div>
            )}

            {Object.keys(form.errors).length > 0 && (
                <div className="mx-4 mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p className="font-medium mb-1">Por favor corrige los siguientes errores:</p>
                    <ul className="list-disc list-inside space-y-0.5">
                        {Object.entries(form.errors).map(([key, msg]) => (
                            <li key={key}>{msg}</li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="space-y-4 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Cupones de Descuento</h1>
                        <p className="text-muted-foreground">Gestiona cupones, códigos promocionales y vales por producto</p>
                    </div>
                    {canCreate && (
                        <Button onClick={handleOpenNew}>
                            <Plus className="mr-2 h-4 w-4" /> Nuevo Cupón
                        </Button>
                    )}
                </div>

                <div className="relative w-full sm:max-w-sm">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => handleSearch(e.target.value)}
                        placeholder="Buscar por código..."
                        className="pl-9"
                    />
                </div>

                {cupones.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <Gift className="mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">Sin cupones</h3>
                            <p className="mb-4 text-muted-foreground">
                                {search ? 'No se encontraron cupones con ese código.' : 'Crea tu primer cupón de descuento.'}
                            </p>
                            {canCreate && !search && (
                                <Button onClick={handleOpenNew}>
                                    <Plus className="mr-2 h-4 w-4" /> Nuevo Cupón
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Cupones</CardTitle>
                                    <CardDescription>{cupones.total} cupones encontrados</CardDescription>
                                </div>
                                <div className="flex gap-1 rounded-lg border p-0.5">
                                    <button
                                        type="button"
                                        onClick={() => setViewMode('table')}
                                        className={`rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors ${viewMode === 'table' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                        title="Vista tabla"
                                    >
                                        <List className="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setViewMode('cards')}
                                        className={`rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors ${viewMode === 'cards' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                        title="Vista tarjetas"
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {viewMode === 'table' ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[600px]">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Código</th>
                                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Tipo</th>
                                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Valor</th>
                                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Usos</th>
                                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Vigencia</th>
                                                <th className="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Estado</th>
                                                <th className="px-4 py-3 text-right text-sm font-medium text-muted-foreground">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {cupones.data.map((c) => (
                                                <tr key={c.id} className="border-b last:border-0 hover:bg-muted/30 transition-colors">
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-mono font-semibold">{c.codigo}</span>
                                                            <button type="button" onClick={() => navigator.clipboard.writeText(c.codigo)} className="text-muted-foreground hover:text-foreground" title="Copiar código">
                                                                <Copy className="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>
                                                        {c.descripcion && <p className="mt-0.5 text-xs text-muted-foreground line-clamp-1">{c.descripcion}</p>}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-1.5 text-sm">
                                                            {tipoIcons[c.tipo]}
                                                            <span>{tipoLabels[c.tipo]}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm font-medium">{formatValor(c)}</td>
                                                    <td className="px-4 py-3 text-sm">
                                                        {c.max_usos > 0 ? (
                                                            <span>
                                                                <span className="font-medium">{c.usos_actuales}</span> / {c.max_usos}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground">{c.usos_actuales} usos</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                                        {c.fecha_fin ? new Date(c.fecha_fin).toLocaleDateString(currency.locale) : 'Sin fecha'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant={c.activa ? 'default' : 'secondary'}>{c.activa ? 'Activo' : 'Inactivo'}</Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <Switch checked={c.activa} onCheckedChange={() => handleToggle(c)} />
                                                            {canEdit && (
                                                                <button onClick={() => handleEdit(c)} className="rounded-md p-1.5 transition-colors hover:bg-muted" title="Editar">
                                                                    <Pencil className="h-4 w-4 text-muted-foreground" />
                                                                </button>
                                                            )}
                                                            {canDelete && (
                                                                <button onClick={() => handleDelete(c.id, c.codigo)} className="rounded-md p-1.5 transition-colors hover:bg-muted" title="Eliminar">
                                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {cupones.data.map((c) => (
                                        <Card key={c.id} className="overflow-hidden border-muted/80 transition-all hover:shadow-md">
                                            <CardHeader className="pb-2">
                                                <div className="flex items-start justify-between">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                        {tipoIcons[c.tipo] || <Gift className="h-5 w-5 text-primary" />}
                                                    </div>
                                                    <Badge variant={c.activa ? 'default' : 'secondary'}>{c.activa ? 'Activo' : 'Inactivo'}</Badge>
                                                </div>
                                                <div className="mt-2">
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="font-mono font-bold text-lg">{c.codigo}</span>
                                                        <button type="button" onClick={() => navigator.clipboard.writeText(c.codigo)} className="text-muted-foreground hover:text-foreground" title="Copiar código">
                                                            <Copy className="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                    {c.descripcion && <p className="mt-0.5 text-xs text-muted-foreground line-clamp-2">{c.descripcion}</p>}
                                                </div>
                                            </CardHeader>
                                            <CardContent className="space-y-2 text-sm pb-3">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">{tipoLabels[c.tipo]}</span>
                                                    <span className="font-semibold">{formatValor(c)}</span>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">Usos</span>
                                                    <span>
                                                        {c.max_usos > 0 ? (
                                                            <>
                                                                {c.usos_actuales} / {c.max_usos}
                                                            </>
                                                        ) : (
                                                            <>{c.usos_actuales} usos</>
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">Vence</span>
                                                    <span>{c.fecha_fin ? new Date(c.fecha_fin).toLocaleDateString(currency.locale) : 'Sin fecha'}</span>
                                                </div>
                                                {c.tipo === 'vale_producto' && c.productos && c.productos.length > 0 && (
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-muted-foreground">Productos</span>
                                                        <span className="text-right">{c.productos.length} productos</span>
                                                    </div>
                                                )}
                                                <div className="flex items-center justify-between pt-2 border-t">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-xs text-muted-foreground">Activo</span>
                                                        <Switch checked={c.activa} onCheckedChange={() => handleToggle(c)} />
                                                    </div>
                                                    <div className="flex gap-1">
                                                        {canEdit && (
                                                            <button onClick={() => handleEdit(c)} className="rounded-md p-1.5 transition-colors hover:bg-muted" title="Editar">
                                                                <Pencil className="h-4 w-4 text-muted-foreground" />
                                                            </button>
                                                        )}
                                                        {canDelete && (
                                                            <button onClick={() => handleDelete(c.id, c.codigo)} className="rounded-md p-1.5 transition-colors hover:bg-muted" title="Eliminar">
                                                                <Trash2 className="h-4 w-4 text-red-500" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                            {cupones.last_page > 1 && (
                                <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="text-center text-sm text-muted-foreground sm:text-left">
                                        Mostrando {cupones.from}-{cupones.to} de {cupones.total}
                                    </p>
                                    <div className="flex flex-wrap justify-center gap-1">
                                        {cupones.links.map((link, i) => (
                                            <Button
                                                key={i}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                disabled={!link.url}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true, replace: true })}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="w-full max-w-3xl max-h-[90vh] overflow-y-auto sm:p-6 p-4">
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar Cupón' : 'Nuevo Cupón'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 sm:gap-6">
                            <div className="space-y-2">
                                <Label>Código *</Label>
                                <div className="flex gap-2">
                                    <Input
                                        value={form.data.codigo}
                                        onChange={(e) => form.setData('codigo', e.target.value.toUpperCase())}
                                        placeholder="Ej: VERANO2025"
                                        className="font-mono uppercase"
                                        required
                                    />
                                    <Button type="button" variant="outline" onClick={generateCodigo}>
                                        Generar
                                    </Button>
                                </div>
                                <FieldError error={form.errors.codigo} />
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div className="space-y-2">
                                    <Label>Tipo de descuento *</Label>
                                    <Select
                                        value={form.data.tipo}
                                        onValueChange={(v: 'porcentaje' | 'precio_fijo' | 'envio_gratis' | 'vale_producto') => {
                                            form.setData('tipo', v);
                                        }}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="porcentaje">Porcentaje (%)</SelectItem>
                                            <SelectItem value="precio_fijo">Precio Fijo ($)</SelectItem>
                                            <SelectItem value="envio_gratis">Envío Gratis</SelectItem>
                                            <SelectItem value="vale_producto">Vale por Producto</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FieldError error={form.errors.tipo} />
                                </div>
                                <div className="space-y-2">
                                    <Label>
                                        {form.data.tipo === 'porcentaje'
                                            ? 'Porcentaje (%) *'
                                            : form.data.tipo === 'precio_fijo'
                                                ? 'Monto ($) *'
                                                : form.data.tipo === 'vale_producto'
                                                    ? 'Porcentaje global (%) *'
                                                    : 'Valor referencial'}
                                    </Label>
                                    {form.data.tipo !== 'envio_gratis' && (
                                        <Input
                                            type="number"
                                            step="any"
                                            min="0"
                                            value={form.data.valor}
                                            onChange={(e) => form.setData('valor', e.target.value)}
                                            placeholder={form.data.tipo === 'vale_producto' ? 'Porcentaje aplicado a productos no configurados individualmente' : ''}
                                        />
                                    )}
                                    <FieldError error={form.errors.valor} />
                                </div>
                            </div>

                            <div className="space-y-3 rounded-lg border p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <Label className="text-base font-semibold">Productos aplicables (Opcional)</Label>
                                            <p className="text-sm text-muted-foreground">
                                                Selecciona productos para restringir este cupón o configura un descuento individual para cada uno.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={productoSearch}
                                            onChange={(e) => {
                                                setProductoSearch(e.target.value);
                                                setShowProductoDropdown(true);
                                            }}
                                            onFocus={() => setShowProductoDropdown(true)}
                                            placeholder="Buscar producto por nombre o código..."
                                            className="pl-9"
                                        />
                                        {showProductoDropdown && filteredProductos.length > 0 && (
                                            <div className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover shadow-md">
                                                {filteredProductos.map((p) => (
                                                    <button
                                                        key={p.id}
                                                        type="button"
                                                        className="flex w-full items-center gap-2 px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground"
                                                        onClick={() => addProducto(p)}
                                                    >
                                                        <Plus className="h-4 w-4 text-muted-foreground" />
                                                        <span className="flex-1 text-left">{p.nombre}</span>
                                                        {p.codigo && <span className="text-xs text-muted-foreground">{p.codigo}</span>}
                                                        {p.precio_venta && (
                                                            <span className="text-xs font-medium">${p.precio_venta.toLocaleString(currency.locale)}</span>
                                                        )}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                        {showProductoDropdown && productoSearch && filteredProductos.length === 0 && (
                                            <div className="absolute z-50 mt-1 w-full rounded-md border bg-popover p-3 text-sm text-muted-foreground shadow-md">
                                                No se encontraron productos
                                            </div>
                                        )}
                                    </div>

                                    {form.data.productos.length > 0 && (
                                        <div className="space-y-2">
                                            {form.data.productos.map((pConfig) => {
                                                const prod = getProductoById(pConfig.id);
                                                if (!prod) return null;
                                                return (
                                                    <div key={pConfig.id} className="flex flex-col sm:flex-row sm:items-center gap-3 rounded-md border p-3">
                                                        <div className="flex-1 min-w-0 w-full">
                                                            <p className="text-sm font-medium truncate">{prod.nombre}</p>
                                                            {prod.precio_venta && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    Precio: ${prod.precio_venta.toLocaleString(currency.locale)}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="flex w-full sm:w-auto items-center gap-2">
                                                            <Select
                                                                value={pConfig.descuento_tipo}
                                                                onValueChange={(v: 'porcentaje' | 'precio_fijo') =>
                                                                    updateProductoConfig(pConfig.id, 'descuento_tipo', v)
                                                                }
                                                            >
                                                                <SelectTrigger className="w-[110px] h-9">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="porcentaje">%</SelectItem>
                                                                    <SelectItem value="precio_fijo">$ Fijo</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                            <Input
                                                                type="number"
                                                                step="any"
                                                                min="0"
                                                                value={pConfig.descuento_valor || ''}
                                                                onChange={(e) =>
                                                                    updateProductoConfig(pConfig.id, 'descuento_valor', parseFloat(e.target.value) || 0)
                                                                }
                                                                placeholder={pConfig.descuento_tipo === 'porcentaje' ? '20' : '15000'}
                                                                className="w-[90px] h-9"
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => removeProducto(pConfig.id)}
                                                                className="h-9 w-9 text-red-500 hover:text-red-700 ml-auto sm:ml-0 shrink-0"
                                                            >
                                                                <X className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}

                                    {form.data.productos.length === 0 && (
                                        <p className="text-center text-sm text-muted-foreground py-4">
                                            No hay productos seleccionados. Busca y agrega productos arriba.
                                        </p>
                                    )}
                                    <FieldError error={form.errors.productos} />
                                </div>

                            <div className="space-y-2">
                                <Label>Descripción</Label>
                                <Textarea
                                    value={form.data.descripcion}
                                    onChange={(e) => form.setData('descripcion', e.target.value)}
                                    placeholder="Describe el cupón y sus condiciones..."
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div className="space-y-2">
                                    <Label>Compra mínima ($)</Label>
                                    <Input
                                        type="number"
                                        step="any"
                                        min="0"
                                        value={form.data.compra_minima}
                                        onChange={(e) => form.setData('compra_minima', e.target.value)}
                                        placeholder="Ej: 20000"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Usos por cliente</Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={form.data.usos_por_cliente}
                                        onChange={(e) => form.setData('usos_por_cliente', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div className="space-y-2">
                                    <Label>Usos máximos (0 = ilimitado)</Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={form.data.max_usos}
                                        onChange={(e) => form.setData('max_usos', e.target.value)}
                                    />
                                </div>
                                <div className="flex items-end pb-2">
                                    <div className="flex items-center gap-2">
                                        <Switch checked={form.data.activa} onCheckedChange={(v) => form.setData('activa', v)} />
                                        <Label className="cursor-pointer">Activo</Label>
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div className="space-y-2">
                                    <Label>Fecha inicio</Label>
                                    <Input type="date" value={form.data.fecha_inicio} onChange={(e) => form.setData('fecha_inicio', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Fecha fin</Label>
                                    <Input type="date" value={form.data.fecha_fin} onChange={(e) => form.setData('fecha_fin', e.target.value)} />
                                    <FieldError error={form.errors.fecha_fin} />
                                </div>
                            </div>

                            <div className="space-y-2 border-t pt-4">
                                <Label className="text-base font-semibold">Diseño de plantilla</Label>
                                <p className="text-sm text-muted-foreground">Personaliza el diseño visual del cupón para emails y vista en pantalla.</p>
                                <TipTapEditorWithSource
                                    value={form.data.plantilla_html}
                                    onChange={(html) => form.setData('plantilla_html', html)}
                                    placeholder="<div style='padding: 20px; text-align: center;'><h2>Cupón de descuento</h2><p>Código: {{codigo}}</p></div>"
                                />
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={() => setShowPreview(!showPreview)}>
                                        {showPreview ? 'Ocultar' : 'Vista previa'}
                                    </Button>
                                    {showPreview && (
                                        <div className="flex items-center gap-1 rounded-lg border p-0.5">
                                            <button
                                                type="button"
                                                onClick={() => setPreviewMode('html')}
                                                className={`rounded-md px-3 py-1 text-xs font-medium transition-colors ${
                                                    previewMode === 'html' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                                                }`}
                                            >
                                                HTML
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setPreviewMode('text')}
                                                className={`rounded-md px-3 py-1 text-xs font-medium transition-colors ${
                                                    previewMode === 'text' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                                                }`}
                                            >
                                                Texto
                                            </button>
                                        </div>
                                    )}
                                </div>
                                {showPreview && (
                                    <div className="rounded-xl border bg-white p-4">
                                        <div className="mb-2 text-xs text-muted-foreground">
                                            {previewMode === 'html' ? 'Vista previa HTML:' : 'Vista previa texto plano:'}
                                        </div>
                                        {previewMode === 'html' ? (
                                            renderedPreview ? (
                                                <div className="[&_img]:max-w-full [&_table]:w-full [&_table]:border-collapse">
                                                    <div dangerouslySetInnerHTML={{ __html: renderedPreview }} />
                                                </div>
                                            ) : (
                                                <p className="text-sm text-muted-foreground italic">Sin contenido de plantilla</p>
                                            )
                                        ) : (
                                            <pre className="whitespace-pre-wrap break-words text-sm">
                                                {renderedPreview ? stripHtml(renderedPreview) : 'Sin contenido de plantilla'}
                                            </pre>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                        <DialogFooter className="flex-col gap-2 sm:flex-row mt-4">
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)} className="w-full sm:w-auto">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing} className="w-full sm:w-auto">
                                {form.processing ? 'Guardando...' : editando ? 'Actualizar' : 'Crear'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

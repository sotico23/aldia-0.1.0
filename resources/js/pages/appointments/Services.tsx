import { Head, useForm, router } from '@inertiajs/react';
import { Edit, Trash2, Plus, Eye, X, Clock, DollarSign, ImageIcon, Calendar, ChevronDown, TrendingUp, BarChart3, PieChart as PieChartIcon } from 'lucide-react';
import { useState, useRef } from 'react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover, PopoverContent, PopoverTrigger,
} from '@/components/ui/popover';
import { Switch } from '@/components/ui/switch';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

const COLORS = ['#6366f1', '#22c55e', '#f59e0b', '#3b82f6', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316'];

export default function Services({ services, categorias, employees, stats, popularServices, serviceCategories }: {
    services: any[];
    categorias: any[];
    employees: { id: number, name: string, email: string }[];
    stats: { total: number; activos: number; totalReservas: number; totalIngresos: number; promedioReservas: number };
    popularServices: { name: string; reservas: number; ingresos: number }[];
    serviceCategories: { name: string; value: number }[];
}) {
    const { data, setData, post, delete: destroy, reset, processing, errors } = useForm({
        nombre: '',
        descripcion: '',
        duracion: 30,
        precio_venta: 0,
        categoria_id: categorias.length > 0 ? categorias[0].id : '',
        activo: true,
        requires_appointment: false,
        provider_ids: [] as number[],
        imagen: null as File | null,
        imagen2: null as File | null,
        imagen3: null as File | null,
        imagen4: null as File | null,
        imagen5: null as File | null,
    });

    const [isEditing, setIsEditing] = useState<number | null>(null);
    const [previews, setPreviews] = useState<Record<string, string>>({});
    const [viewingService, setViewingService] = useState<any | null>(null);
    const [openDialog, setOpenDialog] = useState(false);
    const fileInputs = useRef<Record<string, HTMLInputElement | null>>({});

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const activeCount = services.filter((s: any) => s.activo).length;

    const openCreate = () => {
        setIsEditing(null);
        reset();
        setPreviews({});
        setOpenDialog(true);
    };

    const openEdit = (service: any) => {
        setIsEditing(service.id);
        setData({
            nombre: service.nombre,
            descripcion: service.descripcion || '',
            duracion: service.duracion || 30,
            precio_venta: service.precio_venta,
            categoria_id: service.categoria_id || (categorias.length > 0 ? categorias[0].id : ''),
            activo: service.activo,
            requires_appointment: service.requires_appointment ?? false,
            provider_ids: (service.providers || []).map((p: any) => p.id),
            imagen: null,
            imagen2: null,
            imagen3: null,
            imagen4: null,
            imagen5: null,
        });
        const initialPreviews: Record<string, string> = {};
        ['imagen', 'imagen2', 'imagen3', 'imagen4', 'imagen5'].forEach(key => {
            if (service[key]) {
                initialPreviews[key] = `/storage/${service[key]}`;
            }
        });
        setPreviews(initialPreviews);
        setOpenDialog(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            router.post(`/services/${isEditing}`, {
                _method: 'PUT',
                forceFormData: true,
                ...(data as any),
            }, {
                onSuccess: () => {
                    setIsEditing(null);
                    reset();
                    setPreviews({});
                    setOpenDialog(false);
                },
            });
        } else {
            post('/services', {
                onSuccess: () => {
                    reset();
                    setPreviews({});
                    setOpenDialog(false);
                },
            });
        }
    };

    const handleFileChange = (key: string, file: File | null) => {
        setData(key as any, file);
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => {
                setPreviews(prev => ({ ...prev, [key]: reader.result as string }));
            };
            reader.readAsDataURL(file);
        } else {
            setPreviews(prev => {
                const updated = { ...prev };
                delete updated[key];
                return updated;
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Estás seguro de eliminar este servicio?')) {
            destroy(`/services/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Servicios', href: '/services' }]}>
            <Head title="Servicios" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Servicios</h1>
                        <p className="text-sm text-muted-foreground">Gestiona los servicios ofrecidos en tu negocio</p>
                    </div>
                    <Button onClick={openCreate} className="shrink-0">
                        <Plus className="mr-1.5 h-4 w-4" />
                        Nuevo Servicio
                    </Button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <BarChart3 className="h-3.5 w-3.5 text-indigo-500" />
                                Total Servicios
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <p className="text-2xl font-black text-slate-900">{stats.total}</p>
                            <p className="text-xs text-emerald-600 font-semibold">{stats.activos} activos</p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Calendar className="h-3.5 w-3.5 text-blue-500" />
                                Total Reservas
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <p className="text-2xl font-black text-slate-900">{stats.totalReservas}</p>
                            <p className="text-xs text-slate-400 font-semibold">Prom. {stats.promedioReservas} x servicio</p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <DollarSign className="h-3.5 w-3.5 text-emerald-500" />
                                Ingresos Generados
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <p className="text-2xl font-black text-emerald-600">${stats.totalIngresos.toLocaleString()}</p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <TrendingUp className="h-3.5 w-3.5 text-violet-500" />
                                Proyección Mensual
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <p className="text-2xl font-black text-violet-600">
                                ${stats.total > 0 ? (stats.totalIngresos / stats.total * stats.activos).toLocaleString() : '0'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="px-5 py-4">
                            <CardTitle className="flex items-center gap-2 text-sm font-bold text-slate-700">
                                <BarChart3 className="h-4 w-4 text-violet-500" />
                                Servicios Más Reservados
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-2 pb-4 pt-0">
                            <ResponsiveContainer width="100%" height={250}>
                                <BarChart data={popularServices} layout="vertical">
                                    <XAxis type="number" tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
                                    <YAxis type="category" dataKey="name" tick={{ fontSize: 10, fill: '#64748b' }} axisLine={false} tickLine={false} width={100} />
                                    <Tooltip formatter={(value, name) => [value, name === 'reservas' ? 'Reservas' : 'Ingresos']} contentStyle={{ borderRadius: 12, border: '1px solid #e2e8f0', fontSize: 12 }} />
                                    <Bar dataKey="reservas" radius={[0, 4, 4, 0]} fill="#8b5cf6" maxBarSize={16} name="reservas" />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    <Card className="rounded-2xl border-slate-200/70">
                        <CardHeader className="px-5 py-4">
                            <CardTitle className="flex items-center gap-2 text-sm font-bold text-slate-700">
                                <PieChartIcon className="h-4 w-4 text-indigo-500" />
                                Distribución por Categoría
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-2 pb-4 pt-0">
                            <ResponsiveContainer width="100%" height={220}>
                                <PieChart>
                                    <Pie data={serviceCategories} cx="50%" cy="50%" innerRadius={55} outerRadius={85} dataKey="value" stroke="none">
                                        {serviceCategories.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                                    </Pie>
                                    <Tooltip formatter={(value, name) => [value, name]} contentStyle={{ borderRadius: 12, border: '1px solid #e2e8f0', fontSize: 12 }} />
                                </PieChart>
                            </ResponsiveContainer>
                            <div className="mt-2 flex flex-wrap gap-2 px-3 justify-center">
                                {serviceCategories.map((c, i) => (
                                    <div key={c.name} className="flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                                        <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: COLORS[i % COLORS.length] }} />
                                        {c.name}: {c.value}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Extra stat row for popular services revenue */}
                {popularServices.length > 0 && (
                    <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                        {popularServices.slice(0, 5).map((s) => (
                            <div key={s.name} className="bg-white rounded-xl border border-slate-200 p-3">
                                <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">{s.name}</p>
                                <p className="text-lg font-black text-slate-900">{s.reservas}</p>
                                <p className="text-[10px] text-emerald-600 font-semibold">${s.ingresos.toLocaleString()}</p>
                            </div>
                        ))}
                    </div>
                )}

                {/* Table */}
                <Card>
                    <CardContent className="p-0">
                        {services.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <ImageIcon className="h-12 w-12 text-muted-foreground/40 mb-4" />
                                <p className="text-muted-foreground font-medium">No hay servicios registrados</p>
                                <p className="text-sm text-muted-foreground/60 mt-1">Crea tu primer servicio para empezar</p>
                                <Button onClick={openCreate} variant="outline" className="mt-4">
                                    <Plus className="mr-1.5 h-4 w-4" />
                                    Nuevo Servicio
                                </Button>
                            </div>
                        ) : (
                            <>
                                {/* Desktop table */}
                                <div className="hidden md:block">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Servicio</TableHead>
                                                <TableHead>Categoría</TableHead>
                                                <TableHead>Duración</TableHead>
                                                <TableHead>Precio</TableHead>
                                                <TableHead>Cita</TableHead>
                                                <TableHead>Estado</TableHead>
                                                <TableHead className="text-right">Acciones</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {services.map((service: any) => (
                                                <TableRow key={service.id}>
                                                    <TableCell>
                                                        <div className="flex items-center gap-3">
                                                            {service.imagen ? (
                                                                <img
                                                                    src={`/storage/${service.imagen}`}
                                                                    alt=""
                                                                    className="h-10 w-10 rounded-lg object-cover border shrink-0"
                                                                />
                                                            ) : (
                                                                <div className="h-10 w-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                                                    <ImageIcon className="h-4 w-4 text-muted-foreground" />
                                                                </div>
                                                            )}
                                                            <div className="min-w-0">
                                                                <p className="font-medium truncate">{service.nombre}</p>
                                                                {service.descripcion && (
                                                                    <p className="text-xs text-muted-foreground truncate max-w-48">{service.descripcion}</p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="secondary">{service.categoria?.nombre || '-'}</Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-1.5 text-sm">
                                                            <Clock className="h-3.5 w-3.5 text-muted-foreground" />
                                                            {service.duracion} min
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-1.5 text-sm font-medium">
                                                            <DollarSign className="h-3.5 w-3.5 text-muted-foreground" />
                                                            ${Number(service.precio_venta).toLocaleString()}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        {service.requires_appointment ? (
                                                            <Badge variant="outline" className="border-amber-300 text-amber-700 bg-amber-50 dark:border-amber-700 dark:text-amber-400 dark:bg-amber-950">
                                                                <Calendar className="h-3 w-3 mr-1" /> Cita
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant={service.activo ? 'default' : 'secondary'}>
                                                            {service.activo ? 'Activo' : 'Inactivo'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="inline-flex gap-1">
                                                            <Button variant="ghost" size="icon" onClick={() => setViewingService(service)} title="Ver">
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" onClick={() => openEdit(service)} title="Editar">
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" className="text-destructive hover:text-destructive" onClick={() => handleDelete(service.id)} title="Eliminar">
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>

                                {/* Mobile cards */}
                                <div className="md:hidden divide-y">
                                    {services.map((service: any) => (
                                        <div key={service.id} className="p-4 space-y-3">
                                            <div className="flex items-start gap-3">
                                                {service.imagen ? (
                                                    <img src={`/storage/${service.imagen}`} alt="" className="h-12 w-12 rounded-lg object-cover border shrink-0" />
                                                ) : (
                                                    <div className="h-12 w-12 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                                        <ImageIcon className="h-5 w-5 text-muted-foreground" />
                                                    </div>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <p className="font-medium">{service.nombre}</p>
                                                    <p className="text-xs text-muted-foreground">{service.categoria?.nombre || '-'}</p>
                                                </div>
                                                <div className="flex flex-col gap-1 shrink-0">
                                                    <Badge variant={service.activo ? 'default' : 'secondary'}>
                                                        {service.activo ? 'Activo' : 'Inactivo'}
                                                    </Badge>
                                                    {service.requires_appointment && (
                                                        <Badge variant="outline" className="border-amber-300 text-amber-700 bg-amber-50 dark:border-amber-700 dark:text-amber-400 dark:bg-amber-950 text-[10px]">
                                                            <Calendar className="h-2.5 w-2.5 mr-0.5" /> Cita
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <Clock className="h-3.5 w-3.5" /> {service.duracion} min
                                                </span>
                                                <span className="flex items-center gap-1 font-medium text-foreground">
                                                    <DollarSign className="h-3.5 w-3.5" /> ${Number(service.precio_venta).toLocaleString()}
                                                </span>
                                            </div>
                                            {service.descripcion && (
                                                <p className="text-sm text-muted-foreground line-clamp-2">{service.descripcion}</p>
                                            )}
                                            <div className="flex gap-2 pt-1">
                                                <Button variant="outline" size="sm" className="flex-1" onClick={() => setViewingService(service)}>
                                                    <Eye className="h-3.5 w-3.5 mr-1" /> Ver
                                                </Button>
                                                <Button variant="outline" size="sm" className="flex-1" onClick={() => openEdit(service)}>
                                                    <Edit className="h-3.5 w-3.5 mr-1" /> Editar
                                                </Button>
                                                <Button variant="outline" size="sm" className="flex-1 text-destructive" onClick={() => handleDelete(service.id)}>
                                                    <Trash2 className="h-3.5 w-3.5 mr-1" /> Eliminar
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create / Edit Dialog */}
            <Dialog open={openDialog} onOpenChange={setOpenDialog}>
                <DialogContent className="sm:max-w-xl p-0 gap-0">
                    <DialogHeader className="px-6 pt-6 pb-4 border-b">
                        <DialogTitle>{isEditing ? 'Editar Servicio' : 'Nuevo Servicio'}</DialogTitle>
                        <DialogDescription>
                            {isEditing ? 'Modifica los datos del servicio seleccionado.' : 'Completa los datos para registrar un nuevo servicio.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="overflow-y-auto px-6 py-4 space-y-5 max-h-[60vh]">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="nombre">Nombre</Label>
                                <Input id="nombre" value={data.nombre} onChange={e => setData('nombre', e.target.value)} required placeholder="Ej: Corte de cabello" />
                                {errors.nombre && <p className="text-xs text-destructive">{errors.nombre}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="categoria">Categoría</Label>
                                <select
                                    id="categoria"
                                    value={String(data.categoria_id)}
                                    onChange={e => setData('categoria_id', e.target.value)}
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    {categorias.map(cat => (
                                        <option key={cat.id} value={String(cat.id)}>{cat.nombre}</option>
                                    ))}
                                </select>
                                {errors.categoria_id && <p className="text-xs text-destructive">{errors.categoria_id}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="duracion">Duración (min)</Label>
                                <Input id="duracion" type="number" min="1" value={data.duracion} onChange={e => setData('duracion', parseInt(e.target.value) || 30)} required />
                                {errors.duracion && <p className="text-xs text-destructive">{errors.duracion}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="precio">Precio ($)</Label>
                                <Input id="precio" type="number" min="0" step="0.01" value={data.precio_venta} onChange={e => setData('precio_venta', parseFloat(e.target.value) || 0)} required />
                                {errors.precio_venta && <p className="text-xs text-destructive">{errors.precio_venta}</p>}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="descripcion">Descripción</Label>
                            <Textarea id="descripcion" value={data.descripcion} onChange={e => setData('descripcion', e.target.value)} placeholder="Descripción opcional del servicio..." rows={3} />
                        </div>

                        <div className="space-y-2">
                            <Label>Imágenes (máximo 5)</Label>
                            <div className="grid grid-cols-3 sm:grid-cols-5 gap-2">
                                {['imagen', 'imagen2', 'imagen3', 'imagen4', 'imagen5'].map((key, index) => (
                                    <div key={key}
                                        className="relative aspect-square border-2 border-dashed rounded-lg flex items-center justify-center overflow-hidden hover:bg-muted/50 transition-colors cursor-pointer"
                                        onClick={() => fileInputs.current[key]?.click()}
                                    >
                                        {previews[key] ? (
                                            <>
                                                <img src={previews[key]} className="w-full h-full object-cover" alt="" />
                                                <button
                                                    type="button"
                                                    onClick={e => { e.stopPropagation(); handleFileChange(key, null); }}
                                                    className="absolute top-0.5 right-0.5 bg-background/80 backdrop-blur-sm text-muted-foreground hover:text-destructive p-0.5 rounded"
                                                >
                                                    <X className="h-3 w-3" />
                                                </button>
                                            </>
                                        ) : (
                                            <div className="flex flex-col items-center gap-1 text-muted-foreground">
                                                <ImageIcon className="h-4 w-4" />
                                                <span className="text-[10px]">{index === 0 ? 'Principal' : `${index + 1}`}</span>
                                            </div>
                                        )}
                                        <input
                                            ref={el => { fileInputs.current[key] = el; }}
                                            type="file"
                                            className="hidden"
                                            accept="image/*"
                                            onChange={e => handleFileChange(key, e.target.files?.[0] || null)}
                                        />
                                    </div>
                                ))}
                            </div>
                            {errors.imagen && <p className="text-xs text-destructive">La imagen principal es obligatoria.</p>}
                        </div>

                        <div className="flex items-center gap-3">
                            <Switch
                                id="activo"
                                checked={data.activo}
                                onCheckedChange={v => setData('activo', v)}
                            />
                            <Label htmlFor="activo" className="cursor-pointer">Servicio activo</Label>
                        </div>

                        <div className="flex items-center gap-3">
                            <Switch
                                id="requires_appointment"
                                checked={data.requires_appointment}
                                onCheckedChange={v => setData('requires_appointment', v)}
                            />
                            <Label htmlFor="requires_appointment" className="cursor-pointer leading-tight">
                                <span>Requiere cita previa</span>
                                <p className="text-xs text-muted-foreground font-normal mt-0.5">El cliente deberá elegir fecha y hora al comprar</p>
                            </Label>
                        </div>

                        <div className="space-y-2">
                            <Label>Empleados / Proveedores</Label>
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button variant="outline" className="w-full justify-between font-normal">
                                        {data.provider_ids.length === 0
                                            ? 'Seleccionar empleados...'
                                            : `${data.provider_ids.length} empleado${data.provider_ids.length !== 1 ? 's' : ''} seleccionado${data.provider_ids.length !== 1 ? 's' : ''}`}
                                        <ChevronDown className="ml-2 h-4 w-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-full min-w-[260px] p-2" align="start">
                                    <div className="space-y-1 max-h-48 overflow-y-auto">
                                        {employees.map(emp => {
                                            const checked = data.provider_ids.includes(emp.id);
                                            return (
                                                <label
                                                    key={emp.id}
                                                    className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-muted cursor-pointer text-sm"
                                                >
                                                    <Checkbox
                                                        checked={checked}
                                                        onCheckedChange={() => {
                                                            setData('provider_ids',
                                                                checked
                                                                    ? data.provider_ids.filter(id => id !== emp.id)
                                                                    : [...data.provider_ids, emp.id]
                                                            );
                                                        }}
                                                    />
                                                    <div className="min-w-0">
                                                        <p className="font-medium truncate">{emp.name}</p>
                                                        <p className="text-xs text-muted-foreground truncate">{emp.email}</p>
                                                    </div>
                                                </label>
                                            );
                                        })}
                                        {employees.length === 0 && (
                                            <p className="text-sm text-muted-foreground px-2 py-4 text-center">
                                                No hay empleados disponibles
                                            </p>
                                        )}
                                    </div>
                                </PopoverContent>
                            </Popover>
                            {errors.provider_ids && <p className="text-xs text-destructive">{errors.provider_ids}</p>}
                        </div>

                        <DialogFooter className="px-0 pb-0 sticky bottom-0 bg-background pt-4 border-t">
                            <Button type="button" variant="outline" onClick={() => setOpenDialog(false)}>Cancelar</Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Guardando...' : isEditing ? 'Actualizar' : 'Guardar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* View Dialog */}
            <Dialog open={!!viewingService} onOpenChange={o => { if (!o) setViewingService(null); }}>
                <DialogContent className="sm:max-w-md p-0 gap-0">
                    <DialogHeader className="px-6 pt-6 pb-4 border-b">
                        <DialogTitle>{viewingService?.nombre}</DialogTitle>
                    </DialogHeader>
                    {viewingService && (
                        <div className="overflow-y-auto px-6 py-4 space-y-5 max-h-[60vh]">
                            {['imagen', 'imagen2', 'imagen3', 'imagen4', 'imagen5'].some(k => viewingService[k]) && (
                                <div className="grid grid-cols-5 gap-2">
                                    {['imagen', 'imagen2', 'imagen3', 'imagen4', 'imagen5'].map(key =>
                                        viewingService[key] ? (
                                            <img key={key} src={`/storage/${viewingService[key]}`} className="w-full aspect-square object-cover rounded-lg border" alt="" />
                                        ) : null
                                    )}
                                </div>
                            )}
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                <div>
                                    <p className="text-xs text-muted-foreground font-medium uppercase tracking-wider">Categoría</p>
                                    <p className="font-medium">{viewingService.categoria?.nombre || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground font-medium uppercase tracking-wider">Duración</p>
                                    <p className="font-medium flex items-center gap-1.5"><Clock className="h-3.5 w-3.5 text-muted-foreground" />{viewingService.duracion} min</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground font-medium uppercase tracking-wider">Precio</p>
                                    <p className="font-medium flex items-center gap-1.5"><DollarSign className="h-3.5 w-3.5 text-muted-foreground" />${Number(viewingService.precio_venta).toLocaleString()}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground font-medium uppercase tracking-wider">Estado</p>
                                    <Badge variant={viewingService.activo ? 'default' : 'secondary'}>{viewingService.activo ? 'Activo' : 'Inactivo'}</Badge>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground font-medium uppercase tracking-wider">Cita previa</p>
                                    {viewingService.requires_appointment ? (
                                        <Badge variant="outline" className="border-amber-300 text-amber-700 bg-amber-50 dark:border-amber-700 dark:text-amber-400 dark:bg-amber-950">
                                            <Calendar className="h-3 w-3 mr-1" /> Requiere cita
                                        </Badge>
                                    ) : (
                                        <span className="text-sm text-muted-foreground">No requiere</span>
                                    )}
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground font-medium uppercase tracking-wider">Empleados asignados</p>
                                    {viewingService.providers && viewingService.providers.length > 0 ? (
                                        <div className="flex flex-wrap gap-1 mt-1">
                                            {viewingService.providers.map((p: any) => (
                                                <Badge key={p.id} variant="outline" className="text-xs">
                                                    {p.name}
                                                </Badge>
                                            ))}
                                        </div>
                                    ) : (
                                        <span className="text-sm text-muted-foreground">Ninguno</span>
                                    )}
                                </div>
                            </div>
                            {viewingService.descripcion && (
                                <div>
                                    <p className="text-xs text-muted-foreground font-medium uppercase tracking-wider mb-1">Descripción</p>
                                    <p className="text-sm">{viewingService.descripcion}</p>
                                </div>
                            )}
                        </div>
                    )}
                    <DialogFooter className="px-6 py-4 border-t">
                        <Button variant="outline" onClick={() => setViewingService(null)}>Cerrar</Button>
                        <Button onClick={() => { const s = viewingService; setViewingService(null); openEdit(s); }}>
                            Editar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

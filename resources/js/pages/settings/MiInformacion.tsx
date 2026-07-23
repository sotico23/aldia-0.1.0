import { Head, router, usePage } from '@inertiajs/react';
import {
    User,
    Mail,
    Phone,
    MapPin,
    Building2,
    Shield,
    Clock,
    Edit2,
    Save,
    X,
    Key,
    CheckCircle,
    AlertCircle,
    Image,
    Upload,
    Trash2
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { useCountry } from '@/hooks/use-country';
import { formatRut } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface ProfileData {
    id: number;
    name: string;
    email: string;
    rut: string | null;
    telefono: string | null;
    direccion: string | null;
    giro: string | null;
    ciudad: string | null;
    region: string | null;
    comuna: string | null;
    fecha_nacimiento: string | null;
    genero: string | null;
    tipo_entidad: string | null;
    job: string | null;
    location: string | null;
    profile_photo_url: string | null;
    cover_photo_path: string | null;
    business_logo_url: string | null;
    business_name: string | null;
    roles: string[];
    permissions: string[];
    email_verified_at: string | null;
    created_at: string;
    two_factor_enabled: boolean;
}

interface PageProps {
    profile: ProfileData;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mi Información',
        href: '/mi-informacion',
    },
];

export default function MiInformacion() {
    const { code: countryCode, currency } = useCountry();
    const { profile } = usePage<PageProps>().props;
    const [editando, setEditando] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);
    const [removeLogo, setRemoveLogo] = useState(false);
    const [guardandoLogo, setGuardandoLogo] = useState(false);
    const [editandoNombre, setEditandoNombre] = useState(false);
    const [nombreEdit, setNombreEdit] = useState(profile.name);
    const [guardandoNombre, setGuardandoNombre] = useState(false);
    const logoInputRef = useRef<HTMLInputElement>(null);
    const [formData, setFormData] = useState({
        name: profile.name,
        rut: profile.rut || '',
        telefono: profile.telefono || '',
        direccion: profile.direccion || '',
        giro: profile.giro || '',
        ciudad: profile.ciudad || '',
        region: profile.region || '',
        comuna: profile.comuna || '',
        fecha_nacimiento: profile.fecha_nacimiento || '',
        genero: profile.genero || '',
        job: profile.job || '',
        location: profile.location || '',
        business_name: profile.business_name || '',
    });

    const handleChange = (field: string, value: string) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
    };

    const handleLogoFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        if (file) {
            setLogoFile(file);
            setRemoveLogo(false);
            const reader = new FileReader();
            reader.onload = () => setLogoPreview(reader.result as string);
            reader.readAsDataURL(file);
        }
    };

    const handleRemoveLogo = () => {
        setLogoFile(null);
        setLogoPreview(null);
        setRemoveLogo(true);
        if (logoInputRef.current) logoInputRef.current.value = '';
    };

    const handleGuardarNombre = () => {
        setGuardandoNombre(true);
        const form = new FormData();
        form.append('_method', 'PATCH');
        form.append('name', nombreEdit);
        router.post('/mi-informacion', form, {
            onSuccess: () => {
                setGuardandoNombre(false);
                setEditandoNombre(false);
                toast.success('Nombre actualizado correctamente');
                router.reload({ only: ['auth'] });
            },
            onError: () => {
                setGuardandoNombre(false);
                toast.error('Error al actualizar el nombre');
            },
        });
    };

    const handleGuardarLogo = () => {
        setGuardandoLogo(true);
        const form = new FormData();
        form.append('_method', 'PATCH');
        form.append('name', profile.name);
        if (logoFile) {
            form.append('business_logo', logoFile);
        }
        if (removeLogo) {
            form.append('remove_business_logo', '1');
        }
        router.post('/mi-informacion', form, {
            onSuccess: () => {
                setGuardandoLogo(false);
                setLogoFile(null);
                setLogoPreview(null);
                setRemoveLogo(false);
                toast.success('Logo actualizado correctamente');
                router.reload({ only: ['auth'] });
            },
            onError: () => {
                setGuardandoLogo(false);
                toast.error('Error al actualizar el logo');
            },
        });
    };

    const handleGuardar = () => {
        setGuardando(true);

        const form = new FormData();
        form.append('_method', 'PATCH');
        Object.entries(formData).forEach(([key, value]) => {
            form.append(key, value);
        });
        if (logoFile) {
            form.append('business_logo', logoFile);
        }
        if (removeLogo) {
            form.append('remove_business_logo', '1');
        }

        router.post('/mi-informacion', form, {
            onSuccess: () => {
                setEditando(false);
                setGuardando(false);
                setLogoFile(null);
                setLogoPreview(null);
                setRemoveLogo(false);
                toast.success('Información actualizada correctamente');
                router.reload({ only: ['auth'] });
            },
            onError: () => {
                setGuardando(false);
                toast.error('Error al actualizar la información');
            },
        });
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'No verificado';
        return new Date(dateString).toLocaleDateString(currency.locale, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mi Información Personal" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            Mi Información Personal
                        </h2>
                        <p className="text-muted-foreground">
                            Gestiona tu información personal y datos de contacto
                        </p>
                    </div>
                    {!editando && (
                        <Button onClick={() => setEditando(true)}>
                            <Edit2 className="mr-2 h-4 w-4" />
                            Editar Información
                        </Button>
                    )}
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <User className="h-5 w-5 text-primary" />
                            <h3 className="font-semibold">Datos Personales</h3>
                        </div>
                        <div className="space-y-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                    {profile.profile_photo_url ? (
                                        <img
                                            src={profile.profile_photo_url}
                                            alt={profile.name}
                                            className="h-10 w-10 rounded-full object-cover"
                                        />
                                    ) : (
                                        <span className="text-lg font-bold text-primary">
                                            {profile.name
                                                .charAt(0)
                                                .toUpperCase()}
                                        </span>
                                    )}
                                </div>
                                <div className="flex-1">
                                    {editandoNombre ? (
                                        <div className="flex items-center gap-2">
                                            <Input
                                                value={nombreEdit}
                                                onChange={(e) => setNombreEdit(e.target.value)}
                                                className="h-8 text-sm"
                                            />
                                            <Button size="sm" onClick={handleGuardarNombre} disabled={guardandoNombre} className="h-8">
                                                {guardandoNombre ? '...' : <CheckCircle className="h-4 w-4" />}
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => { setEditandoNombre(false); setNombreEdit(profile.name); }} className="h-8">
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ) : (
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium">{profile.name}</p>
                                            <button onClick={() => { setEditandoNombre(true); setNombreEdit(profile.name); }} className="text-muted-foreground hover:text-foreground">
                                                <Edit2 className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        {profile.tipo_entidad || 'Usuario'}
                                    </p>
                                </div>
                            </div>

                            {editando ? (
                                <div className="space-y-3">
                                    <div>
                                        <Label htmlFor="name">
                                            Nombre Completo
                                        </Label>
                                        <Input
                                            id="name"
                                            value={formData.name}
                                            onChange={(e) =>
                                                handleChange(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="rut">RUT</Label>
                                        <Input
                                            id="rut"
                                            value={formData.rut}
                                            onChange={(e) =>
                                                handleChange(
                                                    'rut',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={(e) =>
                                                handleChange(
                                                    'rut',
                                                    formatRut(e.target.value),
                                                )
                                            }
                                            placeholder="12.345.678-9"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="fecha_nacimiento">
                                            Fecha de Nacimiento
                                        </Label>
                                        <Input
                                            id="fecha_nacimiento"
                                            type="date"
                                            value={formData.fecha_nacimiento}
                                            onChange={(e) =>
                                                handleChange(
                                                    'fecha_nacimiento',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="genero">Género</Label>
                                        <Select
                                            value={formData.genero}
                                            onValueChange={(value) => handleChange('genero', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Seleccionar..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Masculino">Masculino</SelectItem>
                                                <SelectItem value="Femenino">Femenino</SelectItem>
                                                <SelectItem value="Prefiero no especificar">Prefiero no especificar</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            RUT
                                        </span>
                                        <span className="font-medium">
                                            {profile.rut || 'No registrado'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Fecha de Nacimiento
                                        </span>
                                        <span className="font-medium">
                                            {profile.fecha_nacimiento
                                                ? formatDate(
                                                    profile.fecha_nacimiento,
                                                )
                                                : 'No registrado'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Género
                                        </span>
                                        <span className="font-medium">
                                            {profile.genero || 'No registrado'}
                                        </span>
                                    </div>
                                    {profile.job && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Profesión/Cargo
                                            </span>
                                            <span className="font-medium">
                                                {profile.job}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <Mail className="h-5 w-5 text-primary" />
                            <h3 className="font-semibold">
                                Información de Contacto
                            </h3>
                        </div>
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <Mail className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm">{profile.email}</span>
                                {profile.email_verified_at ? (
                                    <CheckCircle className="h-4 w-4 text-emerald-500" />
                                ) : (
                                    <AlertCircle className="h-4 w-4 text-amber-500" />
                                )}
                            </div>

                            {editando ? (
                                <div className="space-y-3">
                                    <div>
                                        <Label htmlFor="telefono">
                                            Teléfono
                                        </Label>
                                        <Input
                                            id="telefono"
                                            value={formData.telefono}
                                            onChange={(e) =>
                                                handleChange(
                                                    'telefono',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="+56 9 1234 5678"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="direccion">
                                            Dirección
                                        </Label>
                                        <Input
                                            id="direccion"
                                            value={formData.direccion}
                                            onChange={(e) =>
                                                handleChange(
                                                    'direccion',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Calle, número, depto..."
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="giro">
                                            Giro Comercial
                                        </Label>
                                        <Input
                                            id="giro"
                                            value={formData.giro}
                                            onChange={(e) =>
                                                handleChange(
                                                    'giro',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Ej: Venta de combustibles, Supermercado..."
                                        />
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
                                        <div>
                                            <Label htmlFor="region">
                                                Región
                                            </Label>
                                            <Input
                                                id="region"
                                                value={formData.region}
                                                onChange={(e) =>
                                                    handleChange(
                                                        'region',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Región"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="ciudad">
                                                Ciudad
                                            </Label>
                                            <Input
                                                id="ciudad"
                                                value={formData.ciudad}
                                                onChange={(e) =>
                                                    handleChange(
                                                        'ciudad',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Ciudad"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="comuna">
                                                Comuna
                                            </Label>
                                            <Input
                                                id="comuna"
                                                value={formData.comuna}
                                                onChange={(e) =>
                                                    handleChange(
                                                        'comuna',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Comuna"
                                            />
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-2 text-sm">
                                    <div className="flex items-center gap-2">
                                        <Phone className="h-4 w-4 text-muted-foreground" />
                                        <span className="font-medium">
                                            {profile.telefono ||
                                                'No registrado'}
                                        </span>
                                    </div>
                                    {profile.direccion && (
                                        <div className="flex items-start gap-2">
                                            <MapPin className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                            <span>
                                                {profile.direccion}
                                                {profile.comuna &&
                                                    `, ${profile.comuna}`}
                                                {profile.ciudad &&
                                                    `, ${profile.ciudad}`}
                                                {profile.region &&
                                                    `, ${profile.region}`}
                                            </span>
                                        </div>
                                    )}
                                    {profile.location && (
                                        <div className="flex items-center gap-2">
                                            <MapPin className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">
                                                Ubicación: {profile.location}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <Shield className="h-5 w-5 text-primary" />
                            <h3 className="font-semibold">Seguridad</h3>
                        </div>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Key className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm">
                                        Autenticación de Dos Factores
                                    </span>
                                </div>
                                <Badge
                                    variant={
                                        profile.two_factor_enabled
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {profile.two_factor_enabled
                                        ? 'Activa'
                                        : 'Inactiva'}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm">
                                        Email verificado
                                    </span>
                                </div>
                                <Badge
                                    variant={
                                        profile.email_verified_at
                                            ? 'default'
                                            : 'destructive'
                                    }
                                >
                                    {profile.email_verified_at
                                        ? 'Verificado'
                                        : 'Pendiente'}
                                </Badge>
                            </div>
                            <Button
                                variant="outline"
                                className="w-full"
                                asChild
                            >
                                <a href="/settings/two-factor">
                                    <Key className="mr-2 h-4 w-4" />
                                    Configurar Seguridad
                                </a>
                            </Button>
                        </div>
                    </div>



                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <Building2 className="h-5 w-5 text-primary" />
                            <h3 className="font-semibold">Nombre de la Empresa</h3>
                        </div>
                        <div className="space-y-4">
                            {editando ? (
                                <div>
                                    <Input
                                        value={formData.business_name}
                                        onChange={(e) => handleChange('business_name', e.target.value)}
                                        placeholder="Nombre de tu empresa o negocio"
                                    />
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {profile.business_name || 'No especificado'}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Este nombre se usará en facturas, proformas y documentos.
                            </p>
                        </div>
                    </div>

                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <Image className="h-5 w-5 text-primary" />
                            <h3 className="font-semibold">Logo de la Empresa</h3>
                        </div>
                        <div className="space-y-4">
                            <div className="flex items-center gap-4">
                                <div className="flex h-20 w-44 items-center justify-center rounded-xl border bg-muted/30 p-2">
                                    {logoPreview || (profile.business_logo_url && !removeLogo) ? (
                                        <img
                                            src={logoPreview || profile.business_logo_url!}
                                            alt="Logo empresa"
                                            className="h-full w-full rounded-lg object-contain"
                                        />
                                    ) : (
                                        <div className="flex flex-col items-center gap-1 text-muted-foreground">
                                            <Building2 className="h-8 w-8" />
                                            <span className="text-xs">Sin logo</span>
                                        </div>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <input
                                        ref={logoInputRef}
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                        onChange={handleLogoFile}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => logoInputRef.current?.click()}
                                    >
                                        <Upload className="mr-2 h-4 w-4" />
                                        {profile.business_logo_url ? 'Cambiar Logo' : 'Subir Logo'}
                                    </Button>
                                    {(logoPreview || profile.business_logo_url) && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="text-destructive hover:text-destructive"
                                            onClick={handleRemoveLogo}
                                        >
                                            <Trash2 className="mr-2 h-4 w-4" />
                                            Eliminar Logo
                                        </Button>
                                    )}
                                </div>
                            </div>
                            {(logoFile || removeLogo) && (
                                <Button onClick={handleGuardarLogo} disabled={guardandoLogo} size="sm">
                                    {guardandoLogo ? 'Guardando...' : 'Guardar Logo'}
                                </Button>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Este logo se usará en tu dashboard, facturas, proformas y documentos. Formatos: PNG, JPG. Máximo 2MB.
                            </p>
                        </div>
                    </div>

                    <div className="rounded-lg border bg-card p-6 shadow-sm md:col-span-2">
                        <div className="mb-4 flex items-center gap-2">
                            <Clock className="h-5 w-5 text-primary" />
                            <h3 className="font-semibold">
                                Información de Cuenta
                            </h3>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                            <div>
                                <p className="text-muted-foreground">
                                    ID de Usuario
                                </p>
                                <p className="font-medium">#{profile.id}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">
                                    Miembro desde
                                </p>
                                <p className="font-medium">
                                    {formatDate(profile.created_at)}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">
                                    Email verificado
                                </p>
                                <p className="font-medium">
                                    {formatDate(profile.email_verified_at)}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">2FA</p>
                                <p className="font-medium">
                                    {profile.two_factor_enabled
                                        ? 'Activado'
                                        : 'Desactivado'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {editando && (
                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button
                            variant="outline"
                            onClick={() => setEditando(false)}
                            disabled={guardando}
                        >
                            <X className="mr-2 h-4 w-4" />
                            Cancelar
                        </Button>
                        <Button onClick={handleGuardar} disabled={guardando}>
                            {guardando ? (
                                <>
                                    <Clock className="mr-2 h-4 w-4 animate-spin" />
                                    Guardando...
                                </>
                            ) : (
                                <>
                                    <Save className="mr-2 h-4 w-4" />
                                    Guardar Cambios
                                </>
                            )}
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

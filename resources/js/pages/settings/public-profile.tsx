import { Head, useForm } from '@inertiajs/react';
import {
    Save,
    Store,
    Link as LinkIcon,
    Phone,
    Mail,
    FileText,
    CheckCircle2,
} from 'lucide-react';
import { useState, type FormEventHandler } from 'react';
import InputError from '@/components/input-error';
import StoreLocationMap from '@/components/store-location-map';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { update } from '@/routes/public-profile';
import type { BreadcrumbItem } from '@/types';

interface PublicProfile {
    id?: number;
    title: string;
    slug: string;
    description: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
    pickup_address?: string | null;
    pickup_lat?: number | null;
    pickup_lng?: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Perfil Público (Tienda)',
        href: '/settings/public-profile',
    },
];

export default function EditPublicProfile({
    publicProfile,
}: {
    publicProfile: PublicProfile;
}) {
    const [locationMessage, setLocationMessage] = useState('');
    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            title: publicProfile.title || '',
            slug: publicProfile.slug || '',
            description: publicProfile.description || '',
            phone: publicProfile.phone || '',
            email: publicProfile.email || '',
            is_active: publicProfile.is_active || false,
            pickup_address: publicProfile.pickup_address || '',
            pickup_lat: publicProfile.pickup_lat?.toString() ?? '',
            pickup_lng: publicProfile.pickup_lng?.toString() ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(update().url);
    };

    const locationReady =
        data.pickup_address.trim() !== '' &&
        data.pickup_lat.trim() !== '' &&
        data.pickup_lng.trim() !== '' &&
        Number.isFinite(Number(data.pickup_lat)) &&
        Number.isFinite(Number(data.pickup_lng)) &&
        Math.abs(Number(data.pickup_lat)) <= 90 &&
        Math.abs(Number(data.pickup_lng)) <= 180;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Perfil Público" />
            <SettingsLayout>
                <div className="space-y-6">
                    <div>
                        <h2 className="text-xl font-bold tracking-tight">
                            Configuración de Tienda
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Administra cómo se ve tu perfil público y tu tienda
                            en línea.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center justify-between rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                                <div className="space-y-0.5">
                                    <Label className="text-base font-semibold text-slate-900 dark:text-white">
                                        Activar Tienda Pública
                                    </Label>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Permite que otros usuarios vean tu
                                        perfil tipo eCommerce.
                                    </p>
                                </div>
                                <Switch
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked)
                                    }
                                />
                            </div>

                            <div className="space-y-4">
                                <fieldset className="space-y-3 rounded-lg border p-4">
                                    <legend className="px-1 font-semibold">
                                        Punto de retiro para repartidores
                                    </legend>
                                    <p className="text-sm text-muted-foreground">
                                        Tu tienda activa aparecerá en el mapa
                                        cuando guardes su ubicación. Usa la
                                        ubicación del comercio, donde se retiran
                                        los productos.
                                    </p>
                                    <Label htmlFor="pickup_address">
                                        Dirección del comercio
                                    </Label>
                                    <Input
                                        id="pickup_address"
                                        value={data.pickup_address}
                                        onChange={(event) =>
                                            setData(
                                                'pickup_address',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Calle, número, comuna y ciudad"
                                    />
                                    <InputError
                                        message={errors.pickup_address}
                                    />
                                    <StoreLocationMap
                                        latitude={data.pickup_lat}
                                        longitude={data.pickup_lng}
                                        address={data.pickup_address}
                                        onChange={(
                                            latitude,
                                            longitude,
                                            address,
                                        ) => {
                                            setData((current) => ({
                                                ...current,
                                                pickup_lat: latitude,
                                                pickup_lng: longitude,
                                                pickup_address:
                                                    address ??
                                                    current.pickup_address,
                                            }));
                                        }}
                                    />
                                    <p
                                        data-testid="store-map-readiness"
                                        className="rounded-lg bg-muted p-3 text-sm"
                                    >
                                        {!data.is_active
                                            ? 'La tienda está desactivada y no aparecerá en el mapa de repartidores.'
                                            : locationReady
                                              ? 'Datos completos para el mapa de repartidores. Guarda los cambios para publicarlos.'
                                              : 'Para aparecer en el mapa, completa la dirección y ambas coordenadas del punto de retiro.'}
                                    </p>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1">
                                            <Label htmlFor="pickup_lat">
                                                Latitud
                                            </Label>
                                            <Input
                                                id="pickup_lat"
                                                type="number"
                                                step="any"
                                                min="-90"
                                                max="90"
                                                value={data.pickup_lat}
                                                onChange={(event) =>
                                                    setData(
                                                        'pickup_lat',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.pickup_lat}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="pickup_lng">
                                                Longitud
                                            </Label>
                                            <Input
                                                id="pickup_lng"
                                                type="number"
                                                step="any"
                                                min="-180"
                                                max="180"
                                                value={data.pickup_lng}
                                                onChange={(event) =>
                                                    setData(
                                                        'pickup_lng',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.pickup_lng}
                                            />
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            if (!navigator.geolocation) {
                                                setLocationMessage(
                                                    'El navegador no permite obtener tu ubicación. Ingresa las coordenadas del comercio.',
                                                );
                                                return;
                                            }
                                            setLocationMessage(
                                                'Obteniendo ubicación…',
                                            );
                                            navigator.geolocation.getCurrentPosition(
                                                (position) => {
                                                    setData((current) => ({
                                                        ...current,
                                                        pickup_lat:
                                                            position.coords.latitude.toFixed(
                                                                7,
                                                            ),
                                                        pickup_lng:
                                                            position.coords.longitude.toFixed(
                                                                7,
                                                            ),
                                                    }));
                                                    setLocationMessage(
                                                        'Ubicación obtenida. Guarda los cambios para publicarla.',
                                                    );
                                                },
                                                () =>
                                                    setLocationMessage(
                                                        'No se pudo obtener la ubicación. Autoriza el acceso o ingresa las coordenadas.',
                                                    ),
                                                {
                                                    enableHighAccuracy: true,
                                                    timeout: 15000,
                                                },
                                            );
                                        }}
                                    >
                                        Usar mi ubicación actual
                                    </Button>
                                    <p
                                        role="status"
                                        className="text-sm text-muted-foreground"
                                    >
                                        {locationMessage}
                                    </p>
                                </fieldset>
                                <div>
                                    <Label
                                        htmlFor="title"
                                        className="flex items-center gap-2"
                                    >
                                        <Store className="h-4 w-4 text-slate-500" />
                                        Nombre de la Tienda
                                    </Label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) =>
                                            setData('title', e.target.value)
                                        }
                                        className="mt-1"
                                        placeholder="Ej: Mi Tienda Online"
                                    />
                                    <InputError
                                        message={errors.title}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <Label
                                        htmlFor="slug"
                                        className="flex items-center gap-2"
                                    >
                                        <LinkIcon className="h-4 w-4 text-slate-500" />
                                        URL de la Tienda (Identificador único)
                                    </Label>
                                    <div className="mt-1 flex rounded-md shadow-sm">
                                        <span className="inline-flex items-center rounded-l-md border border-r-0 border-slate-300 bg-slate-50 px-3 text-slate-500 focus-within:z-10 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary sm:text-sm dark:border-slate-700 dark:bg-slate-800">
                                            /tienda/
                                        </span>
                                        <Input
                                            id="slug"
                                            value={data.slug}
                                            onChange={(e) =>
                                                setData('slug', e.target.value)
                                            }
                                            className="rounded-l-none"
                                            placeholder="mi-tienda"
                                        />
                                    </div>
                                    <InputError
                                        message={errors.slug}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <Label
                                        htmlFor="description"
                                        className="flex items-center gap-2"
                                    >
                                        <FileText className="h-4 w-4 text-slate-500" />
                                        Descripción
                                    </Label>
                                    <Textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) =>
                                            setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 min-h-[120px]"
                                        placeholder="Describe tu tienda o servicios..."
                                    />
                                    <InputError
                                        message={errors.description}
                                        className="mt-2"
                                    />
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label
                                            htmlFor="phone"
                                            className="flex items-center gap-2"
                                        >
                                            <Phone className="h-4 w-4 text-slate-500" />
                                            Teléfono de Contacto
                                        </Label>
                                        <Input
                                            id="phone"
                                            value={data.phone}
                                            onChange={(e) =>
                                                setData('phone', e.target.value)
                                            }
                                            className="mt-1"
                                            placeholder="+56 9 1234 5678"
                                        />
                                        <InputError
                                            message={errors.phone}
                                            className="mt-2"
                                        />
                                    </div>

                                    <div>
                                        <Label
                                            htmlFor="email"
                                            className="flex items-center gap-2"
                                        >
                                            <Mail className="h-4 w-4 text-slate-500" />
                                            Email de Contacto
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) =>
                                                setData('email', e.target.value)
                                            }
                                            className="mt-1"
                                            placeholder="contacto@mitienda.com"
                                        />
                                        <InputError
                                            message={errors.email}
                                            className="mt-2"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-4 border-t border-slate-100 pt-5 dark:border-slate-800">
                                {recentlySuccessful && (
                                    <p className="flex items-center text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                        <CheckCircle2 className="mr-2 h-4 w-4" />
                                        Guardado correctamente
                                    </p>
                                )}
                                <Button
                                    disabled={processing}
                                    className="min-w-[150px]"
                                >
                                    <Save className="mr-2 h-4 w-4" />
                                    Guardar Cambios
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

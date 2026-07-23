import { Head, useForm } from '@inertiajs/react';
import { Save, Store, Link as LinkIcon, Phone, Mail, FileText, CheckCircle2 } from 'lucide-react';
import type { FormEventHandler } from 'react';
import InputError from '@/components/input-error';
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
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Perfil Público (Tienda)',
        href: '/settings/public-profile',
    },
];

export default function EditPublicProfile({ publicProfile }: { publicProfile: PublicProfile }) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        title: publicProfile.title || '',
        slug: publicProfile.slug || '',
        description: publicProfile.description || '',
        phone: publicProfile.phone || '',
        email: publicProfile.email || '',
        is_active: publicProfile.is_active || false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(update().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Perfil Público" />
            <SettingsLayout>
                <div className="space-y-6">
                    <div>
                        <h2 className="text-xl font-bold tracking-tight">Configuración de Tienda</h2>
                        <p className="text-sm text-muted-foreground">
                            Administra cómo se ve tu perfil público y tu tienda en línea.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            
                            <div className="flex items-center justify-between rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                                <div className="space-y-0.5">
                                    <Label className="text-base font-semibold text-slate-900 dark:text-white">Activar Tienda Pública</Label>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Permite que otros usuarios vean tu perfil tipo eCommerce.
                                    </p>
                                </div>
                                <Switch
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked)}
                                />
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <Label htmlFor="title" className="flex items-center gap-2">
                                        <Store className="h-4 w-4 text-slate-500" />
                                        Nombre de la Tienda
                                    </Label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        className="mt-1"
                                        placeholder="Ej: Mi Tienda Online"
                                    />
                                    <InputError message={errors.title} className="mt-2" />
                                </div>

                                <div>
                                    <Label htmlFor="slug" className="flex items-center gap-2">
                                        <LinkIcon className="h-4 w-4 text-slate-500" />
                                        URL de la Tienda (Identificador único)
                                    </Label>
                                    <div className="mt-1 flex rounded-md shadow-sm">
                                        <span className="inline-flex items-center rounded-l-md border border-r-0 border-slate-300 bg-slate-50 px-3 text-slate-500 sm:text-sm dark:border-slate-700 dark:bg-slate-800 focus-within:z-10 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary">
                                            /tienda/
                                        </span>
                                        <Input
                                            id="slug"
                                            value={data.slug}
                                            onChange={(e) => setData('slug', e.target.value)}
                                            className="rounded-l-none"
                                            placeholder="mi-tienda"
                                        />
                                    </div>
                                    <InputError message={errors.slug} className="mt-2" />
                                </div>

                                <div>
                                    <Label htmlFor="description" className="flex items-center gap-2">
                                        <FileText className="h-4 w-4 text-slate-500" />
                                        Descripción
                                    </Label>
                                    <Textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        className="mt-1 min-h-[120px]"
                                        placeholder="Describe tu tienda o servicios..."
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="phone" className="flex items-center gap-2">
                                            <Phone className="h-4 w-4 text-slate-500" />
                                            Teléfono de Contacto
                                        </Label>
                                        <Input
                                            id="phone"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            className="mt-1"
                                            placeholder="+56 9 1234 5678"
                                        />
                                        <InputError message={errors.phone} className="mt-2" />
                                    </div>

                                    <div>
                                        <Label htmlFor="email" className="flex items-center gap-2">
                                            <Mail className="h-4 w-4 text-slate-500" />
                                            Email de Contacto
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="mt-1"
                                            placeholder="contacto@mitienda.com"
                                        />
                                        <InputError message={errors.email} className="mt-2" />
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
                                <Button disabled={processing} className="min-w-[150px]">
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

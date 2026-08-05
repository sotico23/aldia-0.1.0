import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Country {
    id: number;
    code: string;
    name: string;
    currency_code: string;
    currency_symbol: string;
    currency_decimals: number;
    locale: string;
    timezone: string;
    phone_code: string;
    tax_name: string;
    tax_rate: number;
    fiscal_id_label: string;
    fiscal_id_pattern: string | null;
    date_format: string;
    is_active: boolean;
}

interface CountryFormData {
    code: string;
    name: string;
    currency_code: string;
    currency_symbol: string;
    currency_decimals: number;
    locale: string;
    timezone: string;
    phone_code: string;
    tax_name: string;
    tax_rate: number;
    fiscal_id_label: string;
    fiscal_id_pattern: string;
    date_format: string;
    is_active: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Países', href: '/paises' },
];

function getFlagEmoji(countryCode: string): string {
    const codePoints = [...countryCode.toUpperCase()].map(
        (c) => 0x1f1e6 - 65 + c.charCodeAt(0),
    );
    return String.fromCodePoint(...codePoints);
}

const emptyCountry: CountryFormData = {
    code: '',
    name: '',
    currency_code: '',
    currency_symbol: '',
    currency_decimals: 0,
    locale: '',
    timezone: '',
    phone_code: '',
    tax_name: '',
    tax_rate: 0,
    fiscal_id_label: '',
    fiscal_id_pattern: '',
    date_format: 'DD/MM/YYYY',
    is_active: true,
};

export default function Index({ countries }: { countries: Country[] }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('admin.countries.create');
    const canEdit = hasPermission('admin.countries.edit');
    const canDelete = hasPermission('admin.countries.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Country | null>(null);
    const { data, setData, post, put, delete: destroy, reset, processing } = useForm<CountryFormData>(emptyCountry);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/paises/${editando.id}`, {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/paises', {
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (c: Country) => {
        setEditando(c);
        setData({
            code: c.code,
            name: c.name,
            currency_code: c.currency_code,
            currency_symbol: c.currency_symbol,
            currency_decimals: c.currency_decimals,
            locale: c.locale,
            timezone: c.timezone,
            phone_code: c.phone_code,
            tax_name: c.tax_name,
            tax_rate: c.tax_rate,
            fiscal_id_label: c.fiscal_id_label,
            fiscal_id_pattern: c.fiscal_id_pattern || '',
            date_format: c.date_format,
            is_active: c.is_active,
        });
        setIsOpen(true);
    };

    const handleNew = () => {
        reset();
        setData(emptyCountry);
        setEditando(null);
        setIsOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar este país?')) destroy(`/paises/${id}`);
    };

    return (
        <>
            <Head title="Países" />
            <AppLayout breadcrumbs={breadcrumbs}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">Países</h1>
                            <p className="text-muted-foreground">
                                Gestiona los países, monedas y prefijos telefónicos disponibles en la plataforma
                            </p>
                        </div>
                        {canCreate && (
                            <Button onClick={handleNew}>
                                <Plus className="mr-2 h-4 w-4" /> Nuevo País
                            </Button>
                        )}
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Países Soportados</CardTitle>
                            <CardDescription>
                                {countries.length} países registrados
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {countries.length === 0 ? (
                                <p className="py-8 text-center text-muted-foreground">
                                    No hay países registrados
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-2 text-left">País</th>
                                                <th className="py-2 text-left">Código</th>
                                                <th className="py-2 text-left">Moneda</th>
                                                <th className="py-2 text-left">Teléfono</th>
                                                <th className="py-2 text-left">Impuesto</th>
                                                <th className="py-2 text-left">ID Fiscal</th>
                                                <th className="py-2 text-left">Timezone</th>
                                                <th className="py-2 text-center">Activo</th>
                                                <th className="py-2 text-right">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {countries.map((c) => (
                                                <tr key={c.id} className="border-b hover:bg-muted/50">
                                                    <td className="py-2">
                                                        <span className="mr-2 text-lg">{getFlagEmoji(c.code)}</span>
                                                        <span className="font-medium">{c.name}</span>
                                                    </td>
                                                    <td className="py-2 font-mono text-sm">{c.code}</td>
                                                    <td className="py-2">
                                                        <span className="font-medium">{c.currency_symbol}</span>{' '}
                                                        <span className="text-muted-foreground">{c.currency_code}</span>
                                                    </td>
                                                    <td className="py-2 font-mono text-sm">{c.phone_code}</td>
                                                    <td className="py-2">
                                                        {c.tax_name} ({c.tax_rate}%)
                                                    </td>
                                                    <td className="py-2">{c.fiscal_id_label}</td>
                                                    <td className="py-2 text-sm text-muted-foreground">{c.timezone}</td>
                                                    <td className="py-2 text-center">
                                                        <Badge className={c.is_active ? 'bg-green-500' : 'bg-gray-500'}>
                                                            {c.is_active ? 'Activo' : 'Inactivo'}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-2 text-right">
                                                        {canEdit && (
                                                            <Button variant="ghost" size="icon" onClick={() => handleEdit(c)}>
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {canDelete && (
                                                            <Button variant="ghost" size="icon" onClick={() => handleDelete(c.id)}>
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nuevo'} País
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label>Código (2 letras) *</Label>
                                    <Input
                                        value={data.code as string}
                                        onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                        placeholder="CL"
                                        maxLength={2}
                                        required
                                        disabled={!!editando}
                                    />
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label>Nombre del País *</Label>
                                    <Input
                                        value={data.name as string}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Chile"
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div className="space-y-2">
                                    <Label>Código Moneda *</Label>
                                    <Input
                                        value={data.currency_code as string}
                                        onChange={(e) => setData('currency_code', e.target.value.toUpperCase())}
                                        placeholder="CLP"
                                        maxLength={3}
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Símbolo *</Label>
                                    <Input
                                        value={data.currency_symbol as string}
                                        onChange={(e) => setData('currency_symbol', e.target.value)}
                                        placeholder="$"
                                        maxLength={10}
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Decimales *</Label>
                                    <Input
                                        type="number"
                                        value={data.currency_decimals as number}
                                        onChange={(e) => setData('currency_decimals', parseInt(e.target.value) || 0)}
                                        min={0}
                                        max={4}
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Formato Fecha *</Label>
                                    <Input
                                        value={data.date_format as string}
                                        onChange={(e) => setData('date_format', e.target.value)}
                                        placeholder="DD/MM/YYYY"
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label>Locale *</Label>
                                    <Input
                                        value={data.locale as string}
                                        onChange={(e) => setData('locale', e.target.value)}
                                        placeholder="es-CL"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Timezone *</Label>
                                    <Input
                                        value={data.timezone as string}
                                        onChange={(e) => setData('timezone', e.target.value)}
                                        placeholder="America/Santiago"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Código Teléfono *</Label>
                                    <Input
                                        value={data.phone_code as string}
                                        onChange={(e) => setData('phone_code', e.target.value)}
                                        placeholder="+56"
                                        maxLength={5}
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div className="space-y-2">
                                    <Label>Nombre Impuesto *</Label>
                                    <Input
                                        value={data.tax_name as string}
                                        onChange={(e) => setData('tax_name', e.target.value)}
                                        placeholder="IVA"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Tasa (%) *</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={data.tax_rate as number}
                                        onChange={(e) => setData('tax_rate', parseFloat(e.target.value) || 0)}
                                        min={0}
                                        max={100}
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>ID Fiscal *</Label>
                                    <Input
                                        value={data.fiscal_id_label as string}
                                        onChange={(e) => setData('fiscal_id_label', e.target.value)}
                                        placeholder="RUT"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Patrón ID Fiscal</Label>
                                    <Input
                                        value={data.fiscal_id_pattern as string}
                                        onChange={(e) => setData('fiscal_id_pattern', e.target.value)}
                                        placeholder="/^\d{7,8}[\dkK]$/"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.is_active as boolean}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="h-4 w-4"
                                />
                                <Label>País activo (visible en registro)</Label>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editando ? 'Actualizar' : 'Crear'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

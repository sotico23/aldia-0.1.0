import { useForm, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Eye, EyeOff, Save, X, Power, ArrowUpDown, ChevronUp, ChevronDown } from 'lucide-react';
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

export interface Country {
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Configuración Web', href: '/configuracion-web' },
    { title: 'Países', href: '/configuracion-web#paises' },
];

function getFlagEmoji(countryCode: string): string {
    const codePoints = [...countryCode.toUpperCase()].map(
        (c) => 0x1f1e6 - 65 + c.charCodeAt(0),
    );
    return String.fromCodePoint(...codePoints);
}

const emptyCountry: Record<string, unknown> = {
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

export default function CountriesTab({ countries }: { countries: Country[] }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('admin.countries.create');
    const canEdit = hasPermission('admin.countries.edit');
    const canDelete = hasPermission('admin.countries.delete');

    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Country | null>(null);
    const [showOnlyActive, setShowOnlyActive] = useState(false);
    const { data, setData, post, put, delete: destroy, reset, processing } = useForm(emptyCountry);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando) {
            put(`/paises/${editando.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                },
            });
        } else {
            post('/paises', {
                preserveScroll: true,
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
        if (confirm('¿Eliminar este país?')) {
            destroy(`/paises/${id}`, { preserveScroll: true });
        }
    };

    const handleToggle = (id: number) => {
        router.patch(`/paises/${id}/toggle`, {}, { preserveScroll: true });
    };

    const [sortColumn, setSortColumn] = useState<keyof Country>('name');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');

    const handleSort = (column: keyof Country) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    const filteredCountries = showOnlyActive ? countries.filter(c => c.is_active) : countries;
    
    const sortedCountries = [...filteredCountries].sort((a, b) => {
        let valA = a[sortColumn];
        let valB = b[sortColumn];

        if (typeof valA === 'string' && typeof valB === 'string') {
            valA = valA.toLowerCase();
            valB = valB.toLowerCase();
        }

        if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
        if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold">Países</h1>
                    <p className="text-muted-foreground">
                        Gestiona los países disponibles para el registro de usuarios
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setShowOnlyActive(!showOnlyActive)}
                    >
                        {showOnlyActive ? (
                            <>
                                <EyeOff className="mr-2 h-4 w-4" />
                                Mostrar todos ({countries.length})
                            </>
                        ) : (
                            <>
                                <Eye className="mr-2 h-4 w-4" />
                                Solo activos ({countries.filter(c => c.is_active).length})
                            </>
                        )}
                    </Button>
                    {canCreate && (
                        <Button onClick={handleNew}>
                            <Plus className="mr-2 h-4 w-4" /> Nuevo País
                        </Button>
                    )}
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Países Soportados</CardTitle>
                    <CardDescription>
                        {countries.filter(c => c.is_active).length} activos de {countries.length} países en total
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {filteredCountries.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {showOnlyActive
                                ? 'No hay países activos. Desactiva el filtro para ver todos.'
                                : 'No hay países registrados'}
                        </p>
                    ) : (
                        <div className="overflow-auto max-h-[65vh] border rounded-md">
                            <table className="w-full min-w-[1000px] text-sm relative">
                                <thead className="sticky top-0 bg-background z-10 shadow-sm border-b">
                                    <tr>
                                        <th className="py-3 px-4 text-left font-medium text-muted-foreground whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('name')}>
                                            <div className="flex items-center gap-1">País {sortColumn === 'name' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                        </th>
                                        <th className="py-3 px-4 text-left font-medium text-muted-foreground whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('code')}>
                                            <div className="flex items-center gap-1">Código {sortColumn === 'code' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                        </th>
                                        <th className="py-3 px-4 text-left font-medium text-muted-foreground whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('currency_code')}>
                                            <div className="flex items-center gap-1">Moneda {sortColumn === 'currency_code' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                        </th>
                                        <th className="py-3 px-4 text-left font-medium text-muted-foreground whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('phone_code')}>
                                            <div className="flex items-center gap-1">Teléfono {sortColumn === 'phone_code' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                        </th>
                                        <th className="py-3 px-4 text-left font-medium text-muted-foreground whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('tax_name')}>
                                            <div className="flex items-center gap-1">Impuesto {sortColumn === 'tax_name' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                        </th>
                                        <th className="py-3 px-4 text-left font-medium text-muted-foreground whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('fiscal_id_label')}>
                                            <div className="flex items-center gap-1">ID Fiscal {sortColumn === 'fiscal_id_label' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                        </th>
                                        <th className="py-3 px-4 text-left font-medium text-muted-foreground whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('timezone')}>
                                            <div className="flex items-center gap-1">Timezone {sortColumn === 'timezone' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                        </th>
                                        <th className="py-3 px-4 text-center font-medium text-muted-foreground whitespace-nowrap cursor-pointer hover:text-foreground transition-colors group" onClick={() => handleSort('is_active')}>
                                            <div className="flex items-center justify-center gap-1">Activo {sortColumn === 'is_active' ? (sortDirection === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />) : <ArrowUpDown className="h-4 w-4 opacity-0 group-hover:opacity-50 transition-opacity" />}</div>
                                        </th>
                                        <th className="py-3 px-4 text-right font-medium text-muted-foreground whitespace-nowrap">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sortedCountries.map((c) => (
                                        <tr key={c.id} className="border-b hover:bg-muted/50 transition-colors">
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <span className="mr-2 text-lg">{getFlagEmoji(c.code)}</span>
                                                <span className="font-medium">{c.name}</span>
                                            </td>
                                            <td className="py-3 px-4 font-mono text-sm whitespace-nowrap">{c.code}</td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                <span className="font-medium">{c.currency_symbol}</span>{' '}
                                                <span className="text-muted-foreground">{c.currency_code}</span>
                                            </td>
                                            <td className="py-3 px-4 font-mono text-sm whitespace-nowrap">{c.phone_code}</td>
                                            <td className="py-3 px-4 whitespace-nowrap">
                                                {c.tax_name} ({c.tax_rate}%)
                                            </td>
                                            <td className="py-3 px-4 whitespace-nowrap">{c.fiscal_id_label}</td>
                                            <td className="py-3 px-4 text-sm text-muted-foreground whitespace-nowrap">{c.timezone}</td>
                                            <td className="py-3 px-4 text-center whitespace-nowrap">
                                                <Badge className={c.is_active ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-500 hover:bg-gray-600'}>
                                                    {c.is_active ? 'Activo' : 'Inactivo'}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 text-right whitespace-nowrap">
                                                {canEdit && (
                                                    <>
                                                        <Button 
                                                            type="button" 
                                                            variant="ghost" 
                                                            size="icon" 
                                                            onClick={() => handleToggle(c.id)}
                                                            title={c.is_active ? 'Desactivar' : 'Activar'}
                                                            className={c.is_active ? 'text-green-600 hover:text-green-700 hover:bg-green-100' : 'text-gray-500 hover:text-gray-600 hover:bg-gray-100'}
                                                        >
                                                            <Power className="h-4 w-4" />
                                                        </Button>
                                                        <Button type="button" variant="ghost" size="icon" onClick={() => handleEdit(c)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    </>
                                                )}
                                                {canDelete && (
                                                    <Button type="button" variant="ghost" size="icon" onClick={() => handleDelete(c.id)}>
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

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editando ? 'Editar' : 'Nuevo'} País
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Nombre del País *</Label>
                                    <Input
                                        value={data.name as string}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Chile"
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
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

                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                        <DialogFooter className="flex flex-col sm:flex-row">
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                                <X className="mr-2 h-4 w-4" /> Cancelar
                            </Button>
                            <Button type="submit" disabled={processing}>
                                <Save className="mr-2 h-4 w-4" />
                                {editando ? 'Actualizar' : 'Crear'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

import { router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Eye,
    EyeOff,
    Pencil,
    Plus,
    Power,
    Save,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { destroy, store, toggle, update } from '@/routes/paises';

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
    tax_rate: number | string;
    fiscal_id_label: string;
    fiscal_id_pattern: string | null;
    date_format: string;
    is_active: boolean;
}
interface CountryFormData extends Omit<
    Country,
    'id' | 'tax_rate' | 'fiscal_id_pattern'
> {
    tax_rate: number;
    fiscal_id_pattern: string;
}
type FieldKey = Exclude<keyof CountryFormData, 'is_active'>;
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
const fields: {
    key: FieldKey;
    label: string;
    placeholder: string;
    type?: 'number';
    maxLength?: number;
    min?: number;
    max?: number;
    step?: string;
    optional?: boolean;
}[] = [
    {
        key: 'code',
        label: 'Código del país (2 letras)',
        placeholder: 'CL',
        maxLength: 2,
    },
    {
        key: 'name',
        label: 'Nombre del país',
        placeholder: 'Chile',
        maxLength: 100,
    },
    {
        key: 'currency_code',
        label: 'Código de moneda',
        placeholder: 'CLP',
        maxLength: 3,
    },
    {
        key: 'currency_symbol',
        label: 'Símbolo',
        placeholder: '$',
        maxLength: 10,
    },
    {
        key: 'currency_decimals',
        label: 'Decimales',
        placeholder: '0',
        type: 'number',
        min: 0,
        max: 4,
        step: '1',
    },
    {
        key: 'date_format',
        label: 'Formato de fecha',
        placeholder: 'DD/MM/YYYY',
        maxLength: 20,
    },
    { key: 'locale', label: 'Locale', placeholder: 'es-CL', maxLength: 10 },
    {
        key: 'timezone',
        label: 'Zona horaria',
        placeholder: 'America/Santiago',
        maxLength: 50,
    },
    {
        key: 'phone_code',
        label: 'Código de teléfono',
        placeholder: '+56',
        maxLength: 5,
    },
    {
        key: 'tax_name',
        label: 'Nombre del impuesto',
        placeholder: 'IVA',
        maxLength: 20,
    },
    {
        key: 'tax_rate',
        label: 'Tasa (%)',
        placeholder: '19',
        type: 'number',
        min: 0,
        max: 100,
        step: '0.01',
    },
    {
        key: 'fiscal_id_label',
        label: 'ID fiscal',
        placeholder: 'RUT',
        maxLength: 20,
    },
    {
        key: 'fiscal_id_pattern',
        label: 'Patrón del ID fiscal',
        placeholder: 'Opcional',
        maxLength: 50,
        optional: true,
    },
];
const columns: { key: keyof Country; title: string }[] = [
    { key: 'name', title: 'País' },
    { key: 'code', title: 'Código' },
    { key: 'currency_code', title: 'Moneda' },
    { key: 'phone_code', title: 'Teléfono' },
    { key: 'tax_name', title: 'Impuesto' },
    { key: 'fiscal_id_label', title: 'ID fiscal' },
    { key: 'timezone', title: 'Zona horaria' },
    { key: 'is_active', title: 'Estado' },
];

export default function CountriesTab({ countries }: { countries: Country[] }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission('admin.countries.create');
    const canEdit = hasPermission('admin.countries.edit');
    const canDelete = hasPermission('admin.countries.delete');
    const [isOpen, setIsOpen] = useState(false);
    const [editando, setEditando] = useState<Country | null>(null);
    const [showOnlyActive, setShowOnlyActive] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [actionError, setActionError] = useState('');
    const [sortColumn, setSortColumn] = useState<keyof Country>('name');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
    const { data, setData, post, put, errors, clearErrors, reset, processing } =
        useForm<CountryFormData>(emptyCountry);
    const activeCount = countries.filter((country) => country.is_active).length;
    const sortedCountries = countries
        .filter((country) => !showOnlyActive || country.is_active)
        .sort((a, b) => {
            const comparison = String(a[sortColumn] ?? '').localeCompare(
                String(b[sortColumn] ?? ''),
                'es',
                { numeric: true, sensitivity: 'base' },
            );
            return sortDirection === 'asc' ? comparison : -comparison;
        });
    const openForm = (country: Country | null) => {
        clearErrors();
        setActionError('');
        setEditando(country);
        setData(
            country
                ? {
                      ...country,
                      tax_rate: Number(country.tax_rate),
                      fiscal_id_pattern: country.fiscal_id_pattern ?? '',
                  }
                : { ...emptyCountry },
        );
        setIsOpen(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        event.stopPropagation();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
        };
        if (editando) put(update.url(editando.id), options);
        else post(store.url(), options);
    };
    const runAction = (country: Country, deleting: boolean) => {
        if (
            busyId !== null ||
            (deleting && !window.confirm(`¿Eliminar el país ${country.name}?`))
        )
            return;
        setActionError('');
        const options = {
            preserveScroll: true,
            onStart: () => setBusyId(country.id),
            onFinish: () => setBusyId(null),
            onError: (messages: Record<string, string>) =>
                setActionError(Object.values(messages).join(' ')),
        };
        if (deleting) router.delete(destroy.url(country.id), options);
        else router.patch(toggle.url(country.id), {}, options);
    };
    const actions = (country: Country) => (
        <div className="flex shrink-0 flex-wrap items-center justify-end gap-1">
            {canEdit && (
                <>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-11"
                        aria-label={`${country.is_active ? 'Desactivar' : 'Activar'} ${country.name}`}
                        disabled={busyId !== null}
                        onClick={() => runAction(country, false)}
                    >
                        <Power
                            className={
                                country.is_active
                                    ? 'size-4 text-emerald-600'
                                    : 'size-4'
                            }
                        />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-11"
                        aria-label={`Editar ${country.name}`}
                        onClick={() => openForm(country)}
                    >
                        <Pencil className="size-4" />
                    </Button>
                </>
            )}
            {canDelete && (
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="size-11 text-destructive"
                    aria-label={`Eliminar ${country.name}`}
                    disabled={busyId !== null}
                    onClick={() => runAction(country, true)}
                >
                    <Trash2 className="size-4" />
                </Button>
            )}
        </div>
    );
    const status = (country: Country) => (
        <Badge
            className={
                country.is_active
                    ? 'bg-emerald-600 text-white'
                    : 'bg-slate-500 text-white'
            }
        >
            {country.is_active ? 'Activo' : 'Inactivo'}
        </Badge>
    );
    const sortBy = (key: keyof Country) => {
        setSortDirection(
            sortColumn === key && sortDirection === 'asc' ? 'desc' : 'asc',
        );
        setSortColumn(key);
    };
    return (
        <div className="@container min-w-0 space-y-4">
            <div className="flex flex-col gap-3 @min-[42rem]:flex-row @min-[42rem]:items-center @min-[42rem]:justify-between">
                <div className="min-w-0">
                    <h1 className="text-2xl font-bold">Países</h1>
                    <p className="text-sm text-muted-foreground">
                        Gestiona los países disponibles para el registro de
                        usuarios.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        aria-pressed={showOnlyActive}
                        onClick={() => setShowOnlyActive(!showOnlyActive)}
                    >
                        {showOnlyActive ? (
                            <EyeOff className="size-4" />
                        ) : (
                            <Eye className="size-4" />
                        )}
                        {showOnlyActive
                            ? `Mostrar todos (${countries.length})`
                            : `Solo activos (${activeCount})`}
                    </Button>
                    {canCreate && (
                        <Button
                            type="button"
                            className="min-h-11"
                            onClick={() => openForm(null)}
                        >
                            <Plus className="size-4" />
                            Nuevo País
                        </Button>
                    )}
                </div>
            </div>
            {actionError && (
                <p
                    role="alert"
                    className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                >
                    {actionError}
                </p>
            )}
            <Card className="min-w-0 gap-4">
                <CardHeader className="px-4 sm:px-6">
                    <CardTitle>Países soportados</CardTitle>
                    <CardDescription>
                        {activeCount} activos de {countries.length} países en
                        total
                    </CardDescription>
                </CardHeader>
                <CardContent className="min-w-0 px-4 sm:px-6">
                    <div className="mb-3 flex flex-wrap items-center gap-2 @min-[64rem]:hidden">
                        <Label htmlFor="country-sort">Ordenar por</Label>
                        <select
                            id="country-sort"
                            className="h-11 max-w-full rounded-md border bg-background px-2 text-sm"
                            value={sortColumn}
                            onChange={(event) =>
                                setSortColumn(
                                    event.target.value as keyof Country,
                                )
                            }
                        >
                            {columns.map((column) => (
                                <option key={column.key} value={column.key}>
                                    {column.title}
                                </option>
                            ))}
                        </select>
                        <Button
                            type="button"
                            variant="outline"
                            className="size-11"
                            size="icon"
                            aria-label="Invertir orden"
                            onClick={() =>
                                setSortDirection(
                                    sortDirection === 'asc' ? 'desc' : 'asc',
                                )
                            }
                        >
                            {sortDirection === 'asc' ? (
                                <ArrowUp className="size-4" />
                            ) : (
                                <ArrowDown className="size-4" />
                            )}
                        </Button>
                    </div>
                    {sortedCountries.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            {showOnlyActive
                                ? 'No hay países activos. Desactiva el filtro para ver todos.'
                                : 'No hay países registrados.'}
                        </p>
                    ) : (
                        <>
                            <div className="grid min-w-0 gap-3 @min-[40rem]:grid-cols-2 @min-[64rem]:hidden">
                                {sortedCountries.map((country) => (
                                    <article
                                        key={country.id}
                                        aria-label={`País ${country.name}`}
                                        className="min-w-0 rounded-lg border p-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <h2 className="min-w-0 font-semibold break-words">
                                                {country.name}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    {country.code}
                                                </span>
                                            </h2>
                                            {status(country)}
                                        </div>
                                        <dl className="mt-3 grid grid-cols-2 gap-3 text-sm">
                                            {[
                                                [
                                                    'Moneda',
                                                    `${country.currency_symbol} ${country.currency_code}`,
                                                ],
                                                [
                                                    'Teléfono',
                                                    country.phone_code,
                                                ],
                                                [
                                                    'Impuesto',
                                                    `${country.tax_name} (${Number(country.tax_rate).toFixed(2)}%)`,
                                                ],
                                                [
                                                    'ID fiscal',
                                                    country.fiscal_id_label,
                                                ],
                                                [
                                                    'Zona horaria',
                                                    country.timezone,
                                                ],
                                                ['Locale', country.locale],
                                            ].map(([label, value]) => (
                                                <div
                                                    key={label}
                                                    className="min-w-0"
                                                >
                                                    <dt className="text-xs text-muted-foreground">
                                                        {label}
                                                    </dt>
                                                    <dd className="break-words">
                                                        {value}
                                                    </dd>
                                                </div>
                                            ))}
                                        </dl>
                                        {actions(country)}
                                    </article>
                                ))}
                            </div>
                            <div className="hidden max-h-[65dvh] overflow-auto rounded-md border @min-[64rem]:block">
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0 z-10 bg-background">
                                        <tr>
                                            {columns.map((column) => (
                                                <th
                                                    key={column.key}
                                                    aria-sort={
                                                        sortColumn ===
                                                        column.key
                                                            ? sortDirection ===
                                                              'asc'
                                                                ? 'ascending'
                                                                : 'descending'
                                                            : 'none'
                                                    }
                                                    className="text-left"
                                                >
                                                    <button
                                                        type="button"
                                                        className="flex min-h-11 items-center gap-1 px-3 text-xs font-medium"
                                                        onClick={() =>
                                                            sortBy(column.key)
                                                        }
                                                    >
                                                        {column.title}
                                                        {sortColumn ===
                                                            column.key &&
                                                            (sortDirection ===
                                                            'asc' ? (
                                                                <ArrowUp className="size-3" />
                                                            ) : (
                                                                <ArrowDown className="size-3" />
                                                            ))}
                                                    </button>
                                                </th>
                                            ))}
                                            <th className="px-3 text-right">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {sortedCountries.map((country) => (
                                            <tr
                                                key={country.id}
                                                className="border-t"
                                            >
                                                <td className="p-3 font-medium">
                                                    {country.name}
                                                </td>
                                                <td className="p-3">
                                                    {country.code}
                                                </td>
                                                <td className="p-3">
                                                    {country.currency_symbol}{' '}
                                                    {country.currency_code}
                                                </td>
                                                <td className="p-3">
                                                    {country.phone_code}
                                                </td>
                                                <td className="p-3">
                                                    {country.tax_name} (
                                                    {Number(
                                                        country.tax_rate,
                                                    ).toFixed(2)}
                                                    %)
                                                </td>
                                                <td className="p-3">
                                                    {country.fiscal_id_label}
                                                </td>
                                                <td className="p-3 text-xs">
                                                    {country.timezone}
                                                </td>
                                                <td className="p-3">
                                                    {status(country)}
                                                </td>
                                                <td className="p-2">
                                                    {actions(country)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>
            <Dialog
                open={isOpen}
                onOpenChange={(open) => {
                    if (!processing) {
                        setIsOpen(open);
                        if (!open) clearErrors();
                    }
                }}
            >
                <DialogContent className="flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl md:max-w-2xl lg:max-w-3xl xl:max-w-3xl">
                    <DialogHeader className="shrink-0 border-b px-4 py-4 pr-14 sm:px-6">
                        <DialogTitle>
                            {editando ? 'Editar País' : 'Nuevo País'}
                        </DialogTitle>
                        <DialogDescription>
                            Completa los datos regionales. Los campos marcados
                            con * son obligatorios.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="flex min-h-0 flex-col">
                        <div className="min-h-0 overflow-y-auto p-4 sm:p-6">
                            {Object.keys(errors).length > 0 && (
                                <p
                                    role="alert"
                                    className="mb-4 text-sm text-destructive"
                                >
                                    Revisa los campos indicados antes de
                                    guardar.
                                </p>
                            )}
                            <div className="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                                {fields.map((field) => (
                                    <div
                                        key={field.key}
                                        className="min-w-0 space-y-2"
                                    >
                                        <Label htmlFor={`country-${field.key}`}>
                                            {field.label}
                                            {!field.optional && ' *'}
                                        </Label>
                                        <Input
                                            id={`country-${field.key}`}
                                            name={field.key}
                                            type={field.type ?? 'text'}
                                            className="h-11 min-w-0"
                                            value={data[field.key]}
                                            placeholder={field.placeholder}
                                            maxLength={field.maxLength}
                                            min={field.min}
                                            max={field.max}
                                            step={field.step}
                                            required={!field.optional}
                                            disabled={
                                                processing ||
                                                (field.key === 'code' &&
                                                    !!editando)
                                            }
                                            aria-invalid={!!errors[field.key]}
                                            aria-describedby={
                                                errors[field.key]
                                                    ? `country-${field.key}-error`
                                                    : undefined
                                            }
                                            onChange={(event) => {
                                                let value: string | number =
                                                    event.target.value;
                                                if (field.type === 'number')
                                                    value = Number(value);
                                                else if (
                                                    field.key === 'code' ||
                                                    field.key ===
                                                        'currency_code'
                                                )
                                                    value = value.toUpperCase();
                                                setData({
                                                    ...data,
                                                    [field.key]: value,
                                                });
                                            }}
                                        />
                                        {errors[field.key] && (
                                            <p
                                                id={`country-${field.key}-error`}
                                                className="text-sm text-destructive"
                                            >
                                                {errors[field.key]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <div className="mt-4 flex min-h-11 items-center gap-3">
                                <input
                                    id="country-is_active"
                                    type="checkbox"
                                    checked={data.is_active}
                                    disabled={processing}
                                    onChange={(event) =>
                                        setData(
                                            'is_active',
                                            event.target.checked,
                                        )
                                    }
                                    className="size-5"
                                />
                                <Label htmlFor="country-is_active">
                                    País activo (visible en registro)
                                </Label>
                            </div>
                        </div>
                        <DialogFooter className="shrink-0 gap-2 border-t bg-background px-4 py-3 sm:px-6">
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11"
                                disabled={processing}
                                onClick={() => setIsOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                className="min-h-11"
                                disabled={processing}
                            >
                                <Save className="size-4" />
                                {processing
                                    ? 'Guardando…'
                                    : editando
                                      ? 'Actualizar'
                                      : 'Crear'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

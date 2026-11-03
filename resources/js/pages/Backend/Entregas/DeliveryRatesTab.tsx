import { useForm, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { formatCurrency } from '@/lib/utils';

export interface DeliveryRate {
    id: number;
    calculation_type: 'comuna' | 'rango_km' | 'valor_fijo_km';
    commune: string | null;
    km_from: number | null;
    km_to: number | null;
    cost: number;
    is_active: boolean;
}

interface DeliveryRatesTabProps {
    rates: DeliveryRate[];
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
}

type RateFormData = {
    calculation_type: DeliveryRate['calculation_type'];
    commune: string;
    km_from: string;
    km_to: string;
    cost: string;
    is_active: boolean;
};

const emptyForm: RateFormData = {
    calculation_type: 'comuna',
    commune: '',
    km_from: '',
    km_to: '',
    cost: '',
    is_active: true,
};

function getRateDescription(rate: DeliveryRate): string {
    if (rate.calculation_type === 'comuna') {
        return rate.commune || 'Comuna';
    }

    if (rate.calculation_type === 'rango_km') {
        return `${rate.km_from} - ${rate.km_to} km`;
    }

    return 'Valor fijo por kilómetro';
}

function getRateTypeLabel(type: DeliveryRate['calculation_type']): string {
    if (type === 'comuna') return 'Por comuna';
    if (type === 'rango_km') return 'Por rango de kilómetros';

    return 'Valor fijo por km';
}

export default function DeliveryRatesTab({
    rates,
    canCreate,
    canEdit,
    canDelete,
}: DeliveryRatesTabProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRate, setEditingRate] = useState<DeliveryRate | null>(null);
    const { data, setData, post, put, processing, errors, reset } =
        useForm<RateFormData>(emptyForm);

    const openCreateDialog = () => {
        setEditingRate(null);
        reset();
        setData(emptyForm);
        setDialogOpen(true);
    };

    const openEditDialog = (rate: DeliveryRate) => {
        setEditingRate(rate);
        setData({
            calculation_type: rate.calculation_type,
            commune: rate.commune || '',
            km_from: rate.km_from?.toString() || '',
            km_to: rate.km_to?.toString() || '',
            cost: rate.cost.toString(),
            is_active: rate.is_active,
        });
        setDialogOpen(true);
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                setEditingRate(null);
                reset();
            },
        };

        if (editingRate) {
            put(`/entregas/tarifas/${editingRate.id}`, options);
        } else {
            post('/entregas/tarifas', options);
        }
    };

    const destroy = (rate: DeliveryRate) => {
        if (confirm(`¿Eliminar la tarifa ${getRateDescription(rate)}?`)) {
            router.delete(`/entregas/tarifas/${rate.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Card>
                <CardHeader className="flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle>Tarifas de Despacho</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Configura los precios que estarán disponibles en la
                            Caja POS.
                        </p>
                    </div>
                    {canCreate && (
                        <Button onClick={openCreateDialog}>
                            <Plus className="mr-2 h-4 w-4" />
                            Nueva tarifa
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    {rates.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            Todavía no hay tarifas de despacho configuradas.
                        </p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {rates.map((rate) => (
                                <div
                                    key={rate.id}
                                    className="flex flex-wrap items-center justify-between gap-4 p-4"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-semibold">
                                                {getRateDescription(rate)}
                                            </span>
                                            <Badge
                                                variant={
                                                    rate.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {rate.is_active
                                                    ? 'Activa'
                                                    : 'Inactiva'}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {getRateTypeLabel(
                                                rate.calculation_type,
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="font-bold">
                                            {formatCurrency(rate.cost)}
                                            {rate.calculation_type ===
                                                'valor_fijo_km' && (
                                                <span className="ml-1 text-sm font-normal text-muted-foreground">
                                                    / km
                                                </span>
                                            )}
                                        </span>
                                        {canEdit && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                aria-label="Editar tarifa"
                                                onClick={() =>
                                                    openEditDialog(rate)
                                                }
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                        )}
                                        {canDelete && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                aria-label="Eliminar tarifa"
                                                onClick={() => destroy(rate)}
                                            >
                                                <Trash2 className="h-4 w-4 text-destructive" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingRate
                                ? 'Editar tarifa de despacho'
                                : 'Nueva tarifa de despacho'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="calculation-type">
                                Tipo de cálculo
                            </Label>
                            <Select
                                value={data.calculation_type}
                                onValueChange={(value) =>
                                    setData(
                                        'calculation_type',
                                        value as DeliveryRate['calculation_type'],
                                    )
                                }
                            >
                                <SelectTrigger id="calculation-type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="comuna">
                                        Por comuna
                                    </SelectItem>
                                    <SelectItem value="rango_km">
                                        Por rango de kilómetros
                                    </SelectItem>
                                    <SelectItem value="valor_fijo_km">
                                        Valor fijo por km
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.calculation_type && (
                                <p className="text-sm text-destructive">
                                    {errors.calculation_type}
                                </p>
                            )}
                        </div>

                        {data.calculation_type === 'comuna' && (
                            <div className="space-y-2">
                                <Label htmlFor="commune">Comuna</Label>
                                <Input
                                    id="commune"
                                    value={data.commune}
                                    onChange={(event) =>
                                        setData('commune', event.target.value)
                                    }
                                    maxLength={120}
                                    required
                                />
                                {errors.commune && (
                                    <p className="text-sm text-destructive">
                                        {errors.commune}
                                    </p>
                                )}
                            </div>
                        )}

                        {data.calculation_type === 'rango_km' && (
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor="km-from">Desde (km)</Label>
                                    <Input
                                        id="km-from"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={data.km_from}
                                        onChange={(event) =>
                                            setData(
                                                'km_from',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {errors.km_from && (
                                        <p className="text-sm text-destructive">
                                            {errors.km_from}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="km-to">Hasta (km)</Label>
                                    <Input
                                        id="km-to"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={data.km_to}
                                        onChange={(event) =>
                                            setData('km_to', event.target.value)
                                        }
                                        required
                                    />
                                    {errors.km_to && (
                                        <p className="text-sm text-destructive">
                                            {errors.km_to}
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}

                        {data.calculation_type === 'valor_fijo_km' && (
                            <p className="text-sm text-muted-foreground">
                                El POS multiplicará este valor por la distancia
                                ingresada al vender.
                            </p>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="delivery-cost">
                                {data.calculation_type === 'valor_fijo_km'
                                    ? 'Costo por kilómetro'
                                    : 'Costo del despacho'}
                            </Label>
                            <Input
                                id="delivery-cost"
                                type="number"
                                min="0"
                                step="1"
                                value={data.cost}
                                onChange={(event) =>
                                    setData('cost', event.target.value)
                                }
                                required
                            />
                            {errors.cost && (
                                <p className="text-sm text-destructive">
                                    {errors.cost}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="rate-active"
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked === true)
                                }
                            />
                            <Label htmlFor="rate-active">Tarifa activa</Label>
                        </div>

                        <DialogFooter>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Guardando...' : 'Guardar tarifa'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

import { Link } from '@inertiajs/react';
import axios from 'axios';
import {
    ExternalLink,
    Info,
    Loader2,
    Save,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { usePermissions } from '@/hooks/use-permissions';

interface WalletSettingsData {
    commission_type: 'percentage' | 'fixed';
    commission_value: number;
    min_sale_amount: number;
    is_active: boolean;
}

export default function WalletSettings() {
    const { hasPermission } = usePermissions();
    const canViewPanel = hasPermission('finanzas.wallets.viewAny');

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [data, setData] = useState<WalletSettingsData>({
        commission_type: 'percentage',
        commission_value: 0,
        min_sale_amount: 0,
        is_active: false,
    });

    useEffect(() => {
        axios
            .get('/configuracion-web/wallet-settings')
            .then((res) => {
                const d = res.data?.data;
                if (d) {
                    setData({
                        commission_type:
                            d.commission_type === 'fixed'
                                ? 'fixed'
                                : 'percentage',
                        commission_value: Number(d.commission_value) || 0,
                        min_sale_amount: Number(d.min_sale_amount) || 0,
                        is_active: Boolean(d.is_active),
                    });
                }
            })
            .catch(() => {
                toast.error('Error al cargar la configuración de billeteras');
            })
            .finally(() => setLoading(false));
    }, []);

    const handleSave = async () => {
        setSaving(true);
        try {
            const res = await axios.put(
                '/configuracion-web/wallet-settings',
                data,
            );

            if (res.data?.data) {
                setData((prev) => ({ ...prev, ...res.data.data }));
            }

            toast.success('Configuración de billeteras guardada correctamente');
        } catch (error: any) {
            const errors = error.response?.data?.errors;
            const firstError = errors
                ? (Object.values(errors)[0] as string[] | undefined)?.[0]
                : null;

            toast.error(
                firstError ||
                    error.response?.data?.message ||
                    'Error al guardar',
            );
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-12">
                <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-xl font-bold">Billeteras y Comisiones</h2>
                    <p className="text-sm text-muted-foreground">
                        Define la comisión general que reciben los cajeros por
                        sus ventas.
                    </p>
                </div>
                <Button
                    onClick={handleSave}
                    disabled={saving}
                    className="gap-2"
                >
                    {saving ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                        <Save className="h-4 w-4" />
                    )}
                    {saving ? 'Guardando...' : 'Guardar Cambios'}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Wallet className="h-5 w-5" /> Comisión general
                    </CardTitle>
                    <CardDescription>
                        Se aplica a las ventas de todos los cajeros que no tengan
                        una regla de comisión específica.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={data.is_active}
                            onCheckedChange={(v) =>
                                setData((prev) => ({ ...prev, is_active: v }))
                            }
                            id="wallet_commission_active"
                        />
                        <Label htmlFor="wallet_commission_active">
                            Activar comisión general
                        </Label>
                    </div>

                    {!data.is_active && (
                        <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            <Info className="mt-0.5 h-4 w-4 shrink-0" />
                            <p>
                                Mientras la comisión general esté desactivada,
                                los cajeros sin regla propia no acumularán
                                comisiones por ventas.
                            </p>
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label>Tipo de comisión</Label>
                            <Select
                                value={data.commission_type}
                                onValueChange={(v) =>
                                    setData((prev) => ({
                                        ...prev,
                                        commission_type: v as
                                            | 'percentage'
                                            | 'fixed',
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue>
                                        {data.commission_type === 'fixed'
                                            ? 'Monto fijo'
                                            : 'Porcentaje'}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percentage">
                                        Porcentaje
                                    </SelectItem>
                                    <SelectItem value="fixed">
                                        Monto fijo
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label>
                                {data.commission_type === 'fixed'
                                    ? 'Monto fijo por venta'
                                    : 'Porcentaje de la venta (%)'}
                            </Label>
                            <Input
                                type="number"
                                min={0}
                                step={0.01}
                                value={data.commission_value}
                                onChange={(e) =>
                                    setData((prev) => ({
                                        ...prev,
                                        commission_value:
                                            parseFloat(e.target.value) || 0,
                                    }))
                                }
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Monto mínimo de venta</Label>
                            <Input
                                type="number"
                                min={0}
                                step={0.01}
                                value={data.min_sale_amount}
                                onChange={(e) =>
                                    setData((prev) => ({
                                        ...prev,
                                        min_sale_amount:
                                            parseFloat(e.target.value) || 0,
                                    }))
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Las ventas por debajo de este monto no generan
                                comisión. Usa 0 para no aplicar mínimo.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Wallet className="h-5 w-5" /> Panel de billeteras
                    </CardTitle>
                    <CardDescription>
                        Recarga saldos, revisa el historial global y configura
                        comisiones específicas por cajero.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {canViewPanel ? (
                        <Button variant="outline" asChild className="gap-2">
                            <Link href="/billeteras">
                                <ExternalLink className="h-4 w-4" />
                                Ir al panel de billeteras
                            </Link>
                        </Button>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No tienes permiso para acceder al panel de
                            billeteras.
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

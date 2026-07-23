import { useEffect, useState } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Loader2, Save, Store, Wallet, RotateCcw, SplitSquareVertical } from 'lucide-react';

export default function Marketplace() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [data, setData] = useState({
        commission_type: 'percentage',
        commission_rate: 0,
        fixed_amount: 0,
        min_commission: null as number | null,
        max_commission: null as number | null,
        min_withdrawal_amount: 0,
        split_payment_active: false,
        split_payment_gateway: 'mercadopago',
        auto_hold_commission: true,
        fund_release_period: 'immediate',
        refund_policy: 'platform_absorbs',
        partial_refunds_allowed: true,
    });

    useEffect(() => {
        axios.get('/configuracion-web/marketplace-settings').then((res) => {
            const d = res.data.data;
            setData((prev) => ({ ...prev, ...d }));
        }).catch(() => {
            toast.error('Error al cargar configuración de marketplace');
        }).finally(() => setLoading(false));
    }, []);

    const handleSave = async () => {
        setSaving(true);
        try {
            await axios.put('/configuracion-web/marketplace-settings', data);
            toast.success('Configuración de Marketplace guardada correctamente');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al guardar');
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

    const commissionTypeLabel = {
        percentage: 'Porcentaje',
        fixed: 'Fijo',
        hybrid: 'Híbrido (Porcentaje + Fijo)',
    }[data.commission_type];

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold">Configuración de Marketplace</h2>
                <Button onClick={handleSave} disabled={saving} className="gap-2">
                    {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                    {saving ? 'Guardando...' : 'Guardar Cambios'}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><Store className="h-5 w-5" /> Comisión de Plataforma</CardTitle>
                    <CardDescription>Configuración de comisiones que cobra la plataforma a los vendedores</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <Label>Tipo de comisión</Label>
                        <Select value={data.commission_type} onValueChange={(v) => setData((prev) => ({ ...prev, commission_type: v }))}>
                            <SelectTrigger className="w-[280px]">
                                <SelectValue>{commissionTypeLabel}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="percentage">Porcentaje</SelectItem>
                                <SelectItem value="fixed">Fijo</SelectItem>
                                <SelectItem value="hybrid">Híbrido (Porcentaje + Fijo)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        {(data.commission_type === 'percentage' || data.commission_type === 'hybrid') && (
                            <div className="space-y-2">
                                <Label>Tasa de comisión (%)</Label>
                                <Input
                                    type="number"
                                    min={0}
                                    max={100}
                                    step={0.01}
                                    value={data.commission_rate}
                                    onChange={(e) => setData((prev) => ({ ...prev, commission_rate: parseFloat(e.target.value) || 0 }))}
                                />
                            </div>
                        )}
                        {(data.commission_type === 'fixed' || data.commission_type === 'hybrid') && (
                            <div className="space-y-2">
                                <Label>Monto fijo</Label>
                                <Input
                                    type="number"
                                    min={0}
                                    step={0.01}
                                    value={data.fixed_amount}
                                    onChange={(e) => setData((prev) => ({ ...prev, fixed_amount: parseFloat(e.target.value) || 0 }))}
                                />
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><Wallet className="h-5 w-5" /> Límites</CardTitle>
                    <CardDescription>Límites de comisiones y montos mínimos</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-3">
                    <div className="space-y-2">
                        <Label>Comisión mínima</Label>
                        <Input
                            type="number"
                            min={0}
                            step={0.01}
                            value={data.min_commission ?? ''}
                            onChange={(e) => setData((prev) => ({ ...prev, min_commission: e.target.value ? parseFloat(e.target.value) : null }))}
                            placeholder="Sin mínimo"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Comisión máxima</Label>
                        <Input
                            type="number"
                            min={0}
                            step={0.01}
                            value={data.max_commission ?? ''}
                            onChange={(e) => setData((prev) => ({ ...prev, max_commission: e.target.value ? parseFloat(e.target.value) : null }))}
                            placeholder="Sin máximo"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Monto mínimo retiro</Label>
                        <Input
                            type="number"
                            min={0}
                            step={0.01}
                            value={data.min_withdrawal_amount}
                            onChange={(e) => setData((prev) => ({ ...prev, min_withdrawal_amount: parseFloat(e.target.value) || 0 }))}
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><SplitSquareVertical className="h-5 w-5" /> Split Payment</CardTitle>
                    <CardDescription>Configuración de pagos divididos entre plataforma y vendedores</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={data.split_payment_active}
                            onCheckedChange={(v) => setData((prev) => ({ ...prev, split_payment_active: v }))}
                            id="split_payment_active"
                        />
                        <Label htmlFor="split_payment_active">Activar split payment</Label>
                    </div>
                    {data.split_payment_active && (
                        <>
                            <div className="space-y-2">
                                <Label>Gateway utilizado</Label>
                                <Select value={data.split_payment_gateway} onValueChange={(v) => setData((prev) => ({ ...prev, split_payment_gateway: v }))}>
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="paypal">PayPal</SelectItem>
                                        <SelectItem value="mercadopago">MercadoPago</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-center gap-2">
                                <Switch
                                    checked={data.auto_hold_commission}
                                    onCheckedChange={(v) => setData((prev) => ({ ...prev, auto_hold_commission: v }))}
                                    id="auto_hold_commission"
                                />
                                <Label htmlFor="auto_hold_commission">Retener comisión automáticamente</Label>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><RotateCcw className="h-5 w-5" /> Liberación de fondos</CardTitle>
                    <CardDescription>Configuración del período de liberación de fondos a vendedores</CardDescription>
                </CardHeader>
                <CardContent className="space-y-2">
                    <Select value={data.fund_release_period} onValueChange={(v) => setData((prev) => ({ ...prev, fund_release_period: v }))}>
                        <SelectTrigger className="w-[250px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="immediate">Inmediata</SelectItem>
                            <SelectItem value="24_hours">24 horas</SelectItem>
                            <SelectItem value="7_days">7 días</SelectItem>
                            <SelectItem value="15_days">15 días</SelectItem>
                            <SelectItem value="30_days">30 días</SelectItem>
                        </SelectContent>
                    </Select>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><RotateCcw className="h-5 w-5" /> Reembolsos</CardTitle>
                    <CardDescription>Política de reembolsos del marketplace</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <Label>Política de reembolso</Label>
                        <Select value={data.refund_policy} onValueChange={(v) => setData((prev) => ({ ...prev, refund_policy: v }))}>
                            <SelectTrigger className="w-[280px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="platform_absorbs">Plataforma absorbe comisión</SelectItem>
                                <SelectItem value="business_absorbs">Negocio absorbe comisión</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={data.partial_refunds_allowed}
                            onCheckedChange={(v) => setData((prev) => ({ ...prev, partial_refunds_allowed: v }))}
                            id="partial_refunds_allowed"
                        />
                        <Label htmlFor="partial_refunds_allowed">Reembolsos parciales permitidos</Label>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

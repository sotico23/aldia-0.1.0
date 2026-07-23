import { useEffect, useState } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Loader2, Save, Globe, CreditCard, FileText } from 'lucide-react';

export default function GlobalPayments() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [data, setData] = useState({
        operation_mode: 'saas',
        default_currency: 'CLP',
        allowed_currencies: ['CLP', 'PEN', 'COP', 'ARS', 'BRL', 'VES', 'USD'],
        default_vat: 0,
        auto_tax: false,
        financial_email: '',
        billing_email: '',
        subscriptions_active: false,
        trial_days: 0,
        grace_days: 0,
        auto_upgrade: false,
        downgrade_allowed: false,
        cancel_non_payment: false,
        auto_renewal: false,
        invoice_prefix: 'FAC-',
        invoice_start_number: 1,
        auto_invoicing: false,
        auto_send_invoices: false,
        auto_reminders: false,
    });
    const [currencyInput, setCurrencyInput] = useState('');

    useEffect(() => {
        axios.get('/configuracion-web/financial-settings').then((res) => {
            const d = res.data.data;
            setData((prev) => ({ ...prev, ...d }));
        }).catch(() => {
            toast.error('Error al cargar configuración financiera');
        }).finally(() => setLoading(false));
    }, []);

    const addCurrency = () => {
        const val = currencyInput.toUpperCase().trim();
        if (val && !data.allowed_currencies.includes(val)) {
            setData((prev) => ({ ...prev, allowed_currencies: [...prev.allowed_currencies, val] }));
            setCurrencyInput('');
        }
    };

    const removeCurrency = (currency: string) => {
        setData((prev) => ({
            ...prev,
            allowed_currencies: prev.allowed_currencies.filter((c) => c !== currency),
        }));
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            await axios.put('/configuracion-web/financial-settings', data);
            toast.success('Configuración financiera guardada correctamente');
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

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold">Configuración de Pagos Globales</h2>
                <Button onClick={handleSave} disabled={saving} className="gap-2">
                    {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                    {saving ? 'Guardando...' : 'Guardar Cambios'}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><Globe className="h-5 w-5" /> Configuración General</CardTitle>
                    <CardDescription>Modo de operación, moneda e impuestos de la plataforma</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <Label>Modo de operación</Label>
                        <Select value={data.operation_mode} onValueChange={(v) => setData((prev) => ({ ...prev, operation_mode: v }))}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="saas">SaaS</SelectItem>
                                <SelectItem value="marketplace">Marketplace</SelectItem>
                                <SelectItem value="both">SaaS + Marketplace</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Moneda principal</Label>
                            <Select value={data.default_currency} onValueChange={(v) => setData((prev) => ({ ...prev, default_currency: v }))}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="CLP">CLP - Peso Chileno ($)</SelectItem>
                                    <SelectItem value="COP">COP - Peso Colombiano ($)</SelectItem>
                                    <SelectItem value="PEN">PEN - Sol Peruano (S/)</SelectItem>
                                    <SelectItem value="ARS">ARS - Peso Argentino ($)</SelectItem>
                                    <SelectItem value="BOB">BOB - Boliviano (Bs)</SelectItem>
                                    <SelectItem value="USD">USD - Dólar ($)</SelectItem>
                                    <SelectItem value="BRL">BRL - Real Brasileño (R$)</SelectItem>
                                    <SelectItem value="VES">VES - Bolívar Venezolano (Bs)</SelectItem>
                                    <SelectItem value="UYU">UYU - Peso Uruguayo ($)</SelectItem>
                                    <SelectItem value="PYG">PYG - Guaraní (₲)</SelectItem>
                                    <SelectItem value="GTQ">GTQ - Quetzal (Q)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>IVA por defecto (%)</Label>
                            <Input
                                type="number"
                                min={0}
                                max={100}
                                step={0.01}
                                value={data.default_vat}
                                onChange={(e) => setData((prev) => ({ ...prev, default_vat: parseFloat(e.target.value) || 0 }))}
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Monedas permitidas</Label>
                        <div className="flex flex-wrap gap-2 mb-2">
                            {data.allowed_currencies.map((currency) => (
                                <span key={currency} className="inline-flex items-center gap-1 rounded-lg bg-primary/10 px-3 py-1 text-sm font-medium text-primary">
                                    {currency}
                                    <button type="button" onClick={() => removeCurrency(currency)} className="text-primary/60 hover:text-primary">&times;</button>
                                </span>
                            ))}
                        </div>
                        <div className="flex gap-2">
                            <Input
                                value={currencyInput}
                                onChange={(e) => setCurrencyInput(e.target.value)}
                                placeholder="Ej: USD"
                                className="max-w-[200px]"
                                onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addCurrency())}
                            />
                            <Button type="button" variant="outline" onClick={addCurrency}>Agregar</Button>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={data.auto_tax}
                            onCheckedChange={(v) => setData((prev) => ({ ...prev, auto_tax: v }))}
                            id="auto_tax"
                        />
                        <Label htmlFor="auto_tax">Activar impuestos automáticos</Label>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Email financiero</Label>
                            <Input
                                type="email"
                                value={data.financial_email}
                                onChange={(e) => setData((prev) => ({ ...prev, financial_email: e.target.value }))}
                                placeholder="finanzas@ejemplo.com"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Email de facturación</Label>
                            <Input
                                type="email"
                                value={data.billing_email}
                                onChange={(e) => setData((prev) => ({ ...prev, billing_email: e.target.value }))}
                                placeholder="facturacion@ejemplo.com"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><CreditCard className="h-5 w-5" /> Suscripciones</CardTitle>
                    <CardDescription>Configuración global de suscripciones y planes de pago</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={data.subscriptions_active}
                            onCheckedChange={(v) => setData((prev) => ({ ...prev, subscriptions_active: v }))}
                            id="subscriptions_active"
                        />
                        <Label htmlFor="subscriptions_active">Activar suscripciones</Label>
                    </div>
                    {data.subscriptions_active && (
                        <>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Días de prueba</Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={365}
                                        value={data.trial_days}
                                        onChange={(e) => setData((prev) => ({ ...prev, trial_days: parseInt(e.target.value) || 0 }))}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Días de gracia</Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={365}
                                        value={data.grace_days}
                                        onChange={(e) => setData((prev) => ({ ...prev, grace_days: parseInt(e.target.value) || 0 }))}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={data.auto_upgrade}
                                        onCheckedChange={(v) => setData((prev) => ({ ...prev, auto_upgrade: v }))}
                                        id="auto_upgrade"
                                    />
                                    <Label htmlFor="auto_upgrade">Upgrade automático</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={data.downgrade_allowed}
                                        onCheckedChange={(v) => setData((prev) => ({ ...prev, downgrade_allowed: v }))}
                                        id="downgrade_allowed"
                                    />
                                    <Label htmlFor="downgrade_allowed">Downgrade permitido</Label>
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={data.cancel_non_payment}
                                        onCheckedChange={(v) => setData((prev) => ({ ...prev, cancel_non_payment: v }))}
                                        id="cancel_non_payment"
                                    />
                                    <Label htmlFor="cancel_non_payment">Cancelar por falta de pago</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={data.auto_renewal}
                                        onCheckedChange={(v) => setData((prev) => ({ ...prev, auto_renewal: v }))}
                                        id="auto_renewal"
                                    />
                                    <Label htmlFor="auto_renewal">Renovación automática</Label>
                                </div>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><FileText className="h-5 w-5" /> Facturación</CardTitle>
                    <CardDescription>Configuración de emisión de facturas y recordatorios</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Prefijo de factura</Label>
                            <Input
                                value={data.invoice_prefix}
                                onChange={(e) => setData((prev) => ({ ...prev, invoice_prefix: e.target.value }))}
                                placeholder="FAC-"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Número inicial</Label>
                            <Input
                                type="number"
                                min={1}
                                value={data.invoice_start_number}
                                onChange={(e) => setData((prev) => ({ ...prev, invoice_start_number: parseInt(e.target.value) || 1 }))}
                            />
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={data.auto_invoicing}
                            onCheckedChange={(v) => setData((prev) => ({ ...prev, auto_invoicing: v }))}
                            id="auto_invoicing"
                        />
                        <Label htmlFor="auto_invoicing">Facturación automática</Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={data.auto_send_invoices}
                            onCheckedChange={(v) => setData((prev) => ({ ...prev, auto_send_invoices: v }))}
                            id="auto_send_invoices"
                        />
                        <Label htmlFor="auto_send_invoices">Envío automático de facturas</Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={data.auto_reminders}
                            onCheckedChange={(v) => setData((prev) => ({ ...prev, auto_reminders: v }))}
                            id="auto_reminders"
                        />
                        <Label htmlFor="auto_reminders">Recordatorios automáticos</Label>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

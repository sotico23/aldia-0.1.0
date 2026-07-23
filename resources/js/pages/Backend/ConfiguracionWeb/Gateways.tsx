import { useEffect, useState } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Loader2, Save, RefreshCw, ChevronDown, ChevronRight, ExternalLink, CreditCard } from 'lucide-react';

export default function Gateways() {
    const [loading, setLoading] = useState(true);
    const [expanded, setExpanded] = useState<string | null>('webpay');

    const [webpay, setWebpay] = useState({
        commerce_code: '',
        api_key: '',
        environment: 'integration',
        is_active: false,
    });
    const [webpaySaving, setWebpaySaving] = useState(false);

    const [paypal, setPaypal] = useState({
        paypal_client_id: '',
        paypal_client_secret: '',
        paypal_mode: 'sandbox',
        paypal_active: false,
        paypal_webhook_id: '',
    });
    const [paypalSaving, setPaypalSaving] = useState(false);

    const [mercadopago, setMercadopago] = useState({
        mercadopago_public_key: '',
        mercadopago_access_token: '',
        mercadopago_mode: 'sandbox',
        mercadopago_active: false,
        mercadopago_webhook_secret: '',
    });
    const [mercadopagoSaving, setMercadopagoSaving] = useState(false);

    useEffect(() => {
        axios.get('/configuracion-web/gateway-settings').then((res) => {
            const d = res.data.data;
            setWebpay((prev) => ({ ...prev, ...d.webpay }));
            setPaypal((prev) => ({ ...prev, ...d.paypal }));
            setMercadopago((prev) => ({ ...prev, ...d.mercadopago }));
        }).catch(() => {
            toast.error('Error al cargar configuración de gateways');
        }).finally(() => setLoading(false));
    }, []);

    const saveWebpay = async () => {
        setWebpaySaving(true);
        try {
            const res = await axios.put('/configuracion-web/gateway-settings/webpay', webpay);
            toast.success(res.data.message || 'Configuración guardada');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al guardar');
        } finally {
            setWebpaySaving(false);
        }
    };

    const savePaypal = async () => {
        setPaypalSaving(true);
        try {
            const res = await axios.put('/configuracion-web/gateway-settings/paypal', paypal);
            toast.success(res.data.message || 'Configuración guardada');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al guardar');
        } finally {
            setPaypalSaving(false);
        }
    };

    const saveMercadopago = async () => {
        setMercadopagoSaving(true);
        try {
            const res = await axios.put('/configuracion-web/gateway-settings/mercadopago', mercadopago);
            toast.success(res.data.message || 'Configuración guardada');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al guardar');
        } finally {
            setMercadopagoSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-12">
                <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
        );
    }

    const Accordion = ({ id, title, icon, children, defaultOpen }: { id: string; title: string; icon: React.ReactNode; children: React.ReactNode; defaultOpen?: boolean }) => (
        <Card className="overflow-hidden">
            <button
                type="button"
                onClick={() => setExpanded(expanded === id ? null : id)}
                className="flex w-full items-center justify-between p-6 hover:bg-muted/30 transition-colors"
            >
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        {icon}
                    </div>
                    <div className="text-left">
                        <CardTitle className="text-lg">{title}</CardTitle>
                    </div>
                </div>
                {expanded === id ? <ChevronDown className="h-5 w-5 text-muted-foreground" /> : <ChevronRight className="h-5 w-5 text-muted-foreground" />}
            </button>
            {expanded === id && (
                <div className="border-t px-6 pb-6">
                    {children}
                </div>
            )}
        </Card>
    );

    return (
        <div className="space-y-4">
            {/* Webpay */}
            <Accordion id="webpay" title="Webpay Plus (Transbank)" icon={<CreditCard className="h-5 w-5" />} defaultOpen>
                <div className="space-y-4 pt-4">
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={webpay.is_active}
                            onCheckedChange={(v) => setWebpay((prev) => ({ ...prev, is_active: v }))}
                            id="webpay_active"
                        />
                        <Label htmlFor="webpay_active">Activado</Label>
                    </div>
                    <div className="flex items-center gap-4">
                        <Label>Ambiente</Label>
                        <Select value={webpay.environment} onValueChange={(v) => setWebpay((prev) => ({ ...prev, environment: v }))}>
                            <SelectTrigger className="w-[180px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="integration">Sandbox / Integración</SelectItem>
                                <SelectItem value="production">Producción</SelectItem>
                            </SelectContent>
                        </Select>
                        <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${webpay.environment === 'production' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'}`}>
                            {webpay.environment === 'production' ? 'Producción' : 'Sandbox'}
                        </span>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Commerce Code</Label>
                            <Input value={webpay.commerce_code} onChange={(e) => setWebpay((prev) => ({ ...prev, commerce_code: e.target.value }))} placeholder="597055555532" />
                        </div>
                        <div className="space-y-2">
                            <Label>API Key</Label>
                            <Input type="password" value={webpay.api_key} onChange={(e) => setWebpay((prev) => ({ ...prev, api_key: e.target.value }))} placeholder={webpay.api_key ? '••••••••••••••••' : 'Ingresa API Key'} />
                            <p className="text-xs text-muted-foreground">Se almacena cifrada. Dejar vacío para mantener la existente.</p>
                        </div>
                    </div>
                    <Button onClick={saveWebpay} disabled={webpaySaving} className="gap-2">
                        {webpaySaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                        Guardar Configuración Webpay
                    </Button>
                </div>
            </Accordion>

            {/* PayPal */}
            <Accordion id="paypal" title="PayPal" icon={<span className="text-lg">💳</span>}>
                <div className="space-y-4 pt-4">
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={paypal.paypal_active}
                            onCheckedChange={(v) => setPaypal((prev) => ({ ...prev, paypal_active: v }))}
                            id="paypal_active"
                        />
                        <Label htmlFor="paypal_active">Activado</Label>
                    </div>
                    <div className="flex items-center gap-4">
                        <Label>Ambiente</Label>
                        <Select value={paypal.paypal_mode} onValueChange={(v) => setPaypal((prev) => ({ ...prev, paypal_mode: v }))}>
                            <SelectTrigger className="w-[180px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="sandbox">Sandbox</SelectItem>
                                <SelectItem value="live">Producción (Live)</SelectItem>
                            </SelectContent>
                        </Select>
                        <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${paypal.paypal_mode === 'live' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'}`}>
                            {paypal.paypal_mode === 'live' ? 'Producción' : 'Sandbox'}
                        </span>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Client ID</Label>
                            <Input value={paypal.paypal_client_id} onChange={(e) => setPaypal((prev) => ({ ...prev, paypal_client_id: e.target.value }))} placeholder="AeBxP8Dq..." />
                        </div>
                        <div className="space-y-2">
                            <Label>Client Secret</Label>
                            <Input type="password" value={paypal.paypal_client_secret} onChange={(e) => setPaypal((prev) => ({ ...prev, paypal_client_secret: e.target.value }))} placeholder={paypal.paypal_client_secret ? '••••••••••••••••' : 'Ingresa Client Secret'} />
                            <p className="text-xs text-muted-foreground">Se almacena cifrado. Dejar vacío para mantener el existente.</p>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Webhook ID</Label>
                        <Input type="password" value={paypal.paypal_webhook_id} onChange={(e) => setPaypal((prev) => ({ ...prev, paypal_webhook_id: e.target.value }))} placeholder={paypal.paypal_webhook_id ? '••••••••••••••••' : 'Webhook ID'} />
                    </div>
                    <Button onClick={savePaypal} disabled={paypalSaving} className="gap-2">
                        {paypalSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                        Guardar Configuración PayPal
                    </Button>
                </div>
            </Accordion>

            {/* MercadoPago */}
            <Accordion id="mercadopago" title="MercadoPago" icon={<span className="text-lg">🟡</span>}>
                <div className="space-y-4 pt-4">
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={mercadopago.mercadopago_active}
                            onCheckedChange={(v) => setMercadopago((prev) => ({ ...prev, mercadopago_active: v }))}
                            id="mp_active"
                        />
                        <Label htmlFor="mp_active">Activado</Label>
                    </div>
                    <div className="flex items-center gap-4">
                        <Label>Ambiente</Label>
                        <Select value={mercadopago.mercadopago_mode} onValueChange={(v) => setMercadopago((prev) => ({ ...prev, mercadopago_mode: v }))}>
                            <SelectTrigger className="w-[180px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="sandbox">Sandbox</SelectItem>
                                <SelectItem value="production">Producción</SelectItem>
                            </SelectContent>
                        </Select>
                        <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${mercadopago.mercadopago_mode === 'production' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'}`}>
                            {mercadopago.mercadopago_mode === 'production' ? 'Producción' : 'Sandbox'}
                        </span>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Public Key</Label>
                            <Input value={mercadopago.mercadopago_public_key} onChange={(e) => setMercadopago((prev) => ({ ...prev, mercadopago_public_key: e.target.value }))} placeholder="APP_USU-xxxx" />
                        </div>
                        <div className="space-y-2">
                            <Label>Access Token</Label>
                            <Input type="password" value={mercadopago.mercadopago_access_token} onChange={(e) => setMercadopago((prev) => ({ ...prev, mercadopago_access_token: e.target.value }))} placeholder={mercadopago.mercadopago_access_token ? '••••••••••••••••' : 'Ingresa Access Token'} />
                            <p className="text-xs text-muted-foreground">Se almacena cifrado. Dejar vacío para mantener el existente.</p>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Webhook Secret</Label>
                        <Input type="password" value={mercadopago.mercadopago_webhook_secret} onChange={(e) => setMercadopago((prev) => ({ ...prev, mercadopago_webhook_secret: e.target.value }))} placeholder={mercadopago.mercadopago_webhook_secret ? '••••••••••••••••' : 'Webhook Secret'} />
                    </div>
                    <Button onClick={saveMercadopago} disabled={mercadopagoSaving} className="gap-2">
                        {mercadopagoSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                        Guardar Configuración MercadoPago
                    </Button>
                </div>
            </Accordion>
        </div>
    );
}

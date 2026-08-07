import axios from 'axios';
import { Loader2, Save, Zap, Bot, MessageSquare, Mail, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

interface AutomationEvent {
    event: string;
    label: string;
    n8n: boolean;
    telegram: boolean;
    whatsapp: boolean;
    email: boolean;
}

export default function FinancialAutomations() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [events, setEvents] = useState<AutomationEvent[]>([]);

    useEffect(() => {
        axios.get('/configuracion-web/financial-automations').then((res) => {
            setEvents(res.data.data);
        }).catch(() => {
            toast.error('Error al cargar automatizaciones financieras');
        }).finally(() => setLoading(false));
    }, []);

    const toggleEvent = (index: number, channel: keyof AutomationEvent) => {
        setEvents((prev) =>
            prev.map((evt, i) => (i === index ? { ...evt, [channel]: !evt[channel] } : evt))
        );
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            await axios.put('/configuracion-web/financial-automations', { events });
            toast.success('Automatizaciones financieras guardadas correctamente');
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

    const channelIcon: Record<string, React.ReactNode> = {
        n8n: <Bot className="h-4 w-4" />,
        telegram: <MessageSquare className="h-4 w-4" />,
        whatsapp: <Smartphone className="h-4 w-4" />,
        email: <Mail className="h-4 w-4" />,
    };

    const channelLabel: Record<string, string> = {
        n8n: 'n8n',
        telegram: 'Telegram',
        whatsapp: 'WhatsApp',
        email: 'Email',
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-bold">Automatizaciones Financieras</h2>
                    <p className="text-muted-foreground">Configura qué eventos financieros activan automatizaciones</p>
                </div>
                <Button onClick={handleSave} disabled={saving} className="gap-2">
                    {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                    {saving ? 'Guardando...' : 'Guardar Cambios'}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2"><Zap className="h-5 w-5" /> Eventos Financieros</CardTitle>
                    <CardDescription>Activa o desactiva canales de notificación para cada evento</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="pb-3 text-sm font-medium text-muted-foreground">Evento</th>
                                    <th className="pb-3 text-center text-sm font-medium text-muted-foreground">
                                        <div className="flex items-center justify-center gap-1"><Bot className="h-4 w-4" /> n8n</div>
                                    </th>
                                    <th className="pb-3 text-center text-sm font-medium text-muted-foreground">
                                        <div className="flex items-center justify-center gap-1"><MessageSquare className="h-4 w-4" /> Telegram</div>
                                    </th>
                                    <th className="pb-3 text-center text-sm font-medium text-muted-foreground">
                                        <div className="flex items-center justify-center gap-1"><Smartphone className="h-4 w-4" /> WhatsApp</div>
                                    </th>
                                    <th className="pb-3 text-center text-sm font-medium text-muted-foreground">
                                        <div className="flex items-center justify-center gap-1"><Mail className="h-4 w-4" /> Email</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.map((event, index) => (
                                    <tr key={event.event} className="border-b last:border-0">
                                        <td className="py-3 pr-4">
                                            <span className="font-medium">{event.label}</span>
                                        </td>
                                        {(['n8n', 'telegram', 'whatsapp', 'email'] as const).map((channel) => (
                                            <td key={channel} className="py-3 text-center">
                                                <Switch
                                                    checked={event[channel]}
                                                    onCheckedChange={() => toggleEvent(index, channel)}
                                                    id={`${event.event}-${channel}`}
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

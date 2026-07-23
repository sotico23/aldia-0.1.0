import { router } from '@inertiajs/react';
import { Settings, Play, Save, Bot, MessageCircle, Smartphone } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Separator } from '@/components/ui/separator';

const REPORT_OPTIONS = [
    { key: 'resumen_ejecutivo', label: 'Resumen Ejecutivo' },
    { key: 'ventas', label: 'Ventas' },
    { key: 'inventario', label: 'Inventario' },
    { key: 'stock_bajo', label: 'Productos con Stock Bajo' },
    { key: 'clientes_nuevos', label: 'Clientes Nuevos' },
    { key: 'clientes_inactivos', label: 'Clientes Inactivos' },
    { key: 'agenda_citas', label: 'Agenda y Citas' },
    { key: 'gastos', label: 'Gastos' },
    { key: 'flujo_caja', label: 'Flujo de Caja' },
    { key: 'ctas_cobrar', label: 'Cuentas por Cobrar' },
    { key: 'ctas_pagar', label: 'Cuentas por Pagar' },
];

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const CHANNEL_ICONS: Record<string, React.ReactNode> = {
    telegram: <Bot className="h-4 w-4" />,
    whatsapp: <MessageCircle className="h-4 w-4" />,
    both: <Smartphone className="h-4 w-4" />,
};

export default function AutomationConfigDialog({
    automation,
}: {
    automation: {
        channel: string;
        frequency: string;
        execution_time: string;
        enabled: boolean;
        selected_reports: string[];
        last_run_at: string | null;
        next_run_at: string | null;
        last_run_status: string | null;
    } | null;
}) {
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const [channel, setChannel] = useState(automation?.channel ?? 'telegram');
    const [frequency, setFrequency] = useState(automation?.frequency ?? 'daily');
    const [executionTime, setExecutionTime] = useState(automation?.execution_time ?? '08:00');
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [enabled, setEnabled] = useState(automation?.enabled ?? false);
    const [selectedReports, setSelectedReports] = useState<string[]>(
        automation?.selected_reports ?? [],
    );

    const hasChanged =
        channel !== (automation?.channel ?? 'telegram') ||
        frequency !== (automation?.frequency ?? 'daily') ||
        executionTime !== (automation?.execution_time ?? '08:00') ||
        enabled !== (automation?.enabled ?? false) ||
        JSON.stringify(selectedReports.sort()) !==
            JSON.stringify((automation?.selected_reports ?? []).sort());

    const toggleReport = (key: string) => {
        setSelectedReports((prev) =>
            prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key],
        );
    };

    const handleSave = async () => {
        setSaving(true);
        router.post(
            '/canales/automation',
            {
                channel,
                frequency,
                execution_time: executionTime,
                enabled,
                selected_reports: selectedReports,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Configuración guardada correctamente.');
                    setOpen(false);
                    router.reload({ only: ['automation'] });
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    toast.error(firstError || 'Error al guardar la configuración.');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const handleRunTest = async () => {
        if (selectedReports.length === 0) {
            toast.error('Selecciona al menos un reporte para ejecutar.');
            return;
        }

        setTesting(true);
        try {
            const csrfToken =
                document.head
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') || '';

            const resp = await fetch('/canales/automation/test', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    channel,
                    frequency,
                    execution_time: executionTime,
                    selected_reports: selectedReports,
                }),
            });

            const data = await resp.json();

            if (data.success) {
                toast.success(data.message || 'Workflow ejecutado correctamente.');
                router.reload({ only: ['automation'] });
            } else {
                toast.error(data.message || 'Error al ejecutar el workflow.');
            }
        } catch {
            toast.error('Error de conexión al ejecutar el workflow.');
        } finally {
            setTesting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="default" size="lg" className="gap-2">
                    <Settings className="h-5 w-5" />
                    Configurar Automatizaciones
                </Button>
            </DialogTrigger>
            <DialogContent className="mx-4 w-full max-w-full sm:mx-auto sm:max-w-xl p-4 sm:p-6 max-h-[90vh] overflow-y-auto">
                <DialogHeader className="mb-2">
                    <DialogTitle className="text-xl">Configuración de Automatizaciones</DialogTitle>
                    <DialogDescription>
                        Selecciona qué información deseas recibir automáticamente en tus canales
                        conectados.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    <div className="space-y-3">
                        <Label className="text-sm font-medium">Canal de envío</Label>
                        <RadioGroup
                            value={channel}
                            onValueChange={setChannel}
                            className="flex flex-wrap gap-3"
                        >
                            {[
                                { value: 'telegram', icon: <Bot className="h-4 w-4" />, label: 'Telegram' },
                                { value: 'whatsapp', icon: <MessageCircle className="h-4 w-4" />, label: 'WhatsApp' },
                                { value: 'both', icon: <Smartphone className="h-4 w-4" />, label: 'Ambos' },
                            ].map((opt) => (
                                <Label
                                    key={opt.value}
                                    className={`flex flex-1 cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm font-medium transition-colors hover:bg-accent ${
                                        channel === opt.value
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border'
                                    }`}
                                >
                                    <RadioGroupItem value={opt.value} className="sr-only" />
                                    {opt.icon}
                                    {opt.label}
                                </Label>
                            ))}
                        </RadioGroup>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-3">
                            <Label className="text-sm font-medium">Frecuencia</Label>
                            <RadioGroup
                                value={frequency}
                                onValueChange={setFrequency}
                                className="flex flex-col gap-2"
                            >
                                {[
                                    { value: 'daily', label: 'Diario' },
                                    { value: 'weekly', label: 'Semanal' },
                                    { value: 'monthly', label: 'Mensual' },
                                ].map((opt) => (
                                    <Label
                                        key={opt.value}
                                        className={`flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors hover:bg-accent ${
                                            frequency === opt.value
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border'
                                        }`}
                                    >
                                        <RadioGroupItem value={opt.value} className="sr-only" />
                                        {opt.label}
                                    </Label>
                                ))}
                            </RadioGroup>
                        </div>

                        <div className="space-y-3">
                            <Label htmlFor="execution_time" className="text-sm font-medium">
                                Hora de ejecución
                            </Label>
                            <Input
                                id="execution_time"
                                type="time"
                                value={executionTime}
                                onChange={(e) => setExecutionTime(e.target.value)}
                                className="text-base"
                            />
                        </div>
                    </div>

                    <Separator />

                    <div className="space-y-3">
                        <Label className="text-sm font-medium">Reportes disponibles</Label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {REPORT_OPTIONS.map((report) => (
                                <Label
                                    key={report.key}
                                    className="flex cursor-pointer items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium transition-colors hover:bg-accent"
                                >
                                    <Checkbox
                                        checked={selectedReports.includes(report.key)}
                                        onCheckedChange={() => toggleReport(report.key)}
                                    />
                                    {report.label}
                                </Label>
                            ))}
                        </div>
                    </div>

                    {automation?.last_run_at && (
                        <div className="rounded-lg border border-border/50 bg-muted/30 p-3 text-sm">
                            <div className="flex items-center gap-2 font-medium">
                                <span
                                    className={`inline-block h-2 w-2 rounded-full ${
                                        automation.enabled ? 'bg-emerald-500' : 'bg-muted-foreground'
                                    }`}
                                />
                                {automation.enabled ? 'Activo' : 'Inactivo'}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Última ejecución: {automation.last_run_at}
                            </p>
                            {automation.next_run_at && (
                                <p className="text-xs text-muted-foreground">
                                    Próxima ejecución: {automation.next_run_at}
                                </p>
                            )}
                            {automation.last_run_status && (
                                <p className="mt-0.5 text-xs">
                                    Estado:{' '}
                                    <span
                                        className={
                                            automation.last_run_status === 'success'
                                                ? 'text-emerald-600'
                                                : 'text-destructive'
                                        }
                                    >
                                        {automation.last_run_status === 'success'
                                            ? 'Exitosa'
                                            : 'Error'}
                                    </span>
                                </p>
                            )}
                        </div>
                    )}

                    <Separator />

                    <div className="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleRunTest}
                            disabled={testing}
                            className="gap-2"
                        >
                            {testing ? (
                                <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                            ) : (
                                <Play className="h-4 w-4" />
                            )}
                            {testing ? 'Ejecutando...' : 'Ejecutar Ahora'}
                        </Button>
                        <Button
                            type="button"
                            onClick={handleSave}
                            disabled={saving || (!hasChanged && automation !== null)}
                            className="gap-2"
                        >
                            <Save className="h-4 w-4" />
                            {saving ? 'Guardando...' : 'Guardar Configuración'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

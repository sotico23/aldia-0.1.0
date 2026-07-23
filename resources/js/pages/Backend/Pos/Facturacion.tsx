import { Head, router } from '@inertiajs/react';
import {
    FileText,
    Send,
    RefreshCw,
    Smartphone,
    Monitor,
    CheckCircle2,
    Download,
    Upload,
    FileSpreadsheet,
    FileJson,
} from 'lucide-react';
import { useRef } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table-pos';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';

interface Documento {
    id: number;
    numero_factura: string | null;
    tipo_documento: string;
    total: number;
    estado: string;
    created_at: string;
    cliente?: {
        nombre: string;
        rut: string | null;
    };
}

export default function Facturacion({
    documentos,
}: {
    documentos: Documento[];
}) {
    const { hasPermission } = usePermissions();
    const canAccess = hasPermission('ventas.pos.viewAny');
    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);

    const handleImportCSV = () => {
        csvInputRef.current?.click();
    };

    const handleImportExcel = () => {
        excelInputRef.current?.click();
    };

    const handleFileChange = (
        e: React.ChangeEvent<HTMLInputElement>,
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        type: 'csv' | 'excel',
    ) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        router.post('/pos/facturacion/importar', formData, {
            forceFormData: true,
            onSuccess: () => {
                e.target.value = '';
            },
        });
    };

    const countTipo = (tipo: string) =>
        documentos.filter((d) => d.tipo_documento === tipo).length;

    if (!canAccess) {
        return (
            <AppLayout
                breadcrumbs={[
                    { title: 'Dashboard', href: '/dashboard' },
                    { title: 'Caja POS', href: '/pos' },
                ]}
            >
                <div className="flex items-center justify-center py-12">
                    <p className="text-muted-foreground">No tienes permiso para acceder a esta página.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Caja POS', href: '/pos' },
                { title: 'Facturación y N.C', href: '/pos/facturacion' },
            ]}
        >
            <Head title="Facturación Electrónica" />

            <div className="space-y-4 p-4 sm:p-6 md:p-8">
                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            FACTURACIÓN DTE INTEGRADA
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Control de envíos al SII y emisión de documentos
                            legales
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.location.reload()}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" /> Recargar SII
                        </Button>
                        <Button
                            size="sm"
                            className="bg-primary shadow-lg shadow-primary/20"
                        >
                            <FileText className="mr-2 h-4 w-4" /> Nueva Factura
                            Manual
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9 gap-2 rounded-xl border-muted-foreground/10 font-bold"
                                >
                                    <Download className="h-4 w-4 text-primary" />
                                    <span>Herramientas</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem onClick={handleImportCSV}>
                                    <Upload className="mr-2 h-4 w-4" />
                                    Importar CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={handleImportExcel}>
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Importar Excel
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() =>
                                        router.get(
                                            '/pos/facturacion/exportar?format=json',
                                        )
                                    }
                                >
                                    <FileJson className="mr-2 h-4 w-4" />
                                    Exportar JSON
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() =>
                                        router.get(
                                            '/pos/facturacion/exportar?format=excel',
                                        )
                                    }
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Exportar Excel
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card className="border bg-background shadow-none">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                BOLETAS GENERADAS
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black">
                                {countTipo('boleta')} DTE
                            </div>
                            <div className="mt-1 flex items-center text-[10px] font-bold text-emerald-600">
                                <CheckCircle2 className="mr-1 h-3 w-3" />{' '}
                                Sincronización Exitosa
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border bg-background shadow-none">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                FACTURAS GENERADAS
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black">
                                {countTipo('factura')} DTE
                            </div>
                            <div className="mt-1 flex items-center text-[10px] font-bold text-blue-600">
                                <Monitor className="mr-1 h-3 w-3" /> Lista para
                                Impresión
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border bg-background shadow-none">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-muted-foreground uppercase">
                                NOTAS DE CRÉDITO
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-destructive">
                                {countTipo('nota_credito')} emitidas
                            </div>
                            <div className="mt-1 text-[10px] text-muted-foreground">
                                Procesadas este mes
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-none bg-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-xs font-bold text-indigo-200 uppercase">
                                TIEMPO DE RESPUESTA SII
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black">Fast Mode</div>
                            <div className="mt-1 flex items-center text-[10px] text-indigo-200">
                                <Smartphone className="mr-1 h-3 w-3" />{' '}
                                Latencia: 1.2s promedio
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="border shadow-none">
                    <CardHeader className="pb-4">
                        <CardTitle className="text-lg font-bold">
                            Registro de Documentos Tributarios (Historial)
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Monitoreo en tiempo real de los folios enviados y
                            aceptados.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader className="bg-slate-50">
                                    <TableRow>
                                        <TableHead className="font-bold">
                                            Folio
                                        </TableHead>
                                        <TableHead className="font-bold">
                                            Tipo Documento
                                        </TableHead>
                                        <TableHead className="font-bold">
                                            Cliente / RUT
                                        </TableHead>
                                        <TableHead className="font-bold">
                                            Monto Total
                                        </TableHead>
                                        <TableHead className="font-bold">
                                            Estado
                                        </TableHead>
                                        <TableHead className="text-right font-bold">
                                            Operaciones
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {documentos.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="h-32 text-center text-muted-foreground"
                                            >
                                                No hay documentos emitidos
                                                todavía.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        documentos.map((doc) => (
                                            <TableRow
                                                key={doc.id}
                                                className="hover:bg-slate-50/50"
                                            >
                                                <TableCell className="font-mono text-xs font-bold">
                                                    #
                                                    {doc.numero_factura ||
                                                        doc.id}
                                                </TableCell>
                                                <TableCell className="text-xs font-medium uppercase">
                                                    {doc.tipo_documento}
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    <div className="font-bold">
                                                        {doc.cliente?.nombre ||
                                                            'Particular'}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {doc.cliente?.rut ||
                                                            'Sin RUT'}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-xs font-black">
                                                    {formatCurrency(
                                                        doc.total,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className="h-5 rounded-full bg-emerald-500 px-2 text-[10px]">
                                                        Aceptado SII
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 py-0 text-[10px]"
                                                        >
                                                            PDF
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 py-0 text-[10px] text-destructive"
                                                        >
                                                            Anular
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0"
                                                        >
                                                            <Send className="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <input
                ref={csvInputRef}
                type="file"
                accept=".csv"
                className="hidden"
                onChange={(e) => handleFileChange(e, 'csv')}
            />
            <input
                ref={excelInputRef}
                type="file"
                accept=".xlsx,.xls"
                className="hidden"
                onChange={(e) => handleFileChange(e, 'excel')}
            />
        </AppLayout>
    );
}

import { router } from '@inertiajs/react';
import { Download, Upload, FileSpreadsheet, FileText, ChevronDown } from 'lucide-react';
import React, { useRef } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface BulkActionsProps {
    baseUrl: string;
    filters?: Record<string, any>;
    onImportSuccess?: () => void;
    modelName?: string;
    canImport?: boolean;
    canExport?: boolean;
}

export const BulkActions: React.FC<BulkActionsProps> = ({
    baseUrl,
    filters = {},
    onImportSuccess,
    modelName = 'Registros',
    canImport = true,
    canExport = true,
}) => {
    const csvInputRef = useRef<HTMLInputElement>(null);
    const excelInputRef = useRef<HTMLInputElement>(null);

    const handleExport = (type: 'csv' | 'excel') => {
        const exportUrl = type === 'csv' ? `${baseUrl}/export` : `${baseUrl}/export-excel`;
        
        // Clean filters: remove empty or undefined values
        const cleanFilters = Object.entries(filters).reduce((acc, [key, value]) => {
            if (value !== undefined && value !== null && value !== '' && value !== 'all') {
                acc[key] = value;
            }
            return acc;
        }, {} as Record<string, any>);

        const params = new URLSearchParams(cleanFilters as any);
        const urlWithParams = params.toString() ? `${exportUrl}?${params.toString()}` : exportUrl;
        
        window.location.href = urlWithParams;
        toast.success(`Iniciando exportación de ${modelName}...`);
    };

    const handleImport = (e: React.ChangeEvent<HTMLInputElement>, type: 'csv' | 'excel') => {
        const file = e.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('archivo', file);

        const importUrl = type === 'csv' ? `${baseUrl}/import` : `${baseUrl}/import-excel`;

        toast.info(`Procesando archivo...`);

        router.post(importUrl, formData, {
            onSuccess: () => {
                toast.success(`${modelName} importados correctamente`);
                if (onImportSuccess) onImportSuccess();
                // Reset inputs
                if (csvInputRef.current) csvInputRef.current.value = '';
                if (excelInputRef.current) excelInputRef.current.value = '';
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(`Error al importar: ${firstError}`);
            }
        });
    };

    return (
        <div className="flex items-center gap-2">
            {/* Hidden form inputs */}
            <input
                type="file"
                ref={csvInputRef}
                className="hidden"
                accept=".csv"
                onChange={(e) => handleImport(e, 'csv')}
            />
            <input
                type="file"
                ref={excelInputRef}
                className="hidden"
                accept=".xlsx,.xls"
                onChange={(e) => handleImport(e, 'excel')}
            />

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm" className="h-9 px-3 gap-2 rounded-xl bg-background shadow-sm hover:bg-muted font-bold border-muted-foreground/10">
                        <Download className="h-4 w-4 text-primary" />
                        <span>Herramientas</span>
                        <ChevronDown className="h-3 w-3 text-muted-foreground" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56 p-2 rounded-2xl border-none shadow-2xl bg-white dark:bg-slate-900 ring-1 ring-black/5">
                    {canExport && (
                        <>
                            <div className="px-2 py-1.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground/60">Exportar</div>
                            <DropdownMenuItem onClick={() => handleExport('csv')} className="rounded-lg py-2.5 cursor-pointer focus:bg-primary/5 focus:text-primary">
                                <FileText className="mr-2 h-4 w-4 text-blue-500" />
                                <span className="font-medium">Exportar a CSV</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => handleExport('excel')} className="rounded-lg py-2.5 cursor-pointer focus:bg-primary/5 focus:text-primary">
                                <FileSpreadsheet className="mr-2 h-4 w-4 text-green-500" />
                                <span className="font-medium">Exportar a Excel</span>
                            </DropdownMenuItem>
                        </>
                    )}
                    
                    {canImport && (
                        <>
                            <div className="px-2 py-1.5 mt-2 text-[10px] font-black uppercase tracking-widest text-muted-foreground/60">Importar</div>
                            <DropdownMenuItem onClick={() => csvInputRef.current?.click()} className="rounded-lg py-2.5 cursor-pointer focus:bg-primary/5 focus:text-primary">
                                <Upload className="mr-2 h-4 w-4 text-orange-500" />
                                <span className="font-medium">Importar CSV</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => excelInputRef.current?.click()} className="rounded-lg py-2.5 cursor-pointer focus:bg-primary/5 focus:text-primary">
                                <FileSpreadsheet className="mr-2 h-4 w-4 text-indigo-500" />
                                <span className="font-medium">Importar Excel (XLSX)</span>
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
};

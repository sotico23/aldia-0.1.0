<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HasBulkOperations
{
    /**
     * Requirement: Controller must implement this to return the Export class instance.
     */
    abstract protected function getExportClass(array $filters): object;

    /**
     * Requirement: Controller must implement this to return the Import class instance.
     */
    abstract protected function getImportClass(): object;

    /**
     * Standard route for CSV export.
     */
    public function exportCsv(Request $request)
    {
        $filters = $request->only(['search', 'categoria_id', 'estado', 'fecha_desde', 'fecha_hasta', 'activo', 'tipo', 'almacen_id']);
        $export = $this->getExportClass($filters);
        $filename = (new \ReflectionClass($export))->getShortName().'_'.now()->format('Ymd_His').'.csv';

        return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * Standard route for Excel export.
     */
    public function exportExcel(Request $request)
    {
        $filters = $request->only(['search', 'categoria_id', 'estado', 'fecha_desde', 'fecha_hasta', 'activo', 'tipo', 'almacen_id']);
        $export = $this->getExportClass($filters);
        $filename = (new \ReflectionClass($export))->getShortName().'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * Standard route for CSV import.
     */
    public function importCsv(Request $request): RedirectResponse
    {
        return $this->importExcel($request);
    }

    /**
     * Standard route for Excel import.
     */
    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        try {
            Excel::import($this->getImportClass(), $request->file('archivo'));

            return redirect()->back()->with('success', 'Importación completada con éxito.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error en la importación: '.$e->getMessage());
        }
    }

    /**
     * Legacy helper for custom CSV streaming (keeping for backward compatibility if needed)
     */
    protected function performExportCsv(
        $query,
        string $filename,
        array $headers,
        callable $mapper
    ): StreamedResponse {
        $responseHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'_'.now()->format('Ymd_His').'.csv"',
        ];

        $callback = function () use ($query, $headers, $mapper) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $headers, ';');

            $query->chunk(200, function ($records) use ($file, $mapper) {
                foreach ($records as $record) {
                    fputcsv($file, $mapper($record), ';');
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $responseHeaders);
    }
}

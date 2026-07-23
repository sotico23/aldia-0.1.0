<?php

namespace App\Http\Controllers\Backend;

use App\Exports\TimesheetsExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\TimesheetsImport;
use App\Models\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:proyectos.timesheets.create', only: ['create', 'store']),
            new Middleware('permission:proyectos.timesheets.edit', only: ['edit', 'update']),
            new Middleware('permission:proyectos.timesheets.delete', only: ['destroy']),
            new Middleware('permission:proyectos.timesheets.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:proyectos.timesheets.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(): Response
    {
        $timesheets = Timesheet::orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Backend/Timesheets/Index', ['timesheets' => $timesheets]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'empleado_id' => 'nullable|integer',
            'proyecto_id' => 'nullable|integer',
            'fecha' => 'nullable|date',
            'horas' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string',
            'tipo' => 'nullable|string|max:50',
            'estado' => 'required|string|max:50',
        ]);
        Timesheet::create($validated);

        return redirect()->route('timesheets.index');
    }

    public function update(Request $request, Timesheet $timesheet): RedirectResponse
    {
        $validated = $request->validate([
            'empleado_id' => 'nullable|integer',
            'proyecto_id' => 'nullable|integer',
            'fecha' => 'nullable|date',
            'horas' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string',
            'tipo' => 'nullable|string|max:50',
            'estado' => 'required|string|max:50',
        ]);
        $timesheet->update($validated);

        return redirect()->route('timesheets.index');
    }

    public function destroy(Timesheet $timesheet): RedirectResponse
    {
        $timesheet->delete();

        return redirect()->route('timesheets.index');
    }

    protected function getExportClass(array $filters): object
    {
        return new TimesheetsExport($filters);
    }

    protected function getImportClass(): object
    {
        return new TimesheetsImport;
    }
}

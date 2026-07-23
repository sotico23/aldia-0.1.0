<?php

namespace App\Http\Controllers\Backend;

use App\Exports\VaciosExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Imports\VaciosImport;
use App\Models\Producto;
use App\Models\Vacio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VacioController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventario.vacios.create', only: ['create', 'store']),
            new Middleware('permission:inventario.vacios.edit', only: ['edit', 'update']),
            new Middleware('permission:inventario.vacios.delete', only: ['destroy']),
            new Middleware('permission:inventario.vacios.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:inventario.vacios.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Vacio::with('producto')
            ->where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('producto', function ($q2) use ($search) {
                    $q2->where('nombre', 'like', "%{$search}%");
                })->orWhere('ubicacion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $vacios = $query->orderBy('updated_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $productos = Producto::where('activo', true)
            ->where('envase_retornable', true)
            ->where('owner_id', $ownerId)
            ->get();

        return Inertia::render('Backend/Vacios/Index', [
            'vacios' => $vacios->items(),
            'productos' => $productos,
            'filters' => $request->only(['search', 'estado']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('owner_id', $ownerId)],
            'cantidad' => 'required|integer|min:0',
            'cantidad_minima' => 'required|integer|min:0',
            'estado' => 'required|in:disponible,entregado,retornado,perdido',
            'ubicacion' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
        ]);

        $validated['owner_id'] = $ownerId;
        Vacio::create($validated);

        return redirect()->route('vacios.index');
    }

    public function update(Request $request, Vacio $vacio): RedirectResponse
    {
        $validated = $request->validate([
            'cantidad' => 'required|integer|min:0',
            'cantidad_minima' => 'required|integer|min:0',
            'estado' => 'required|in:disponible,entregado,retornado,perdido',
            'ubicacion' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
        ]);

        $vacio->update($validated);

        return redirect()->route('vacios.index');
    }

    public function destroy(Vacio $vacio): RedirectResponse
    {
        $vacio->delete();

        return redirect()->route('vacios.index');
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'vacios_'.now()->format('Ymd_His').'.csv';
        $vacios = Vacio::with('producto')->where('owner_id', auth()->user()->getOwnerId())->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($vacios) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['ID', 'Producto', 'Cantidad', 'Minima', 'Estado', 'Ubicacion'], ';');

            foreach ($vacios as $v) {
                fputcsv($file, [
                    $v->id,
                    $v->producto?->nombre ?? 'N/A',
                    $v->cantidad,
                    $v->cantidad_minima,
                    $v->estado,
                    $v->ubicacion,
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportExcel()
    {
        return Excel::download(new VaciosExport, 'vacios_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt']);
        Excel::import(new VaciosImport, $request->file('archivo'));

        return redirect()->route('vacios.index')->with('success', 'Vacios importados correctamente.');
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate(['archivo' => 'required|file|mimes:xlsx,xls']);
        Excel::import(new VaciosImport, $request->file('archivo'));

        return redirect()->route('vacios.index')->with('success', 'Vacios importados correctamente.');
    }

    public function retornar(Request $request, Vacio $vacio): RedirectResponse
    {
        $validated = $request->validate([
            'cantidad' => 'required|integer|min:1',
        ]);

        $vacio->decrement('cantidad', $validated['cantidad']);

        if ($vacio->cantidad <= 0) {
            $vacio->delete();
        }

        return redirect()->route('vacios.index');
    }
}

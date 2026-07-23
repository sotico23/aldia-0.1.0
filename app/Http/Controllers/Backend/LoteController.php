<?php

namespace App\Http\Controllers\Backend;

use App\Exports\LotesExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Imports\LotesImport;
use App\Models\Lote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventario.lotes.create', only: ['create', 'store']),
            new Middleware('permission:inventario.lotes.edit', only: ['edit', 'update']),
            new Middleware('permission:inventario.lotes.delete', only: ['destroy']),
            new Middleware('permission:inventario.lotes.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:inventario.lotes.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $query = Lote::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero_lote', 'like', "%{$search}%")
                    ->orWhere('producto', 'like', "%{$search}%")
                    ->orWhere('estado', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $lotes = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Backend/Lotes/Index', [
            'lotes' => $lotes,
            'filters' => $request->only(['search', 'estado']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'numero_lote' => 'required|string|max:100',
            'producto' => 'nullable|string|max:255',
            'cantidad' => 'required|integer|min:0',
            'fecha_vencimiento' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);

        $validated['owner_id'] = $ownerId;
        Lote::create($validated);

        return redirect()->route('lotes.index');
    }

    public function update(Request $request, Lote $lote): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        if ($lote->owner_id !== $ownerId) {
            abort(403);
        }

        $validated = $request->validate([
            'numero_lote' => 'required|string|max:100',
            'producto' => 'nullable|string|max:255',
            'cantidad' => 'required|integer|min:0',
            'fecha_vencimiento' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);

        $lote->update($validated);

        return redirect()->route('lotes.index');
    }

    public function destroy(Lote $lote): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        if ($lote->owner_id !== $ownerId) {
            abort(403);
        }

        $lote->delete();

        return redirect()->route('lotes.index');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Lote::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero_lote', 'like', "%{$search}%")
                    ->orWhere('producto', 'like', "%{$search}%")
                    ->orWhere('estado', 'like', "%{$search}%");
            });
        }

        $lotes = $query->orderBy('created_at', 'desc')->get();
        $filename = 'lotes_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($lotes) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, [
                'Número Lote',
                'Producto',
                'Cantidad',
                'Fecha Vencimiento',
                'Estado',
                'Notas',
            ], ';');

            foreach ($lotes as $lote) {
                fputcsv($file, [
                    $lote->numero_lote,
                    $lote->producto,
                    $lote->cantidad,
                    $lote->fecha_vencimiento ? $lote->fecha_vencimiento->format('Y-m-d') : '',
                    $lote->estado,
                    $lote->notas,
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportExcel(Request $request)
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Lote::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero_lote', 'like', "%{$search}%")
                    ->orWhere('producto', 'like', "%{$search}%")
                    ->orWhere('estado', 'like', "%{$search}%");
            });
        }

        $lotes = $query->orderBy('created_at', 'desc')->get();

        return Excel::download(new LotesExport($lotes), 'lotes_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt',
        ]);

        Excel::import(new LotesImport, $request->file('archivo'));

        return redirect()->route('lotes.index')->with('success', 'Lotes importados correctamente.');
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls',
        ]);

        Excel::import(new LotesImport, $request->file('archivo'));

        return redirect()->route('lotes.index')->with('success', 'Lotes importados correctamente.');
    }
}

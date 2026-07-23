<?php

namespace App\Http\Controllers\Backend;

use App\Exports\RafflesExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\RafflesImport;
use App\Models\Raffle;
use App\Models\RafflePrize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RaffleController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:rifas.rifas.create', only: ['create', 'store']),
            new Middleware('permission:rifas.rifas.edit', only: ['edit', 'update']),
            new Middleware('permission:rifas.rifas.delete', only: ['destroy']),
            new Middleware('permission:rifas.rifas.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:rifas.rifas.import', only: ['importCsv', 'importExcel']),
        ];
    }

    protected function getExportClass(array $filters): object
    {
        return new RafflesExport($filters);
    }

    protected function getImportClass(): object
    {
        return new RafflesImport;
    }

    public function index(): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $raffles = Raffle::where('owner_id', $ownerId)
            ->withCount('participants', 'prizes')
            ->with('prizes')
            ->latest()
            ->paginate(15);

        return Inertia::render('Backend/Raffles/Index', [
            'raffles' => $raffles,
        ]);
    }

    public function show(Raffle $raffle): Response
    {
        $this->authorizeRaffle($raffle);
        $raffle->load(['prizes', 'participants', 'winners.prize']);

        return Inertia::render('Backend/Raffles/Show', [
            'raffle' => $raffle,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Backend/Raffles/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'required|image|mimes:jpeg,png,gif,webp|max:10240',
            'prizes' => 'required|array|min:1',
            'prizes.*.name' => 'required|string|max:255',
            'prizes.*.image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:5120',
            'type' => 'required|in:raffle,draw,competition',
            'status' => 'sometimes|in:draft,published,active,completed,cancelled',
            'max_participants' => 'nullable|integer|min:1',
            'min_participants' => 'nullable|integer|min:1',
            'ticket_price' => 'nullable|numeric|min:0',
            'allow_multiple_entries' => 'boolean',
            'max_entries_per_user' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'show_winners' => 'boolean',
            'background_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $validated['slug'] = Str::slug($validated['title']).'-'.rand(1000, 9999);
        $validated['owner_id'] = auth()->user()->getOwnerId();
        $validated['user_id'] = auth()->id();
        $validated['status'] = $validated['status'] ?? 'draft';

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('raffles/'.$validated['owner_id'], 'public');
        }

        $raffle = Raffle::create($validated);

        foreach ($request->input('prizes') as $index => $prizeData) {
            $prizeImage = null;
            if (isset($prizeData['image']) && $request->hasFile("prizes.{$index}.image")) {
                $prizeImage = $request->file("prizes.{$index}.image")->store('raffles/'.$validated['owner_id'].'/prizes', 'public');
            }

            $raffle->prizes()->create([
                'name' => $prizeData['name'],
                'description' => $prizeData['description'] ?? null,
                'image_url' => $prizeImage,
                'quantity' => $prizeData['quantity'] ?? 1,
                'position' => $index + 1,
                'estimated_value' => $prizeData['estimated_value'] ?? null,
            ]);
        }

        return redirect()->route('raffles.index')
            ->with('success', 'Rifa creada exitosamente.');
    }

    public function edit(Raffle $raffle): Response
    {
        $this->authorizeRaffle($raffle);
        $raffle->load('prizes', 'participants', 'winners.prize');

        return Inertia::render('Backend/Raffles/Edit', [
            'raffle' => $raffle,
        ]);
    }

    public function update(Request $request, Raffle $raffle): RedirectResponse
    {
        $this->authorizeRaffle($raffle);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:10240',
            'image_url' => 'nullable|string',
            'type' => 'required|in:raffle,draw,competition',
            'status' => 'sometimes|in:draft,published,active,completed,cancelled',
            'max_participants' => 'nullable|integer|min:1',
            'min_participants' => 'nullable|integer|min:1',
            'ticket_price' => 'nullable|numeric|min:0',
            'allow_multiple_entries' => 'boolean',
            'max_entries_per_user' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'show_winners' => 'boolean',
            'background_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'prizes' => 'sometimes|array',
            'prizes.*.id' => 'nullable|integer',
            'prizes.*.name' => 'required_with:prizes|string|max:255',
            'prizes.*.description' => 'nullable|string',
            'prizes.*.quantity' => 'nullable|integer|min:1',
            'prizes.*.estimated_value' => 'nullable|numeric|min:0',
            'prizes.*.image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('raffles/'.$raffle->owner_id, 'public');
        }

        $raffle->update($validated);

        if ($request->has('prizes')) {
            $submittedPrizeIds = [];
            foreach ($request->input('prizes') as $index => $prizeData) {
                $prizeId = $prizeData['id'] ?? null;

                $prizeDataUpdate = [
                    'name' => $prizeData['name'],
                    'description' => $prizeData['description'] ?? null,
                    'quantity' => $prizeData['quantity'] ?? 1,
                    'position' => $index + 1,
                    'estimated_value' => $prizeData['estimated_value'] ?? null,
                ];

                if ($request->hasFile("prizes.{$index}.image")) {
                    $prizeDataUpdate['image_url'] = $request->file("prizes.{$index}.image")->store('raffles/'.$raffle->owner_id.'/prizes', 'public');
                }

                if ($prizeId) {
                    $raffle->prizes()->where('id', $prizeId)->update($prizeDataUpdate);
                    $submittedPrizeIds[] = $prizeId;
                } else {
                    $newPrize = $raffle->prizes()->create($prizeDataUpdate);
                    $submittedPrizeIds[] = $newPrize->id;
                }
            }

            $raffle->prizes()->whereNotIn('id', $submittedPrizeIds)->delete();
        }

        return back()->with('success', 'Rifa actualizada.');
    }

    public function destroy(Raffle $raffle): RedirectResponse
    {
        $this->authorizeRaffle($raffle);
        $raffle->delete();

        return redirect()->route('raffles.index')
            ->with('success', 'Rifa eliminada.');
    }

    public function storePrize(Request $request, Raffle $raffle): RedirectResponse
    {
        $this->authorizeRaffle($raffle);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
            'position' => 'nullable|integer|min:1',
            'estimated_value' => 'nullable|numeric|min:0',
        ]);

        $validated['position'] = $validated['position'] ?? ($raffle->prizes()->max('position') + 1);

        $raffle->prizes()->create($validated);

        return back()->with('success', 'Premio añadido.');
    }

    public function updatePrize(Request $request, RafflePrize $prize): RedirectResponse
    {
        $this->authorizeRaffle($prize->raffle);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
            'position' => 'nullable|integer|min:1',
            'estimated_value' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:pending,awarded,cancelled',
        ]);

        $prize->update($validated);

        return back()->with('success', 'Premio actualizado.');
    }

    public function destroyPrize(RafflePrize $prize): RedirectResponse
    {
        $this->authorizeRaffle($prize->raffle);
        $prize->delete();

        return back()->with('success', 'Premio eliminado.');
    }

    public function draw(Raffle $raffle)
    {
        $this->authorizeRaffle($raffle);

        if ($raffle->status !== 'active') {
            return response()->json(['message' => 'La rifa debe estar activa para realizar el sorteo.'], 422);
        }

        if ($raffle->participants()->count() < ($raffle->min_participants ?? 1)) {
            return response()->json(['message' => 'No hay suficientes participantes para el sorteo.'], 422);
        }

        $winners = $raffle->drawWinners();

        if (empty($winners)) {
            return response()->json(['message' => 'No se pudieron seleccionar ganadores.'], 422);
        }

        $raffle->update(['status' => 'completed']);

        $winnersData = collect($winners)->map(function ($winner) {
            return [
                'id' => $winner['participant']->id,
                'name' => $winner['participant']->name,
                'email' => $winner['participant']->email,
                'prize' => [
                    'id' => $winner['prize']->id,
                    'name' => $winner['prize']->name,
                ],
                'won_at' => $winner['participant']->won_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'message' => '¡Sorteo realizado! Se seleccionaron '.count($winners).' ganadores.',
            'winners' => $winnersData,
        ]);
    }

    public function exportParticipants(Raffle $raffle)
    {
        $this->authorizeRaffle($raffle);

        $participants = $raffle->participants()->with('prize')->get();

        $filename = 'participantes-'.$raffle->slug.'-'.now()->format('Ymd').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($participants) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Nombre', 'Email', 'Teléfono', 'Participaciones', 'Es Ganador', 'Premio', 'Fecha'], ';');

            foreach ($participants as $p) {
                fputcsv($file, [
                    $p->name,
                    $p->email,
                    $p->phone,
                    $p->entries,
                    $p->is_winner ? 'Sí' : 'No',
                    $p->prize?->name ?? '-',
                    $p->won_at?->format('Y-m-d H:i') ?? '-',
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function drawsIndex(): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $raffles = Raffle::where('owner_id', $ownerId)
            ->whereIn('status', ['published', 'active'])
            ->withCount('participants', 'prizes')
            ->with('winners.prize')
            ->latest()
            ->paginate(15)
            ->through(function ($raffle) {
                $raffle->winners_count = $raffle->winners->count();

                return $raffle;
            });

        return Inertia::render('Backend/Raffles/Draws/Index', [
            'raffles' => $raffles,
        ]);
    }

    public function drawRoom(Raffle $raffle): Response
    {
        $this->authorizeRaffle($raffle);
        $raffle->load(['prizes', 'participants', 'winners.prize']);

        return Inertia::render('Backend/Raffles/Draws/Room', [
            'raffle' => $raffle,
        ]);
    }

    private function authorizeRaffle(Raffle $raffle): void
    {
        $ownerId = auth()->user()->getOwnerId();
        if ($raffle->owner_id !== $ownerId) {
            abort(403, 'No tienes autorización para esta rifa.');
        }
    }
}

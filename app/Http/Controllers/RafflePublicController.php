<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use App\Models\RaffleParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RafflePublicController extends Controller
{
    public function show(string $slug)
    {
        $raffle = Raffle::where('slug', $slug)
            ->whereIn('status', ['published', 'active'])
            ->with('prizes')
            ->firstOrFail();

        $participantCount = $raffle->getUniqueParticipantCount();
        $isParticipating = false;

        if (Auth::check()) {
            $isParticipating = RaffleParticipant::where('raffle_id', $raffle->id)
                ->where('email', Auth::user()->email)
                ->exists();
        }

        return Inertia::render('Raffles/Public/Show', [
            'raffle' => [
                'id' => $raffle->id,
                'title' => $raffle->title,
                'description' => $raffle->description,
                'slug' => $raffle->slug,
                'cover_image' => $raffle->cover_image,
                'image_url' => $raffle->image_url,
                'type' => $raffle->type,
                'status' => $raffle->status,
                'ticket_price' => $raffle->ticket_price,
                'max_participants' => $raffle->max_participants,
                'min_participants' => $raffle->min_participants,
                'start_date' => $raffle->start_date?->toIsoString(),
                'end_date' => $raffle->end_date?->toIsoString(),
                'show_winners' => $raffle->show_winners,
                'background_color' => $raffle->background_color,
                'text_color' => $raffle->text_color,
                'prizes' => $raffle->prizes->map(fn ($prize) => [
                    'id' => $prize->id,
                    'name' => $prize->name,
                    'description' => $prize->description,
                    'image_url' => $prize->image_url,
                    'quantity' => $prize->quantity,
                    'position' => $prize->position,
                    'estimated_value' => $prize->estimated_value,
                ]),
            ],
            'participantCount' => $participantCount,
            'isParticipating' => $isParticipating,
        ]);
    }

    public function participate(Request $request, string $slug)
    {
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'Debes iniciar sesión para participar en la rifa.');
        }

        $raffle = Raffle::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        if (! $raffle->canParticipate()) {
            return back()->with('error', 'La rifa ya no acepta más participantes.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $existingParticipant = $raffle->participants()->where('email', $validated['email'])->first();

        if ($existingParticipant) {
            if (! $raffle->allow_multiple_entries) {
                return back()->with('error', 'Ya estás participando en esta rifa.');
            }

            $currentEntries = $existingParticipant->entries;
            if ($raffle->max_entries_per_user && $currentEntries >= $raffle->max_entries_per_user) {
                return back()->with('error', 'Has alcanzado el límite de participaciones.');
            }

            $existingParticipant->increment('entries');

            return back()->with('success', '¡Tu participación ha sido registrada!');
        }

        $raffle->participants()->create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'ip_address' => $request->ip(),
            'entries' => 1,
        ]);

        return back()->with('success', '¡Estás participando! Mucha suerte.');
    }

    public function buyNumbers(Request $request, string $slug)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Debes iniciar sesión para comprar números.'], 401);
        }

        $raffle = Raffle::where('slug', $slug)
            ->whereIn('status', ['published', 'active'])
            ->firstOrFail();

        if (! $raffle->canParticipate()) {
            return response()->json(['error' => 'La rifa ya no acepta más participantes.'], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'numbers' => 'required|array|min:1',
            'numbers.*' => 'integer|min:1',
        ]);

        $totalCost = count($validated['numbers']) * $raffle->ticket_price;

        foreach ($validated['numbers'] as $number) {
            $existingParticipant = $raffle->participants()
                ->where('email', $validated['email'])
                ->first();

            if ($existingParticipant) {
                if ($raffle->allow_multiple_entries && $raffle->max_entries_per_user) {
                    $currentEntries = $existingParticipant->entries;
                    if ($currentEntries < $raffle->max_entries_per_user) {
                        $existingParticipant->increment('entries');
                    }
                }
            } else {
                $raffle->participants()->create([
                    'user_id' => Auth::id(),
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'ip_address' => $request->ip(),
                    'entries' => 1,
                    'purchased_numbers' => json_encode([$number]),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Compra realizada exitosamente',
            'numbers' => $validated['numbers'],
            'total' => $totalCost,
        ]);
    }

    public function winners(string $slug)
    {
        $raffle = Raffle::where('slug', $slug)
            ->where('show_winners', true)
            ->with(['winners.prize', 'prizes'])
            ->firstOrFail();

        return Inertia::render('Raffles/Public/Winners', [
            'raffle' => $raffle,
        ]);
    }
}

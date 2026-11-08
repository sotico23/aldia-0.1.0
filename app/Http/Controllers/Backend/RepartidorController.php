<?php

namespace App\Http\Controllers\Backend;

use App\Actions\Delivery\CreateCompanyDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backend\StoreRepartidorRequest;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Scopes\OwnerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class RepartidorController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:delivery.repartidores.create', only: ['store']),
            new Middleware('permission:delivery.repartidores.edit', only: ['update']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = (int) $request->user()->getOwnerId();

        $activos = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->whereIn('estado', ['preparando', 'enviado'])
            ->whereNotNull('repartidor_id')
            ->selectRaw('repartidor_id, count(*) as total')
            ->groupBy('repartidor_id')
            ->pluck('total', 'repartidor_id')
            ->all();

        $repartidores = Repartidor::query()
            ->with('user:id,name,email')
            ->orderBy('estado')
            ->orderBy('user_id')
            ->get()
            ->map(fn (Repartidor $repartidor) => [
                'id' => $repartidor->id,
                'user_id' => $repartidor->user_id,
                'nombre' => $repartidor->user?->name,
                'email' => $repartidor->user?->email,
                'estado' => $repartidor->estado,
                'radio_km' => (float) $repartidor->radio_km,
                'capacidad_max' => (int) $repartidor->capacidad_max,
                'pedidos_activos' => (int) ($activos[$repartidor->user_id] ?? 0),
                'lat' => $repartidor->lat !== null ? (float) $repartidor->lat : null,
                'lng' => $repartidor->lng !== null ? (float) $repartidor->lng : null,
                'last_position_at' => $repartidor->last_position_at?->toISOString(),
            ])
            ->values()
            ->all();

        $vinculados = Repartidor::query()->pluck('user_id');

        $candidatos = User::query()
            ->where(function ($query) use ($ownerId) {
                $query->where('creator_id', $ownerId)
                    ->orWhereHas('roles', fn ($roles) => $roles->where('name', Repartidor::defaultRole()));
            })
            ->whereNotIn('id', $vinculados)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();

        return Inertia::render('Backend/Repartidores/Index', [
            'owner_id' => $ownerId,
            'repartidores' => $repartidores,
            'candidatos' => $candidatos,
        ]);
    }

    public function store(StoreRepartidorRequest $request, CreateCompanyDriver $createDriver): RedirectResponse|JsonResponse
    {
        $result = $createDriver->handle($request->validated(), $request->user());

        /** @var User $user */
        $user = $result['user'];
        $repartidor = $result['repartidor'];

        $payload = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'creator_id' => $user->creator_id,
                'owner_id' => $user->owner_id,
                'roles' => $user->getRoleNames()->values(),
            ],
            'repartidor' => [
                'id' => $repartidor->id,
                'owner_id' => $repartidor->owner_id,
                'user_id' => $repartidor->user_id,
                'driver_type' => $repartidor->driver_type,
                'approval_status' => $repartidor->approval_status,
                'estado' => $repartidor->estado,
                'radio_km' => $repartidor->radio_km,
                'capacidad_max' => $repartidor->capacidad_max,
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Repartidor creado correctamente.',
                'data' => $payload,
            ], 201);
        }

        return back()->with('success', 'Repartidor creado correctamente.');
    }

    public function update(Request $request, Repartidor $repartidor): RedirectResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'in:disponible,ocupado,offline'],
            'radio_km' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'capacidad_max' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $repartidor->update($validated);

        return back()->with('success', 'Repartidor actualizado correctamente.');
    }
}

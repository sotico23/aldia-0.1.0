<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Requests\Api\Bot\StoreVentaRequest;
use App\Http\Requests\Api\Bot\UpdateVentaRequest;
use App\Services\Api\Bot\VentaBotService;
use App\Support\BotContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentaBotController extends BotBaseController
{
    public function __construct(private readonly VentaBotService $ventas) {}

    public function index(Request $request): JsonResponse
    {
        $ctx = $this->botContext($request);

        $page = $this->ventas->paginate($ctx->ownerId, [
            'search' => trim((string) $request->query('search', '')),
            'estado' => $request->query('estado'),
            'fecha_desde' => $request->query('fecha_desde'),
            'fecha_hasta' => $request->query('fecha_hasta'),
            'cliente_id' => $request->query('cliente_id'),
            'limit' => $request->query('limit'),
            'offset' => $request->query('offset'),
        ]);

        return $this->ok([
            'items' => $page['items']->map(fn ($venta) => $this->ventas->serialize($venta)),
            'total' => $page['total'],
            'limit' => $page['limit'],
            'offset' => $page['offset'],
        ], 'Ventas obtenidas.');
    }

    public function show(Request $request, int $venta): JsonResponse
    {
        $venta = $this->ventas->find($this->botContext($request)->ownerId, $venta);

        if (! $venta) {
            return $this->notFound('Venta no encontrada.');
        }

        return $this->ok($this->ventas->serializeDetail($venta), 'Venta obtenida.');
    }

    public function store(StoreVentaRequest $request): JsonResponse
    {
        $ctx = $this->botContext($request);

        $venta = $this->ventas->create($ctx->ownerId, $ctx->actingUserId, $request->validated());

        return $this->created($this->ventas->serializeDetail($venta), 'Venta creada.');
    }

    public function update(UpdateVentaRequest $request, int $venta): JsonResponse
    {
        $venta = $this->ventas->find($this->botContext($request)->ownerId, $venta);

        if (! $venta) {
            return $this->notFound('Venta no encontrada.');
        }

        $venta = $this->ventas->update($venta, $request->validated());

        return $this->ok($this->ventas->serializeDetail($venta), 'Venta actualizada.');
    }

    public function destroy(Request $request, int $venta): JsonResponse
    {
        $venta = $this->ventas->find($this->botContext($request)->ownerId, $venta);

        if (! $venta) {
            return $this->notFound('Venta no encontrada.');
        }

        $this->ventas->cancel($venta);

        return $this->ok(null, 'Venta cancelada.');
    }

    protected function botContext(Request $request): BotContext
    {
        return $request->attributes->get('bot_context');
    }
}

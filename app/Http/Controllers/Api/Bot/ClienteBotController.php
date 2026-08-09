<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Requests\Api\Bot\StoreClienteRequest;
use App\Http\Requests\Api\Bot\UpdateClienteRequest;
use App\Services\Api\Bot\ClienteBotService;
use App\Support\BotContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteBotController extends BotBaseController
{
    public function __construct(private readonly ClienteBotService $clientes) {}

    public function index(Request $request): JsonResponse
    {
        $ctx = $this->botContext($request);

        $page = $this->clientes->paginate($ctx->ownerId, [
            'search' => trim((string) $request->query('search', '')),
            'activo' => $request->query('activo'),
            'limit' => $request->query('limit'),
            'offset' => $request->query('offset'),
        ]);

        return $this->ok([
            'items' => $page['items']->map(fn ($cliente) => $this->clientes->serialize($cliente)),
            'total' => $page['total'],
            'limit' => $page['limit'],
            'offset' => $page['offset'],
        ], 'Clientes obtenidos.');
    }

    public function show(Request $request, int $cliente): JsonResponse
    {
        $cliente = $this->clientes->find($this->botContext($request)->ownerId, $cliente);

        if (! $cliente) {
            return $this->notFound('Cliente no encontrado.');
        }

        return $this->ok($this->clientes->serialize($cliente), 'Cliente obtenido.');
    }

    public function store(StoreClienteRequest $request): JsonResponse
    {
        $ctx = $this->botContext($request);

        $cliente = $this->clientes->create($ctx->ownerId, $ctx->actingUserId, $request->validated());

        return $this->created($this->clientes->serialize($cliente), 'Cliente creado.');
    }

    public function update(UpdateClienteRequest $request, int $cliente): JsonResponse
    {
        $cliente = $this->clientes->find($this->botContext($request)->ownerId, $cliente);

        if (! $cliente) {
            return $this->notFound('Cliente no encontrado.');
        }

        $cliente = $this->clientes->update($cliente, $request->validated());

        return $this->ok($this->clientes->serialize($cliente), 'Cliente actualizado.');
    }

    public function destroy(Request $request, int $cliente): JsonResponse
    {
        $cliente = $this->clientes->find($this->botContext($request)->ownerId, $cliente);

        if (! $cliente) {
            return $this->notFound('Cliente no encontrado.');
        }

        $this->clientes->disable($cliente);

        return $this->ok(null, 'Cliente desactivado.');
    }

    protected function botContext(Request $request): BotContext
    {
        return $request->attributes->get('bot_context');
    }
}

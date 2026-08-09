<?php

namespace App\Services\Api\Bot;

use App\Models\Cliente;
use App\Services\ClienteService;

class ClienteBotService
{
    private const MAX_LIMIT = 100;

    public function __construct(private readonly ClienteService $clienteService) {}

    public function paginate(int $ownerId, array $filters): array
    {
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), self::MAX_LIMIT);
        $offset = max((int) ($filters['offset'] ?? 0), 0);

        $query = Cliente::query();

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q
                ->where('nombre', 'like', $term)
                ->orWhere('nit', 'like', $term)
                ->orWhere('rut', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('telefono', 'like', $term));
        }

        if (array_key_exists('activo', $filters) && $filters['activo'] !== null && $filters['activo'] !== '') {
            $query->where('activo', filter_var($filters['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        $total = (clone $query)->where('owner_id', $ownerId)->count();

        $items = $query->where('owner_id', $ownerId)
            ->orderByDesc('id')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function find(int $ownerId, int $id): ?Cliente
    {
        return Cliente::where('owner_id', $ownerId)->find($id);
    }

    public function create(int $ownerId, int $actingUserId, array $data): Cliente
    {
        $data['user_id'] = $actingUserId;
        $data['owner_id'] = $ownerId;

        return $this->clienteService->crearCliente($data);
    }

    public function update(Cliente $cliente, array $data): Cliente
    {
        return $this->clienteService->actualizarCliente($cliente, $data);
    }

    public function disable(Cliente $cliente): Cliente
    {
        $cliente->update(['activo' => false]);

        return $cliente->fresh();
    }

    public function serialize(Cliente $cliente): array
    {
        return [
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'rut' => $cliente->rut,
            'nit' => $cliente->nit,
            'email' => $cliente->email,
            'telefono' => $cliente->telefono,
            'direccion' => $cliente->direccion,
            'ciudad' => $cliente->ciudad,
            'region' => $cliente->region,
            'comuna' => $cliente->comuna,
            'giro' => $cliente->giro,
            'contacto' => $cliente->contacto,
            'telefono_contacto' => $cliente->telefono_contacto,
            'categoria_id' => $cliente->categoria_id,
            'activo' => $cliente->activo,
        ];
    }
}

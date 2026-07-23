<?php

namespace App\Http\Controllers\Traits;

use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;

trait FiltraPorCliente
{
    protected function getClienteAuth(): ?Cliente
    {
        $user = Auth::user();

        return $user?->getClienteActual();
    }

    protected function usuarioEsCliente(): bool
    {
        return Auth::check() && Auth::user()->isCliente();
    }

    protected function aplicarFiltroCliente($query)
    {
        $cliente = $this->getClienteAuth();

        if ($cliente) {
            $query->where('cliente_id', $cliente->id);
        }

        return $query;
    }
}

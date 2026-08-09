<?php

namespace App\Http\Requests\Api\Bot;

use App\Support\BotContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ownerId = $this->botContext()->ownerId;
        $clienteId = (int) $this->route('cliente');

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'rut' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d{1,2}\.\d{3}\.\d{3}-[\dkK]$/', $this->uniquePerTenant('clientes', 'rut', $ownerId, $clienteId)],
            'nit' => ['sometimes', 'nullable', 'string', 'max:30', $this->uniquePerTenant('clientes', 'nit', $ownerId, $clienteId)],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('clientes', 'email')->ignore($clienteId)],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ciudad' => ['sometimes', 'nullable', 'string', 'max:100'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
            'comuna' => ['sometimes', 'nullable', 'string', 'max:100'],
            'giro' => ['sometimes', 'nullable', 'string', 'max:150'],
            'contacto' => ['sometimes', 'nullable', 'string', 'max:150'],
            'telefono_contacto' => ['sometimes', 'nullable', 'string', 'max:30'],
            'categoria_id' => ['sometimes', 'nullable', 'integer',
                Rule::exists('categorias', 'id')->where(fn ($q) => $q->where('owner_id', $ownerId)),
            ],
            'activo' => ['sometimes', 'boolean'],
            'notas' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function botContext(): BotContext
    {
        return $this->attributes->get('bot_context');
    }

    protected function uniquePerTenant(string $table, string $column, int $ownerId, int $ignoreId): Unique
    {
        return Rule::unique($table, $column)
            ->where(fn ($q) => $q->where('owner_id', $ownerId))
            ->ignore($ignoreId);
    }
}

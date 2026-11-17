<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && ($this->user()->hasRole('Master') || $this->user()->hasRole('Super Admin'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $commands = config('maintenance.commands', []);
        $command = $this->input('command');
        $requiresConfirmation = is_string($command) && ($commands[$command]['confirm'] ?? false);

        return [
            'command' => ['required', 'string', Rule::in(array_keys($commands))],
            'confirmed' => $requiresConfirmation ? ['required', 'accepted'] : ['sometimes', 'boolean'],
            'parameters' => ['prohibited'],
            'arguments' => ['prohibited'],
        ];
    }
}

<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', $this->uniqueUserRule('email', $userId)],
            'country' => ['nullable', 'string', 'exists:countries,code'],
            'job' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'giro' => ['nullable', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:20', $this->uniqueUserRule('rut', $userId)],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'comuna' => ['nullable', 'string', 'max:100'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'genero' => ['nullable', 'string', 'max:50'],
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120',
            ],
            'cover_photo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:10240',
            ],
        ];
    }

    /**
     * Get a uniqueness rule for a given column, ignoring a specific user id.
     *
     * @param  string  $field  The database column name.
     * @param  ?int  $userId  The user id to ignore.
     */
    private function uniqueUserRule(string $field, ?int $userId = null): Rule
    {
        return Rule::unique(User::class, $field)->ignore($userId);
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $this->uniqueUserRule('email', $userId),
        ];
    }
}

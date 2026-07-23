<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->check();
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', Rule::in(['smtp', 'sendmail', 'mailgun', 'postmark', 'ses'])],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];

        $driver = $this->input('driver', $this->route('emailConfig')?->driver ?? 'smtp');

        if ($driver === 'smtp') {
            $rules = array_merge($rules, [
                'host' => ['required', 'string', 'max:255'],
                'port' => ['required', 'integer', 'between:1,65535'],
                'encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
                'username' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string'],
            ]);
        }

        if ($driver === 'mailgun') {
            $rules = array_merge($rules, [
                'domain' => ['required', 'string', 'max:255'],
                'secret' => ['required', 'string'],
                'endpoint' => ['nullable', 'string', 'max:255'],
            ]);
        }

        if ($driver === 'postmark') {
            $rules = array_merge($rules, [
                'secret' => ['required', 'string'],
            ]);
        }

        if ($driver === 'ses') {
            $rules = array_merge($rules, [
                'username' => ['required', 'string', 'max:255', 'min:20'],
                'password' => ['required', 'string'],
                'region' => ['nullable', 'string', 'max:50'],
                'domain' => ['nullable', 'string', 'max:255'],
            ]);
        }

        if ($driver === 'sendmail') {
            $rules = array_merge($rules, [
                'host' => ['nullable', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string'],
            ]);
        }

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['password'] = ['nullable', 'string'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la configuración es requerido.',
            'driver.required' => 'Selecciona un driver de correo.',
            'from_address.required' => 'El email del remitente es requerido.',
            'from_address.email' => 'El email del remitente no es válido.',
            'from_name.required' => 'El nombre del remitente es requerido.',
            'host.required' => 'El host SMTP es requerido.',
            'port.required' => 'El puerto es requerido.',
            'port.between' => 'El puerto debe estar entre 1 y 65535.',
            'username.required' => 'El usuario es requerido.',
            'password.required' => 'La contraseña es requerida.',
            'domain.required' => 'El dominio es requerido para Mailgun.',
            'secret.required' => 'El API Token es requerido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('encryption') && $this->input('encryption') === '') {
            $this->merge(['encryption' => null]);
        }
    }
}

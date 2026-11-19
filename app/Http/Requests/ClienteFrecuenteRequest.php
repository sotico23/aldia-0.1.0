<?php

namespace App\Http\Requests;

use App\Models\ClienteFrecuente;
use App\Models\Wallet;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteFrecuenteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canUsePersonalWallet() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $wallet = $this->user()?->wallet;
        $clienteFrecuente = $this->route('clienteFrecuente');
        $clienteFrecuenteId = $clienteFrecuente instanceof ClienteFrecuente
            ? $clienteFrecuente->id
            : null;

        return [
            'nombre' => ['required', 'string', 'max:120'],
            'account_number' => [
                'required',
                'string',
                'regex:/^[0-9]{16}$/',
                Rule::unique('clientes_frecuentes', 'account_number')
                    ->where(fn ($query) => $query->where('wallet_id', $wallet?->id))
                    ->ignore($clienteFrecuenteId),
                function (string $attribute, mixed $value, Closure $fail) use ($wallet): void {
                    if ($wallet === null) {
                        $fail('Debes tener una billetera para guardar clientes frecuentes.');

                        return;
                    }

                    if ($wallet->account_number === $value) {
                        $fail('No puedes agregar tu propia billetera como cliente frecuente.');

                        return;
                    }

                    if (! Wallet::query()->where('account_number', $value)->exists()) {
                        $fail('No existe una billetera con ese número.');
                    }
                },
            ],
            'nota' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
            'account_number' => is_string($this->account_number)
                ? preg_replace('/\s+/', '', $this->account_number)
                : $this->account_number,
            'nota' => filled($this->nota) ? trim((string) $this->nota) : null,
        ]);
    }
}

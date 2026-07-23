<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function create(array $input): User
    {
        try {
            Validator::make($input, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'country' => ['nullable', 'string', 'exists:countries,code'],
                'business_name' => ['nullable', 'string', 'max:255'],
                'telefono' => ['nullable', 'string', 'max:20'],
                'password' => $this->passwordRules(),
                'referral_code' => ['nullable', 'string', 'exists:users,referral_code'],
            ], [
                'referral_code.exists' => 'El código de referido ingresado no es válido.',
                'country.exists' => 'El país seleccionado no es válido.',
            ])->validate();

            $referrer = null;
            if (! empty($input['referral_code'])) {
                $referrer = User::where('referral_code', $input['referral_code'])->first();
            }

            $countryCode = $input['country'] ?? Country::getDefault()?->code ?? 'CL';

            // Hash the password before persisting
            $hashedPassword = Hash::make($input['password']);

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'country' => $countryCode,
                'business_name' => $input['business_name'] ?? $input['name'],
                'telefono' => $input['telefono'] ?? null,
                'password' => $hashedPassword,
                'creator_id' => null,
                'referred_by' => $referrer ? $referrer->id : null,
            ]);

            if ($referrer) {
                $referrer->increment('points', 10);
            }

            return $user;
        } catch (\Throwable $e) {
            // Log the error for debugging purposes
            Log::error('Error creating new user', [
                'input' => $input,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Re‑throw the exception so the caller can handle it appropriately
            throw $e;
        }
    }
}

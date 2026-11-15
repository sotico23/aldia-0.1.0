<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:255', Rule::unique('public_profiles', 'slug')->ignore($this->user()?->publicProfile?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'pickup_address' => ['nullable', 'string', 'max:255', 'required_with:pickup_lat,pickup_lng'],
            'pickup_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:pickup_lng'],
            'pickup_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:pickup_lat'],
        ];
    }
}

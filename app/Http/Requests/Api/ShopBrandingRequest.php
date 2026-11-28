<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ShopBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->is_active && $user->banned_at === null && $user->hasRole('Master');
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'login_description' => ['required', 'string', 'max:500'],
            'logo_url' => ['nullable', 'url:https', 'max:2048'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }
}

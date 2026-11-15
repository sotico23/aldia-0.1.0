<?php

namespace App\Http\Requests;

class UpdateCountryRequest extends StoreCountryRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['code']);

        return $rules;
    }
}

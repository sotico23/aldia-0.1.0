<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class RutRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isValidRut($value)) {
            $fail('El dígito verificador del RUT no es válido. Ejemplo correcto: 12.345.678-5.');
        }
    }

    /**
     * Validador de RUT Chileno
     */
    private function isValidRut($rut): bool
    {
        $rut = preg_replace('/[^k0-9]/i', '', $rut);
        if (strlen($rut) < 8) {
            return false;
        }

        $dv = substr($rut, -1);
        $numero = substr($rut, 0, strlen($rut) - 1);
        $i = 2;
        $suma = 0;
        foreach (array_reverse(str_split($numero)) as $v) {
            if ($i == 8) {
                $i = 2;
            }
            $suma += $v * $i;
            $i++;
        }
        $dvr = 11 - ($suma % 11);
        if ($dvr == 11) {
            $dvr = 0;
        }
        if ($dvr == 10) {
            $dvr = 'K';
        }

        return strtoupper($dv) == strtoupper($dvr);
    }
}

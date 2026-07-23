<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class RutValido implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rut = preg_replace('/[^0-9kK]/', '', (string) $value);

        if (strlen($rut) < 8) {
            $fail('El RUT ingresado no tiene el formato chileno válido (mínimo 8 caracteres)');

            return;
        }

        $numero = substr($rut, 0, -1);
        $dv = strtolower(substr($rut, -1));

        if (! is_numeric($numero)) {
            $fail('El RUT ingresado no es válido');

            return;
        }

        $suma = 0;
        $multiplicador = 2;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $char = substr($numero, $i, 1);
            $suma += ((int) $char) * $multiplicador;
            $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
        }

        $res = 11 - ($suma % 11);
        $dvEsperado = match ($res) {
            11 => '0',
            10 => 'k',
            default => (string) $res,
        };

        if ($dv !== $dvEsperado) {
            $fail('El RUT ingresado no tiene un dígito verificador válido.');
        }
    }
}

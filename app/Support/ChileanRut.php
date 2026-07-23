<?php

namespace App\Support;

class ChileanRut
{
    /**
     * Valida un RUT chileno (formato y dígito verificador).
     */
    public static function validate(string $rut): bool
    {
        $rut = preg_replace('/[^k0-9]/i', '', $rut);
        if (strlen($rut) < 8) {
            return false;
        }

        $dv = substr($rut, -1);
        $numero = substr($rut, 0, strlen($rut) - 1);

        return self::calculateDV($numero) == strtoupper($dv);
    }

    /**
     * Calcula el dígito verificador de un número.
     */
    public static function calculateDV(string|int $numero): string
    {
        $numero = preg_replace('/[^0-9]/', '', (string) $numero);
        $i = 2;
        $suma = 0;
        foreach (array_reverse(str_split($numero)) as $v) {
            if ($i == 8) {
                $i = 2;
            }
            $suma += $v * $i;
            $i++;
        }

        $dvt = 11 - ($suma % 11);
        if ($dvt == 11) {
            return '0';
        }
        if ($dvt == 10) {
            return 'K';
        }

        return (string) $dvt;
    }

    /**
     * Formatea un RUT (e.g. 12345678K -> 12.345.678-K).
     */
    public static function format(string $rut): string
    {
        $rut = preg_replace('/[^k0-9]/i', '', $rut);
        if (strlen($rut) < 2) {
            return $rut;
        }

        $dv = substr($rut, -1);
        $numero = substr($rut, 0, strlen($rut) - 1);

        return number_format((float) $numero, 0, ',', '.').'-'.strtoupper($dv);
    }
}

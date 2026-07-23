<?php

namespace App\Enums;

enum Currency: string
{
    case CLP = 'CLP';
    case COP = 'COP';
    case PEN = 'PEN';
    case ARS = 'ARS';
    case BOB = 'BOB';
    case USD = 'USD';
    case BRL = 'BRL';
    case VES = 'VES';
    case UYU = 'UYU';
    case PYG = 'PYG';
    case GTQ = 'GTQ';

    public function symbol(): string
    {
        return match ($this) {
            self::CLP, self::COP, self::ARS, self::USD, self::UYU => '$',
            self::PEN => 'S/',
            self::BOB, self::VES => 'Bs',
            self::BRL => 'R$',
            self::PYG => '₲',
            self::GTQ => 'Q',
        };
    }

    public function decimals(): int
    {
        return match ($this) {
            self::CLP, self::COP, self::PYG => 0,
            default => 2,
        };
    }

    public function locale(): string
    {
        return match ($this) {
            self::CLP => 'es-CL',
            self::COP => 'es-CO',
            self::PEN => 'es-PE',
            self::ARS => 'es-AR',
            self::BOB => 'es-BO',
            self::USD => 'en-US',
            self::BRL => 'pt-BR',
            self::VES => 'es-VE',
            self::UYU => 'es-UY',
            self::PYG => 'es-PY',
            self::GTQ => 'es-GT',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CLP => 'Peso Chileno',
            self::COP => 'Peso Colombiano',
            self::PEN => 'Sol Peruano',
            self::ARS => 'Peso Argentino',
            self::BOB => 'Boliviano',
            self::USD => 'Dólar',
            self::BRL => 'Real Brasileño',
            self::VES => 'Bolívar Venezolano',
            self::UYU => 'Peso Uruguayo',
            self::PYG => 'Guaraní',
            self::GTQ => 'Quetzal',
        };
    }

    public static function default(): string
    {
        return self::CLP->value;
    }

    public static function fromCountry(string $countryCode): self
    {
        return match (strtoupper($countryCode)) {
            'PE' => self::PEN,
            'CL' => self::CLP,
            default => self::CLP,
        };
    }

    public static function supportedValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label().' ('.$c->symbol().')',
            'symbol' => $c->symbol(),
            'decimals' => $c->decimals(),
            'locale' => $c->locale(),
        ], self::cases());
    }
}

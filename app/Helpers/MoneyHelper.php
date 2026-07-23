<?php

namespace App\Helpers;

use App\Enums\Currency;
use App\Models\Country;

class MoneyHelper
{
    /**
     * Format a monetary amount with the given currency.
     */
    public static function format(float $amount, ?string $currency = null): string
    {
        $currency = $currency ? Currency::tryFrom($currency) : Currency::tryFrom(config('currencies.default', 'CLP'));
        $currency = $currency ?? Currency::CLP;

        $value = (float) $amount;

        if ($value == 0) {
            return $currency->symbol().' 0';
        }

        return number_format($value, $currency->decimals(), ',', '.').$currency->symbol();
    }

    /**
     * Format a monetary amount with symbol prefix (e.g. "$ 1.500").
     */
    public static function formatWithSymbol(float $amount, ?string $currency = null): string
    {
        $currency = $currency ? Currency::tryFrom($currency) : Currency::tryFrom(config('currencies.default', 'CLP'));
        $currency = $currency ?? Currency::CLP;

        $value = (float) $amount;

        $formatted = number_format($value, $currency->decimals(), ',', '.');

        return $currency->symbol().' '.$formatted;
    }

    /**
     * Format a monetary amount for a specific country using locale-aware formatting.
     */
    public static function formatForCountry(float $amount, ?string $countryCode = null): string
    {
        $countryCode = $countryCode ?? config('countries.default', 'CL');
        $country = Country::findByCode($countryCode);

        if (! $country) {
            return self::formatWithSymbol($amount);
        }

        $currency = Currency::tryFrom($country->currency_code) ?? Currency::CLP;
        $value = (float) $amount;
        $decimals = $currency->decimals();

        $formatted = number_format($value, $decimals, ',', '.');

        return $country->currency_symbol.' '.$formatted;
    }

    /**
     * Format a monetary amount with symbol prefix for a specific country.
     */
    public static function formatWithSymbolForCountry(float $amount, ?string $countryCode = null): string
    {
        return self::formatForCountry($amount, $countryCode);
    }

    /**
     * Get the currency symbol for a given currency code.
     */
    public static function symbol(?string $currency = null): string
    {
        $currency = $currency ? Currency::tryFrom($currency) : Currency::tryFrom(config('currencies.default', 'CLP'));

        return $currency?->symbol() ?? '$';
    }

    /**
     * Get the number of decimal places for a given currency code.
     */
    public static function decimals(?string $currency = null): int
    {
        $currency = $currency ? Currency::tryFrom($currency) : Currency::tryFrom(config('currencies.default', 'CLP'));

        return $currency?->decimals() ?? 0;
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenFoodFactsService
{
    /**
     * @return array{encontrado: bool, error: bool, datos: array{nombre: ?string, marca: ?string, categoria: ?string, imagen: ?string, formato: ?string}|null}
     */
    public function buscarProductoPorEAN(string $codigoEAN): array
    {
        try {
            $response = Http::withUserAgent(sprintf(
                '%s - Web/1.0 - %s',
                config('app.name', 'MiAppInventario'),
                config('mail.from.address', 'contacto@miapp.com'),
            ))
                ->connectTimeout(3)
                ->timeout(8)
                ->get("https://world.openfoodfacts.org/api/v2/product/{$codigoEAN}.json")
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('No se pudo consultar Open Food Facts.', [
                'codigo_ean' => $codigoEAN,
                'error' => $exception->getMessage(),
            ]);

            return [
                'encontrado' => false,
                'error' => true,
                'datos' => null,
            ];
        }

        $payload = $response->json();
        $product = is_array($payload) && ($payload['status'] ?? 0) === 1
            ? ($payload['product'] ?? null)
            : null;

        if (! is_array($product)) {
            return [
                'encontrado' => false,
                'error' => false,
                'datos' => null,
            ];
        }

        return [
            'encontrado' => true,
            'error' => false,
            'datos' => [
                'nombre' => $this->cleanText($product['product_name_es'] ?? null)
                    ?? $this->cleanText($product['product_name'] ?? null),
                'marca' => $this->cleanText($product['brands'] ?? null),
                'categoria' => $this->cleanCategories(
                    $product['categories'] ?? $product['categories_tags'] ?? null,
                ),
                'imagen' => $this->cleanImageUrl($product['image_front_url'] ?? null)
                    ?? $this->cleanImageUrl($product['image_url'] ?? null),
                'formato' => $this->cleanText($product['quantity'] ?? null),
            ],
        ];
    }

    private function cleanText(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $cleaned = Str::squish(strip_tags($value));

        return $cleaned !== '' ? $cleaned : null;
    }

    private function cleanCategories(mixed $categories): ?string
    {
        if (is_array($categories)) {
            $categories = array_map(function (mixed $category): ?string {
                if (! is_string($category)) {
                    return null;
                }

                $category = preg_replace('/^[a-z]{2,3}:/i', '', $category) ?? $category;

                return $this->cleanText(str_replace('-', ' ', $category));
            }, $categories);

            $categories = implode(', ', array_filter($categories));
        }

        return $this->cleanText($categories);
    }

    private function cleanImageUrl(mixed $value): ?string
    {
        $url = $this->cleanText($value);

        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }
}

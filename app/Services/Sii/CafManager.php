<?php

namespace App\Services\Sii;

use App\Models\CafFolio;
use Illuminate\Database\Eloquent\Collection;
use SimpleXMLElement;

/**
 * Gestiona los archivos CAF (Código de Autorización de Folios) que el
 * contribuyente descarga desde el SII y sube al sistema.
 */
class CafManager
{
    /**
     * Procesa el XML del CAF descargado del SII y lo persiste en la base de datos.
     *
     * @throws \RuntimeException si el XML es inválido o el CAF ya está registrado.
     */
    public function importar(string $xmlCaf, int $ownerId): CafFolio
    {
        $xml = $this->parsearXml($xmlCaf);

        $tipoDoc = (int) $xml->CAF->DA->TD;
        $desdeF = (int) $xml->CAF->DA->RNG->D;
        $hastaF = (int) $xml->CAF->DA->RNG->H;
        $vencimiento = isset($xml->CAF->DA->FA)
            ? (string) $xml->CAF->DA->FA
            : null;

        // Evitar duplicados: mismo tipo + mismo rango
        $existente = CafFolio::where('owner_id', $ownerId)
            ->where('tipo_documento', $tipoDoc)
            ->where('folio_desde', $desdeF)
            ->where('folio_hasta', $hastaF)
            ->first();

        if ($existente) {
            throw new \RuntimeException(
                "El CAF para tipo {$tipoDoc} folios {$desdeF}-{$hastaF} ya está registrado."
            );
        }

        return CafFolio::create([
            'owner_id' => $ownerId,
            'tipo_documento' => $tipoDoc,
            'folio_desde' => $desdeF,
            'folio_hasta' => $hastaF,
            'folio_siguiente' => $desdeF,
            'xml_caf' => $xmlCaf,
            'fecha_vencimiento' => $vencimiento,
            'ambiente' => config('sii.ambiente', 'certificacion'),
            'agotado' => false,
        ]);
    }

    /**
     * Obtiene el próximo folio disponible para un tipo de documento.
     *
     * @throws \RuntimeException si no hay CAF vigente con folios disponibles.
     */
    public function obtenerSiguienteFolio(int $tipoDocumento, int $ownerId): array
    {
        $caf = CafFolio::where('owner_id', $ownerId)
            ->where('tipo_documento', $tipoDocumento)
            ->vigente()
            ->orderBy('folio_siguiente')
            ->lockForUpdate()
            ->first();

        if (! $caf) {
            throw new \RuntimeException(
                "No hay CAF vigente disponible para tipo de documento {$tipoDocumento}."
            );
        }

        $folio = $caf->reservarFolio();

        if (! $folio) {
            throw new \RuntimeException('El CAF encontrado no tiene folios disponibles.');
        }

        return ['folio' => $folio, 'caf' => $caf];
    }

    /** Parsea y valida el XML del CAF. */
    private function parsearXml(string $xmlCaf): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlCaf);

        if ($xml === false || ! isset($xml->CAF->DA->TD)) {
            $errores = libxml_get_errors();
            libxml_clear_errors();
            $msg = collect($errores)->pluck('message')->join('; ');

            throw new \RuntimeException("XML de CAF inválido: {$msg}");
        }

        return $xml;
    }

    /** Lista los CAFs de un owner agrupados por tipo de documento. */
    public function listarPorOwner(int $ownerId): Collection
    {
        return CafFolio::where('owner_id', $ownerId)
            ->orderBy('tipo_documento')
            ->orderBy('folio_desde')
            ->get();
    }
}

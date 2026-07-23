<?php

namespace App\Services\Sii;

use SimpleXMLElement;

/**
 * Genera el XML de un DTE tipo 33 (Factura Electrónica) según
 * el esquema XSD del SII de Chile.
 *
 * Referencia: Formato Documento Tributario Electrónico v2.1
 * https://www.sii.cl/factura_electronica/formato_dte.htm
 */
class DteXmlGenerator
{
    /** IVA vigente en Chile */
    private const IVA = 0.19; // Use config('taxes.iva_rate') for dynamic rate

    /**
     * @param  array{
     *   rut_emisor: string,
     *   razon_social_emisor: string,
     *   giro_emisor: string,
     *   direccion_emisor: string,
     *   comuna_emisor: string,
     *   ciudad_emisor: string,
     *   actividad_economica: int,
     *   rut_receptor: string,
     *   razon_social_receptor: string,
     *   giro_receptor: string,
     *   direccion_receptor: string,
     *   comuna_receptor: string,
     *   ciudad_receptor: string,
     *   fecha: string,
     *   folio: int,
     *   items: array<array{nombre: string, cantidad: int, precio_unitario: int, descuento?: int}>,
     *   referencia?: array{tipo_doc_ref: int, folio_ref: string, fecha_ref: string, razon: string}
     * }  $datos
     */
    public function generar(array $datos, int $tipoDocumento = 33): string
    {
        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="ISO-8859-1"?>'
            .'<DTE xmlns="http://www.sii.cl/SiiDte" version="1.0"></DTE>'
        );

        $documento = $xml->addChild('Documento');
        $documento->addAttribute('ID', "DTE-{$tipoDocumento}-{$datos['folio']}");

        $this->agregarEncabezado($documento, $datos, $tipoDocumento);
        $totales = $this->agregarDetalle($documento, $datos['items']);
        $this->actualizarTotalesEnEncabezado($documento, $totales, $tipoDocumento);

        if (! empty($datos['referencia'])) {
            $this->agregarReferencia($documento, $datos['referencia']);
        }

        // El TED (timbre) se agrega después de firmar; dejamos el nodo vacío.
        $documento->addChild('TED');

        return $this->formatearXml($xml->asXML());
    }

    private function agregarEncabezado(
        SimpleXMLElement $documento,
        array $datos,
        int $tipoDocumento
    ): void {
        $encabezado = $documento->addChild('Encabezado');

        // IdDoc
        $idDoc = $encabezado->addChild('IdDoc');
        $idDoc->addChild('TipoDTE', (string) $tipoDocumento);
        $idDoc->addChild('Folio', (string) $datos['folio']);
        $idDoc->addChild('FchEmis', $datos['fecha']);
        $idDoc->addChild('IndServicio', '3'); // 3 = servicios habituales
        $idDoc->addChild('MntBruto', '1');    // montos con IVA incluido

        // Emisor
        $emisor = $encabezado->addChild('Emisor');
        $emisor->addChild('RUTEmisor', $datos['rut_emisor']);
        $emisor->addChild('RznSoc', $datos['razon_social_emisor']);
        $emisor->addChild('GiroEmis', $datos['giro_emisor']);
        $emisor->addChild('Acteco', (string) $datos['actividad_economica']);
        $emisor->addChild('DirOrigen', $datos['direccion_emisor']);
        $emisor->addChild('CmnaOrigen', $datos['comuna_emisor']);
        $emisor->addChild('CiudadOrigen', $datos['ciudad_emisor']);

        // Receptor
        $receptor = $encabezado->addChild('Receptor');
        $receptor->addChild('RUTRecep', $datos['rut_receptor']);
        $receptor->addChild('RznSocRecep', $datos['razon_social_receptor']);
        $receptor->addChild('GiroRecep', $datos['giro_receptor'] ?? 'Sin giro');
        $receptor->addChild('DirRecep', $datos['direccion_receptor'] ?? '');
        $receptor->addChild('CmnaRecep', $datos['comuna_receptor'] ?? '');
        $receptor->addChild('CiudadRecep', $datos['ciudad_receptor'] ?? '');

        // Totales — se completan en actualizarTotalesEnEncabezado()
        $encabezado->addChild('Totales');
    }

    /**
     * @param  array<array{nombre: string, cantidad: int, precio_unitario: int, descuento?: int}>  $items
     * @return array{monto_neto: int, monto_iva: int, monto_total: int}
     */
    private function agregarDetalle(SimpleXMLElement $documento, array $items): array
    {
        $montoNeto = 0;

        foreach ($items as $numero => $item) {
            $descuento = $item['descuento'] ?? 0;
            $precioUnit = $item['precio_unitario'];
            $cantidad = $item['cantidad'];
            $subtotalItem = ($precioUnit * $cantidad) - $descuento;

            $montoNeto += $subtotalItem;

            $detalle = $documento->addChild('Detalle');
            $detalle->addChild('NroLinDet', (string) ($numero + 1));
            $detalle->addChild('NmbItem', htmlspecialchars($item['nombre']));
            $detalle->addChild('QtyItem', (string) $cantidad);
            $detalle->addChild('PrcItem', (string) $precioUnit);

            if ($descuento > 0) {
                $detalle->addChild('DescuentoMonto', (string) $descuento);
            }

            $detalle->addChild('MontoItem', (string) $subtotalItem);
        }

        $montoIva = (int) round($montoNeto * self::IVA);
        $montoTotal = $montoNeto + $montoIva;

        return [
            'monto_neto' => $montoNeto,
            'monto_iva' => $montoIva,
            'monto_total' => $montoTotal,
        ];
    }

    private function actualizarTotalesEnEncabezado(
        SimpleXMLElement $documento,
        array $totales,
        int $tipoDocumento
    ): void {
        $totalesNode = $documento->Encabezado->Totales;

        $totalesNode->addChild('MntNeto', (string) $totales['monto_neto']);

        // Tipo 34 es exento de IVA
        if ($tipoDocumento !== 34) {
            $totalesNode->addChild('TasaIVA', '19');
            $totalesNode->addChild('IVA', (string) $totales['monto_iva']);
        } else {
            $totalesNode->addChild('MntExe', (string) $totales['monto_neto']);
        }

        $totalesNode->addChild('MntTotal', (string) $totales['monto_total']);
    }

    private function agregarReferencia(SimpleXMLElement $documento, array $ref): void
    {
        $referencia = $documento->addChild('Referencia');
        $referencia->addChild('NroLinRef', '1');
        $referencia->addChild('TpoDocRef', (string) $ref['tipo_doc_ref']);
        $referencia->addChild('FolioRef', $ref['folio_ref']);
        $referencia->addChild('FchRef', $ref['fecha_ref']);
        $referencia->addChild('RazonRef', $ref['razon']);
    }

    private function formatearXml(string $xml): string
    {
        $dom = new \DOMDocument('1.0', 'ISO-8859-1');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);

        return $dom->saveXML();
    }
}

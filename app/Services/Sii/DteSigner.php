<?php

namespace App\Services\Sii;

use DOMDocument;
use Exception;
use SimpleXMLElement;

/**
 * Servicio para firmar digitalmente documentos tributarios electrónicos (DTE)
 * y generar el Timbre Electrónico DTE (TED).
 */
class DteSigner
{
    /**
     * Firma un XML de DTE.
     *
     * @param  string  $xml  Unsigned XML content.
     * @param  string  $pfxPath  Absolute path to the .pfx file.
     * @param  string  $pfxPassword  Password of the .pfx certificate.
     * @return string Signed XML content.
     */
    public function firmarDte(string $xml, string $pfxPath, string $pfxPassword = ''): string
    {
        $certs = $this->leerCertificado($pfxPath, $pfxPassword);

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        // La firma del DTE se realiza sobre el nodo <Documento>
        $nodeToSign = $dom->getElementsByTagName('Documento')->item(0);
        if (! $nodeToSign) {
            throw new Exception('No se encontró el nodo <Documento> para firmar.');
        }

        $id = $nodeToSign->getAttribute('ID');

        // Canonicalización del nodo a firmar
        $c14n = $nodeToSign->C14N(true, false);
        $digest = base64_encode(sha1($c14n, true));

        // Construir la estructura de la Firma Digital (DSig)
        $signature = $dom->createElement('Signature');
        $signature->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
        $dom->documentElement->appendChild($signature);

        $signedInfo = $dom->createElement('SignedInfo');
        $signature->appendChild($signedInfo);

        $canonicalizationMethod = $dom->createElement('CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonicalizationMethod);

        $signatureMethod = $dom->createElement('SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($signatureMethod);

        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', "#{$id}");
        $signedInfo->appendChild($reference);

        $transforms = $dom->createElement('Transforms');
        $reference->appendChild($transforms);

        $transform = $dom->createElement('Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);

        $digestMethod = $dom->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digestMethod);

        $digestValue = $dom->createElement('DigestValue', $digest);
        $reference->appendChild($digestValue);

        // Generar la firma RSA-SHA1 sobre SignedInfo
        $signedInfoC14N = $signedInfo->C14N(true, false);
        openssl_sign($signedInfoC14N, $signatureValueBinary, $certs['pkey'], OPENSSL_ALGO_SHA1);

        $signatureValue = $dom->createElement('SignatureValue', base64_encode($signatureValueBinary));
        $signature->appendChild($signatureValue);

        $keyInfo = $dom->createElement('KeyInfo');
        $signature->appendChild($keyInfo);

        $x509Data = $dom->createElement('X509Data');
        $keyInfo->appendChild($x509Data);

        // El SII requiere el certificado limpio (sin headers/footers) en X509Certificate
        $cleanCert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"], '', $certs['cert']);
        $x509Certificate = $dom->createElement('X509Certificate', $cleanCert);
        $x509Data->appendChild($x509Certificate);

        return $dom->saveXML();
    }

    /**
     * Genera el Timbre Electrónico DTE (TED) que va dentro del XML y se usa para el QR.
     */
    public function generarTed(SimpleXMLElement $xml, string $xmlCaf): string
    {
        $caf = simplexml_load_string($xmlCaf);
        $privateKeyCaf = (string) $caf->CAF->DA->RSAPK;

        // El TED requiere un subconjunto de datos del DTE y el CAF
        // Nota: El formato del DD (Documento Digital) para el TED es muy estricto en espacios.
        $dd = $this->construirDD($xml, $caf);

        // El SII usa RSA-SHA1 para firmar el DD con la clave privada del CAF
        $pkey = openssl_get_privatekey($privateKeyCaf);
        openssl_sign($dd, $signatureBinary, $pkey, OPENSSL_ALGO_SHA1);
        $signatureBase64 = base64_encode($signatureBinary);

        // Retornar el nodo <TED> completo
        return '<TED version="1.0">'.
                 '<DD>'.$dd.'</DD>'.
                 '<FRMT algoritmo="SHA1withRSA">'.$signatureBase64.'</FRMT>'.
               '</TED>';
    }

    private function leerCertificado(string $path, string $password): array
    {
        if (! file_exists($path)) {
            throw new Exception("Archivo de certificado no encontrado en: {$path}");
        }

        $pfx = file_get_contents($path);
        $certs = [];
        if (! openssl_pkcs12_read($pfx, $certs, $password)) {
            throw new Exception('No se pudo leer el archivo .pfx con la clave proporcionada.');
        }

        return $certs;
    }

    /**
     * Construye el contenido del nodo <DD> (Datos DTE) para el Timbre.
     */
    private function construirDD(SimpleXMLElement $xml, SimpleXMLElement $caf): string
    {
        // Extraemos datos necesarios del XML del DTE
        $tipoDoc = (string) $xml->Documento->Encabezado->IdDoc->TipoDTE;
        $folio = (string) $xml->Documento->Encabezado->IdDoc->Folio;
        $fchEmis = (string) $xml->Documento->Encabezado->IdDoc->FchEmis;
        $rutRecep = (string) $xml->Documento->Encabezado->IdDoc->RUTRecep;
        $mntTotal = (string) $xml->Documento->Encabezado->Totales->MntTotal;
        $rznSocRecep = (string) $xml->Documento->Encabezado->Receptor->RznSocRecep;

        // Razón Social Receptor (truncar a 40 chars para el timbre)
        $rznSocRecep = substr($rznSocRecep, 0, 40);

        // Primer ítem para el timbre
        $nmbItem = (string) $xml->Documento->Detalle[0]->NmbItem;
        $nmbItem = substr($nmbItem, 0, 40);

        // Datos del CAF
        $re = (string) $caf->CAF->DA->RE; // RUT Emisor
        $td = (string) $caf->CAF->DA->TD; // Tipo Doc
        $d = (string) $caf->CAF->DA->RNG->D; // Desde
        $h = (string) $caf->CAF->DA->RNG->H; // Hasta
        $fa = (string) $caf->CAF->DA->FA; // Fecha Autorización

        // Nota: SimpleXML no mantiene el formato exacto del nodo <CAF>
        // El SII exige que el nodo <CAF> vaya EXACTO como se recibió.
        $cafFragment = $caf->CAF->asXML();
        $cafFragment = str_replace(['<?xml version="1.0"?>', '<?xml version="1.0" encoding="ISO-8859-1"?>'], '', $cafFragment);
        $cafFragment = trim($cafFragment);

        // Estructura DD del SII
        return "<RE>{$re}</RE>".
               "<TD>{$td}</TD>".
               "<F>{$folio}</F>".
               "<FE>{$fchEmis}</FE>".
               "<RR>{$rutRecep}</RR>".
               "<RSR>{$rznSocRecep}</RSR>".
               "<MNT>{$mntTotal}</MNT>".
               "<IT1>{$nmbItem}</IT1>".
               $cafFragment.
               '<TSTZ>'.date('Y-m-d\TH:i:s').'</TSTZ>';
    }
}

<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SiiService
{
    protected string $certPath;

    protected string $password;

    protected string $urlSemilla;

    protected string $urlToken;

    public function __construct()
    {
        $ambiente = config('sii.ambiente', 'certificacion');

        $this->certPath = storage_path('app/private/'.config('sii.certificado.ruta'));
        $this->password = config('sii.certificado.clave', '');
        $this->urlSemilla = config("sii.urls.{$ambiente}.semilla");
        $this->urlToken = config("sii.urls.{$ambiente}.token");
    }

    /**
     * Obtiene el Token de autenticación del SII.
     * Usa caché para evitar solicitar un nuevo token en cada operación.
     */
    public function obtenerToken(): string
    {
        $ttl = config('sii.token_ttl', 240);

        return Cache::remember('sii_token', $ttl, function () {
            return $this->solicitarTokenAlSii();
        });
    }

    /**
     * Fuerza la obtención de un token nuevo (invalida caché).
     */
    public function refrescarToken(): string
    {
        Cache::forget('sii_token');

        return $this->obtenerToken();
    }

    /**
     * Solicita un token nuevo al SII (Semilla → Firma → Token).
     */
    private function solicitarTokenAlSii(): string
    {
        // 1. Obtener Semilla (SOAP)
        $soapSeed = '<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:def="http://DefaultNamespace"><soapenv:Body><def:getSeed soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/></soapenv:Body></soapenv:Envelope>';

        $resSeed = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'text/xml;charset=UTF-8', 'SOAPAction' => '""'])
            ->withBody($soapSeed, 'text/xml')
            ->post($this->urlSemilla);

        if (! $resSeed->successful()) {
            throw new Exception("Error al obtener semilla (SOAP). Status: {$resSeed->status()}");
        }

        $semilla = $this->extraerValorSoap($resSeed->body(), 'getSeedReturn', 'SEMILLA');

        if (empty($semilla)) {
            throw new Exception('No se pudo obtener la semilla del SII.');
        }

        // 2. Firmar Semilla
        $signedXml = $this->firmarSemilla($semilla);

        // 3. Obtener Token (SOAP)
        $soapToken = '<?xml version="1.0" encoding="UTF-8"?>'.
            '<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:def="http://DefaultNamespace">'.
            '<soapenv:Body><def:getToken soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">'.
            '<pszXml xsi:type="xsd:string"><![CDATA['.$signedXml.']]></pszXml>'.
            '</def:getToken></soapenv:Body></soapenv:Envelope>';

        $resToken = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'text/xml;charset=UTF-8', 'SOAPAction' => '""'])
            ->withBody($soapToken, 'text/xml')
            ->post($this->urlToken);

        if (! $resToken->successful()) {
            throw new Exception("Error al obtener token (SOAP). Status: {$resToken->status()}");
        }

        $token = $this->extraerValorSoap($resToken->body(), 'getTokenReturn', 'TOKEN');

        if (! empty($token)) {
            return $token;
        }

        $glosa = $this->extraerValorSoap($resToken->body(), 'getTokenReturn', 'GLOSA');

        throw new Exception('Error SII: '.($glosa ?: 'Desconocido'));
    }

    /**
     * Extrae un valor del XML interno de una respuesta SOAP del SII.
     */
    private function extraerValorSoap(string $body, string $returnTag, string $element): string
    {
        if (preg_match("/<ns1:{$returnTag}[^>]*>(.*)<\\/ns1:{$returnTag}>/s", $body, $matches)) {
            $xml = simplexml_load_string(html_entity_decode($matches[1]));

            if ($xml !== false) {
                $result = $xml->xpath("//*[local-name()=\"{$element}\"]");

                return (string) ($result[0] ?? '');
            }
        }

        return '';
    }

    /**
     * Firma la semilla con el certificado digital (.pfx).
     *
     * IMPORTANTE: El SignedInfo lleva xmlns para la firma (C14N),
     * pero NO en el XML final (hereda del padre Signature).
     */
    private function firmarSemilla(string $semilla): string
    {
        if (! file_exists($this->certPath)) {
            throw new Exception("Certificado no encontrado en: {$this->certPath}");
        }

        $pfx = file_get_contents($this->certPath);
        $certs = [];

        if (! openssl_pkcs12_read($pfx, $certs, $this->password)) {
            throw new Exception('No se pudo leer el PFX. Verifique la contraseña.');
        }

        $privateKey = $certs['pkey'];
        $publicCert = $certs['cert'];
        $certBody = str_replace(
            ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r", ' '],
            '',
            $publicCert
        );

        // 1. XML base para el Digest
        $data = '<getToken><item><Semilla>'.$semilla.'</Semilla></item></getToken>';
        $digestValue = base64_encode(sha1($data, true));

        // 2. SignedInfo CON xmlns (para firmar — C14N lo requiere)
        $signedInfoCore = '<CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"></CanonicalizationMethod>'.
            '<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod>'.
            '<Reference URI="">'.
            '<Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></Transform></Transforms>'.
            '<DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod>'.
            '<DigestValue>'.$digestValue.'</DigestValue>'.
            '</Reference>';

        $signedInfoForSigning = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">'.$signedInfoCore.'</SignedInfo>';

        // 3. Firmar
        openssl_sign($signedInfoForSigning, $signature, $privateKey, OPENSSL_ALGO_SHA1);
        $signatureValue = base64_encode($signature);

        // 4. SignedInfo SIN xmlns (hereda del padre Signature en el XML final)
        $signedInfoForXml = '<SignedInfo>'.$signedInfoCore.'</SignedInfo>';

        // 5. Ensamblaje final
        return '<getToken>'.
            '<item><Semilla>'.$semilla.'</Semilla></item>'.
            '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">'.
            $signedInfoForXml.
            '<SignatureValue>'.$signatureValue.'</SignatureValue>'.
            '<KeyInfo><X509Data><X509Certificate>'.$certBody.'</X509Certificate></X509Data></KeyInfo>'.
            '</Signature>'.
            '</getToken>';
    }

    /**
     * Consulta datos reales de una empresa por su RUT.
     * En una integración real, aquí llamarías a un proveedor de datos o al SII.
     */
    public function consultarDatosReales(string $rut): array
    {
        $rutLimpio = preg_replace('/[^k0-9]/i', '', $rut);
        $dv = strtoupper(substr($rutLimpio, -1));
        $numero = substr($rutLimpio, 0, -1);
        $fullRut = "{$numero}-{$dv}";

        // Lista de proveedores públicos/demo para intentar (estrategy pattern simple)
        $providers = [
            "https://api.libreapi.cl/rut/activities?id={$fullRut}",
            "https://api.rutify.cl/v1/{$fullRut}", // Alternativa común
        ];

        foreach ($providers as $url) {
            try {
                $response = Http::timeout(4)->get($url);

                if ($response->successful()) {
                    $json = $response->json();

                    // Adaptamos según el formato de LibreAPI (que es el más completo)
                    $data = $json['data'] ?? $json; // Algunos devuelven el objeto directo

                    if (! empty($data['name']) || ! empty($data['razon_social'])) {
                        return [
                            'success' => true,
                            'is_contribuyente' => ! empty($data['activities']) || ! empty($data['giros']),
                            'razon_social' => $data['name'] ?? $data['razon_social'] ?? 'NO ENCONTRADO',
                            'giro' => $data['activities'][0]['name'] ?? $data['giros'][0]['glosa'] ?? 'GIRO NO DISPONIBLE',
                            'giros' => $data['activities'] ?? $data['giros'] ?? [],
                            'direccion' => $data['address'] ?? $data['direccion'] ?? '',
                            'comuna' => $data['commune'] ?? $data['comuna'] ?? '',
                        ];
                    }
                }
            } catch (Exception $e) {
                continue; // Si un proveedor falla, intentamos el siguiente
            }
        }

        return ['success' => false];
    }
}

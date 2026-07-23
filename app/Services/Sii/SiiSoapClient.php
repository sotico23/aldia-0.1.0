<?php

namespace App\Services\Sii;

use Exception;
use Illuminate\Support\Facades\Http;

/**
 * Cliente para interactuar con los Web Services del SII (SII de Chile).
 * Maneja Autenticación (Semilla -> Token) y Envío de DTEs.
 */
class SiiSoapClient
{
    /**
     * Obtiene una Semilla (Seed) del SII para iniciar la autenticación.
     */
    public function getSeed(string $url): string
    {
        $response = Http::timeout(15)->connectTimeout(5)->get($url);

        if ($response->failed()) {
            throw new Exception('Error al conectar con el SII para obtener semilla: '.$response->body());
        }

        $xml = simplexml_load_string($response->body());
        if (! $xml || ! isset($xml->RETORNO->SEMILLA)) {
            throw new Exception('Respuesta del SII inválida al obtener semilla: '.$response->body());
        }

        return (string) $xml->RETORNO->SEMILLA;
    }

    /**
     * Obtiene un Token de sesión (Token) enviando la semilla firmada.
     *
     * @param  string  $url  URL del endpoint getToken
     * @param  string  $signedSeedXml  XML <getToken><item><semilla>...</semilla></item></getToken> firmado
     */
    public function getToken(string $url, string $signedSeedXml): string
    {
        // El SII espera el XML firmado como cuerpo del POST
        $response = Http::timeout(15)->connectTimeout(5)->withHeaders([
            'Content-Type' => 'application/xml',
        ])->send('POST', $url, [
            'body' => $signedSeedXml,
        ]);

        if ($response->failed()) {
            throw new Exception('Error al conectar con el SII para obtener token: '.$response->body());
        }

        $xml = simplexml_load_string($response->body());
        if (! $xml || ! isset($xml->RETORNO->TOKEN)) {
            // A veces el SII retorna errores dentro del XML
            $glosa = (string) ($xml->RETORNO->GLOSA ?? 'Error desconocido');
            throw new Exception("SII denegó el token: {$glosa}");
        }

        return (string) $xml->RETORNO->TOKEN;
    }

    /**
     * Envía un sobre (EnvioDTE) al SII.
     *
     * @param  string  $url  URL del endpoint de recepción
     * @param  string  $token  Token de autenticación obtenido previamente
     * @param  string  $rutEmisor  RUT de la empresa (sin puntos, con guión)
     * @param  string  $rutEnvia  RUT de la persona que firma (vínculo certificado)
     * @param  string  $xmlSobre  XML del sobre <EnvioDTE> firmado
     * @param  string  $fileName  Nombre sugerido para el archivo
     */
    public function enviarSobre(
        string $url,
        string $token,
        string $rutEmisor,
        string $rutEnvia,
        string $xmlSobre,
        string $fileName = 'envio.xml'
    ): array {
        // El SII usa multipart/form-data para la subida del sobre
        // RUTs deben ir separados por componente (Cuerpo y DV)
        [$rutCompanyCorp, $rutCompanyDv] = explode('-', $rutEmisor);
        [$rutSenderCorp, $rutSenderDv] = explode('-', $rutEnvia);

        $response = Http::timeout(30)->connectTimeout(5)->withHeaders([
            'Cookie' => "TOKEN={$token}", // Autenticación vía Cookie
            'User-Agent' => 'Mozilla/4.0 (compatible; PROYECTO ERP ALDIA)',
        ])->asMultipart()
            ->post($url, [
                [
                    'name' => 'rutSender',
                    'contents' => $rutSenderCorp,
                ],
                [
                    'name' => 'dvSender',
                    'contents' => $rutSenderDv,
                ],
                [
                    'name' => 'rutCompany',
                    'contents' => $rutCompanyCorp,
                ],
                [
                    'name' => 'dvCompany',
                    'contents' => $rutCompanyDv,
                ],
                [
                    'name' => 'archivo',
                    'contents' => $xmlSobre,
                    'filename' => $fileName,
                ],
            ]);

        if ($response->failed()) {
            throw new Exception('Error de conexión al enviar sobre al SII: '.$response->status());
        }

        $xml = simplexml_load_string($response->body());
        if (! $xml) {
            throw new Exception('Respuesta del SII no es un XML válido: '.$response->body());
        }

        // Si el estado es 0, el envío fue recibido correctamente y entrega un TrackID
        return [
            'status' => (string) $xml->STATUS,
            'track_id' => (string) $xml->TRACKID,
            'response' => $response->body(),
        ];
    }
}

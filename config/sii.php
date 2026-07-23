<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Certificado Digital (.pfx)
    |--------------------------------------------------------------------------
    | Archivo sin protección de clave SMS para permitir firma automática
    | en facturación masiva / POS sin intervención humana.
    | Sube el .pfx a storage/app/private/sii/ y nunca lo incluyas en el repositorio.
    */

    'certificado' => [
        'ruta' => env('SII_CERT_PATH', 'sii/certificado_std.pfx'),
        'clave' => env('SII_CERT_PASSWORD', ''),
        'rut_emisor' => env('SII_RUT_EMISOR', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ambiente
    |--------------------------------------------------------------------------
    | 'certificacion' o 'produccion'. Controla qué URLs SOAP se usan.
    */

    'ambiente' => env('SII_AMBIENTE', 'certificacion'),

    /*
    |--------------------------------------------------------------------------
    | Web Services SOAP – Servicio de Impuestos Internos (Chile)
    |--------------------------------------------------------------------------
    | URLs de los Web Services SOAP del SII por ambiente.
    */

    'urls' => [
        'certificacion' => [
            'semilla' => 'https://maullin.sii.cl/DTEWS/CrSeed.jws',
            'token' => 'https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws',
            'envio_dte' => 'https://maullin.sii.cl/cgi_dte/UPL/DTEUpload',
        ],
        'produccion' => [
            'semilla' => 'https://palena.sii.cl/DTEWS/CrSeed.jws',
            'token' => 'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws',
            'envio_dte' => 'https://palena.sii.cl/cgi_dte/UPL/DTEUpload',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caché del Token
    |--------------------------------------------------------------------------
    | El token del SII tiene una vida útil corta (~5 minutos).
    | token_ttl: segundos que se mantiene en caché (default: 4 min = 240s).
    */

    'token_ttl' => env('SII_TOKEN_TTL', 240),

];

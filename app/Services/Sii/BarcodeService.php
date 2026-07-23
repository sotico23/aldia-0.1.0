<?php

namespace App\Services\Sii;

use Com\Tecnick\Barcode\Barcode;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Genera el código de barras PDF417 para el Timbre Electrónico DTE (TED).
 */
class BarcodeService
{
    /**
     * Genera la imagen del PDF417 en formato SVG (recomendado para PDF) o base64.
     *
     * @param  string  $ted  Contenido del nodo <TED> completo.
     * @return string Imagen en formato SVG.
     */
    public function generarPdf417(string $ted): string
    {
        try {
            // Eliminar espacios innecesarios
            $ted = trim($ted);

            // Usamos la librería tc-lib-barcode (tecnickcom/tc-lib-barcode)
            $barcode = new Barcode;
            $bobj = $barcode->getBarcodeObj(
                'PDF417',
                $ted,
                -3, // width factor
                -3, // height factor
                'black',
                [0, 0, 0, 0] // padding
            );

            return $bobj->getSvgCode();
        } catch (Exception $e) {
            Log::error('Error generando PDF417: '.$e->getMessage());
            throw $e;
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Product;

class SubirProductosLocales extends Command
{
    protected $signature = 'productos:subir';
    protected $description = 'Sube productos masivamente adaptados al esquema real de la base de datos';

    public function handle()
    {
        $carpetaTextos = public_path('textos_productos');
        $carpetaImagenes = public_path('imagenes_maxiofertas');

        if (!File::exists($carpetaTextos)) {
            $this->error("La carpeta de textos no existe en: {$carpetaTextos}");
            return;
        }

        $archivosTxt = File::files($carpetaTextos);
        $this->info("Procesando " . count($archivosTxt) . " productos...");

        foreach ($archivosTxt as $archivoTxt) {
            $pathTxt = $archivoTxt->getPathname();
            $nombreArchivoSinExt = pathinfo($archivoTxt->getFilename(), PATHINFO_FILENAME);
            
            $this->info("Procesando: " . $archivoTxt->getFilename());

            $contenido = File::get($pathTxt);

            // 1. Extraer los datos básicos del archivo txt
            $titulo = $this->extraerSeccion($contenido, 'TÍTULO:', 'PRECIO:');
            $precioStr = $this->extraerSeccion($contenido, 'PRECIO:', 'ENLACE:');
            $enlace = $this->extraerSeccion($contenido, 'ENLACE:', 'DESCRIPCIÓN:');
            $descripcionOriginal = $this->extraerSeccion($contenido, 'DESCRIPCIÓN:', null);

            // Limpiar precio
            $precioLimpio = preg_replace('/[^0-9.]/', '', $precioStr);
            $precioFinal = is_numeric($precioLimpio) ? floatval($precioLimpio) : 0.00;

            // 2. Parafrasear, añadir emojis e invitación a la tienda
            $descripcionMejorada = $this->parafrasearConEmojis($titulo, $descripcionOriginal);

            // 3. Vincular la imagen exacta de forma segura en storage/app/public/productos
            $imagenRelativa = null;
            if (File::exists($carpetaImagenes)) {
                $archivosImg = File::files($carpetaImagenes);
                foreach ($archivosImg as $img) {
                    if (pathinfo($img->getFilename(), PATHINFO_FILENAME) === $nombreArchivoSinExt) {
                        $nombreArchivoUnico = time() . '_' . $img->getFilename();
                        $destinoStorage = storage_path('app/public/productos/' . $nombreArchivoUnico);
                        
                        if (!File::exists(storage_path('app/public/productos'))) {
                            File::makeDirectory(storage_path('app/public/productos'), 0755, true, true);
                        }

                        File::copy($img->getPathname(), $destinoStorage);
                        $imagenRelativa = 'productos/' . $nombreArchivoUnico;
                        break;
                    }
                }
            }

            // 4. Guardar en Base de Datos usando estrictamente los campos reales de la tabla
            \App\Models\Producto::updateOrCreate(
                ['nombre' => trim($titulo)], // Usando el campo 'nombre' real
                [
                    'descripcion' => $descripcionMejorada,
                    'precio_venta' => $precioFinal,
                    'stock_minimo' => 10,
                    'imagen' => $imagenRelativa,
                    'activo' => true,
                    'unidad_medida' => 'unidad',
                    // Si requieres guardar código basado en el slug o identificador:
                    'codigo' => Str::slug($titulo) . '-' . rand(100, 999),
                ]
            );
        }

        $this->info('¡Proceso completado con éxito! Todos los productos se subieron usando los campos reales.');
    }

    /**
     * Función para parafrasear, agregar emojis y la invitación al catálogo
     */
    private function parafrasearConEmojis($titulo, $descripcion)
    {
        $descripcionLimpia = trim(preg_replace('/\s+/', ' ', $descripcion));

        if (empty($descripcionLimpia) || $descripcionLimpia === "Sin descripción disponible.") {
            $descripcionLimpia = "Un producto excelente y de gran utilidad diseñado para facilitarte el día a día.";
        }

        $paratexto = "🔥 **¡No te quedes sin el tuyo!**\n\n";
        $paratexto .= "🌟 **Producto:** {$titulo}\n\n";
        $paratexto .= "📝 **Descripción:** {$descripcionLimpia}\n\n";
        $paratexto .= "✅ Calidad garantizada y despacho rápido.\n\n";
        $paratexto .= "🛍️ **¿Buscas más ofertas increíbles?** Explora nuestro catálogo completo y descubre todo lo que tenemos disponible para ti en 👉 [RedCliente Store](https://redcliente.cl/tienda/RedCLienteStore) 🚀";

        return $paratexto;
    }

    private function extraerSeccion($texto, $inicio, $fin)
    {
        $string = '';
        $inicioPos = strpos($texto, $inicio);
        if ($inicioPos === false) return '';
        
        $inicioPos += strlen($inicio);
        
        if ($fin !== null) {
            $finPos = strpos($texto, $fin, $inicioPos);
            if ($finPos === false) {
                $string = substr($texto, $inicioPos);
            } else {
                $string = substr($texto, $inicioPos, $finPos - $inicioPos);
            }
        } else {
            $string = substr($texto, $inicioPos);
        }

        return trim($string);
    }
}
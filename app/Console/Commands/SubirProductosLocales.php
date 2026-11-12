<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Producto; // Asegúrate de que tu modelo coincida

class SubirProductosLocales extends Command
{
    protected $signature = 'productos:subir';
    protected $description = 'Sube productos masivamente, asigna almacén Santiago, stock mínimo 10, comisiones por rangos y enlace a RedCliente Store';

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

            // 2. Calcular la comisión del cajero según el rango de precios de Santiago
            $comisionCajero = $this->calcularComisionSantiago($precioFinal);

            // 3. Parafrasear, añadir emojis e invitación a la tienda
            $descripcionMejorada = $this->parafrasearConEmojis($titulo, $descripcionOriginal);

            // 4. Vincular la imagen exacta de forma segura en storage/app/public/productos
            $imagenRelativa = null;
            if (File::exists($carpetaImagenes)) {
                $archivosImg = File::files($carpetaImagenes);
                foreach ($archivosImg as $img) {
                    if (pathinfo($img->getFilename(), PATHINFO_FILENAME) === $nombreArchivoSinExt) {
                        // Siguiendo la ruta estándar del controlador: storage/app/public/productos
                        $nombreArchivoUnico = time() . '_' . $img->getFilename();
                        $destinoStorage = storage_path('app/public/productos/' . $nombreArchivoUnico);
                        
                        if (!File::exists(storage_path('app/public/productos'))) {
                            File::makeDirectory(storage_path('app/public/productos'), 0755, true, true);
                        }

                        File::copy($img->getPathname(), $destinoStorage);
                        
                        // Guardamos la ruta relativa para el campo de imagen de la BD
                        $imagenRelativa = 'productos/' . $nombreArchivoUnico;
                        break;
                    }
                }
            }

            // 5. Guardar en Base de Datos aplicando la estructura del ciclo de vida del producto
            \App\Models\Producto::updateOrCreate(
                ['name' => trim($titulo)],
                [
                    'slug' => Str::slug($titulo),
                    'price' => $precioFinal,
                    'description' => $descripcionMejorada,
                    'image' => $imagenRelativa,
                    'source_link' => trim($enlace),
                    'warehouse' => 'Santiago',           // Almacén Santiago requerido
                    'min_stock' => 10,                   // Stock mínimo por defecto
                    'cashier_commission' => $comisionCajero, // Comisión calculada por rangos
                    // Si tu controlador asigna valores por defecto para owner_id o user_id, 
                    // puedes descomentar e incluir los IDs correspondientes aquí si tu BD los exige:
                    // 'owner_id' => 1,
                    // 'user_id' => 1,
                ]
            );
        }

        $this->info('¡Proceso completado con éxito! Todos los productos se subieron alineados al flujo de Santiago.');
    }

    /**
     * Calcula la comisión del cajero basándose en los rangos de la tabla de Santiago
     */
    private function calcularComisionSantiago($precio)
    {
        if ($precio >= 1000 && $precio <= 10500) {
            return 1350.00;
        } elseif ($precio >= 11000 && $precio <= 22500) {
            return 1850.00;
        } elseif ($precio >= 23000 && $precio <= 32500) {
            return 2350.00;
        } elseif ($precio >= 33000) {
            return 2850.00;
        }
        
        return 0.00;
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
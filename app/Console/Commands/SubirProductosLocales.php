namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Product;

class SubirProductosLocales extends Command
{
    protected $signature = 'productos:subir';
    protected $description = 'Sube productos, parafrasea descripciones, añade emojis, vincula fotos e incluye el enlace a RedCliente Store';

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

            // 1. Extraer los datos básicos
            $titulo = $this->extraerSeccion($contenido, 'TÍTULO:', 'PRECIO:');
            $precioStr = $this->extraerSeccion($contenido, 'PRECIO:', 'ENLACE:');
            $enlace = $this->extraerSeccion($contenido, 'ENLACE:', 'DESCRIPCIÓN:');
            $descripcionOriginal = $this->extraerSeccion($contenido, 'DESCRIPCIÓN:', null);

            $precioLimpio = preg_replace('/[^0-9.]/', '', $precioStr);
            $precioFinal = is_numeric($precioLimpio) ? $precioLimpio : 0.00;

            // 2. Parafrasear, añadir emojis e invitación a la tienda
            $descripcionMejorada = $this->parafrasearConEmojis($titulo, $descripcionOriginal);

            // 3. Vincular la imagen exacta de forma segura
            $imagenRelativa = null;
            if (File::exists($carpetaImagenes)) {
                $archivosImg = File::files($carpetaImagenes);
                foreach ($archivosImg as $img) {
                    if (pathinfo($img->getFilename(), PATHINFO_FILENAME) === $nombreArchivoSinExt) {
                        $nombreImgDestino = 'productos/' . time() . '_' . $img->getFilename();
                        
                        if (!File::exists(public_path('storage/productos'))) {
                            File::makeDirectory(public_path('storage/productos'), 0755, true, true);
                        }

                        File::copy($img->getPathname(), public_path($nombreImgDestino));
                        $imagenRelativa = $nombreImgDestino;
                        break;
                    }
                }
            }

            // 4. Guardar en Base de Datos
            Product::updateOrCreate(
                ['name' => trim($titulo)],
                [
                    'slug' => Str::slug($titulo),
                    'price' => $precioFinal,
                    'description' => $descripcionMejorada,
                    'image' => $imagenRelativa,
                    'source_link' => trim($enlace)
                ]
            );
        }

        $this->info('¡Proceso completado! Productos subidos con descripciones renovadas, emojis y enlace a RedCliente Store.');
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

        // Estructura comercial con emojis y llamada a la acción integrada
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
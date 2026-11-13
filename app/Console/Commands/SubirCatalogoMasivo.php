<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Producto;
use App\Models\Inventario;
use App\Models\Almacen;

class SubirProductosLocales extends Command
{
    protected $signature = 'productos:subir {--textos=textos_productos : Carpeta de textos dentro de public} {--imagenes=imagenes_maxiofertas : Carpeta de imágenes dentro de public}';
    protected $description = 'Sube productos masivamente adaptados al esquema real, asignando owner 1 e inventario inicial de 1000.';

    public function handle()
    {
        $nombreCarpetaTextos = $this->option('textos');
        $nombreCarpetaImagenes = $this->option('imagenes');

        $carpetaTextos = public_path($nombreCarpetaTextos);
        $carpetaImagenes = public_path($nombreCarpetaImagenes);

        if (!File::exists($carpetaTextos)) {
            $this->error("La carpeta de textos no existe en: {$carpetaTextos}");
            return;
        }

        // Obtener el almacén principal o del owner 1
        $almacen = Almacen::where('owner_id', 1)->first() ?? Almacen::first();
        if (!$almacen) {
            $this->error("No se encontró ningún almacén en la base de datos. Crea uno primero.");
            return;
        }

        $archivosTxt = File::files($carpetaTextos);
        $this->info("Procesando " . count($archivosTxt) . " productos desde '{$nombreCarpetaTextos}'...");

        $bar = $this->output->createProgressBar(count($archivosTxt));
        $bar->start();

        foreach ($archivosTxt as $archivoTxt) {
            $pathTxt = $archivoTxt->getPathname();
            $nombreArchivoSinExt = pathinfo($archivoTxt->getFilename(), PATHINFO_FILENAME);

            $contenido = File::get($pathTxt);

            // 1. Extraer los datos básicos del archivo txt usando tus métodos seguros
            $titulo = $this->extraerSeccion($contenido, 'TÍTULO:', 'PRECIO:');
            $precioStr = $this->extraerSeccion($contenido, 'PRECIO:', 'ENLACE:');
            $enlace = $this->extraerSeccion($contenido, 'ENLACE:', 'DESCRIPCIÓN:');
            $descripcionOriginal = $this->extraerSeccion($contenido, 'DESCRIPCIÓN:', null);

            if (empty($titulo)) {
                $bar->advance();
                continue;
            }

            // Limpiar precio
            $precioLimpio = preg_replace('/[^0-9.]/', '', $precioStr);
            $precioFinal = is_numeric($precioLimpio) ? floatval($precioLimpio) : 0.00;

            // 2. Parafrasear, añadir emojis e invitación a la tienda
            $descripcionMejorada = $this->parafrasearConEmojis($titulo, $descripcionOriginal);

            // 3. Vincular la imagen exacta en storage/app/public/productos
            $imagenRelativa = null;
            if (File::exists($carpetaImagenes)) {
                $archivosImg = File::files($carpetaImagenes);
                foreach ($archivosImg as $img) {
                    if (pathinfo($img->getFilename(), PATHINFO_FILENAME) === $nombreArchivoSinExt) {
                        $nombreArchivoUnico = time() . '_' . rand(1000, 9999) . '_' . $img->getFilename();
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

            // 4. Guardar en Base de Datos asegurando owner_id y user_id = 1
            $producto = Producto::updateOrCreate(
                ['nombre' => trim($titulo), 'owner_id' => 1],
                [
                    'user_id' => 1,
                    'owner_id' => 1,
                    'descripcion' => $descripcionMejorada,
                    'precio_venta' => $precioFinal,
                    'stock_minimo' => 10,
                    'imagen' => $imagenRelativa,
                    'estado' => 'ACTIVO',
                    'activo' => true,
                    'unidad_medida' => 'unidad',
                    'peso_base' => 0.00,
                    'codigo' => Str::slug($titulo) . '-' . rand(100, 999),
                ]
            );

            // 5. Crear o actualizar el registro de inventario con 1000 unidades
            Inventario::updateOrCreate(
                [
                    'producto_id' => $producto->id,
                    'almacen_id'  => $almacen->id,
                ],
                [
                    'owner_id'        => 1,
                    'cantidad'        => 1000,
                    'cantidad_minima' => 10,
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->info("\n¡Proceso completado con éxito! Todos los productos y su stock inicial de 1000 unidades se configuraron correctamente.");
    }

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
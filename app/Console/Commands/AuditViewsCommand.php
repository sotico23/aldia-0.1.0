<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditViewsCommand extends Command
{
    protected $signature = 'audit:ui';

    protected $description = 'Audita las vistas y componentes React para asegurar cumplimiento de estándares de validación y feedback.';

    public function handle()
    {
        $this->info('Iniciando auditoría de UI/UX...');

        $files = File::allFiles(resource_path('js/pages'));
        $errors = [];

        foreach ($files as $file) {
            $content = File::get($file);
            $filename = $file->getRelativePathname();

            // Check for useForm errors usage
            if (str_contains($content, 'useForm') && ! str_contains($content, 'FormInput') && ! str_contains($content, 'InputError')) {
                $errors[] = "[$filename] Usa useForm pero no parece mostrar errores de validación (Falta FormInput o InputError).";
            }

            // Check for 500 error handling (This is global in app.tsx, so we just verify its existence)
            if ($filename === 'app.tsx' && ! str_contains($content, 'Error de servidor: Intente más tarde')) {
                $errors[] = "[app.tsx] No tiene el mensaje de error global para 500 configurado como 'Error de servidor: Intente más tarde'.";
            }

            // Check for numeric inputs without proper constraints
            if (preg_match('/<input[^>]+type=["\']number["\']/', $content) && ! str_contains($content, 'FormInput')) {
                $errors[] = "[$filename] Tiene inputs numéricos nativos. Se recomienda usar <FormInput type=\"number\" /> para validación de caracteres.";
            }
        }

        if (empty($errors)) {
            $this->info('✅ Auditoría completada. Todo parece estar bajo control.');
        } else {
            $this->warn('⚠️ Se encontraron los siguientes problemas:');
            foreach ($errors as $error) {
                $this->line("- $error");
            }
        }

        return 0;
    }
}

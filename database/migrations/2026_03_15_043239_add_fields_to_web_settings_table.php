<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->text('hero_titulo')->nullable()->after('app_author');
            $table->text('hero_subtitulo')->nullable()->after('hero_titulo');
            $table->string('hero_boton_principal')->nullable()->after('hero_subtitulo');
            $table->string('hero_boton_secundario')->nullable()->after('hero_boton_principal');
            $table->string('hero_badge')->nullable()->after('hero_boton_secundario');
            $table->json('caracteristicas')->nullable()->after('hero_badge');
            $table->json('planes')->nullable()->after('caracteristicas');
            $table->text('cta_titulo')->nullable()->after('planes');
            $table->text('cta_descripcion')->nullable()->after('cta_titulo');
            $table->string('cta_boton')->nullable()->after('cta_descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hero_titulo',
                'hero_subtitulo',
                'hero_boton_principal',
                'hero_boton_secundario',
                'hero_badge',
                'caracteristicas',
                'planes',
                'cta_titulo',
                'cta_descripcion',
                'cta_boton',
            ]);
        });
    }
};

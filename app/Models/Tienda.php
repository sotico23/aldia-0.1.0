<?php

namespace App\Models;

/**
 * "Tienda" del marketplace.
 *
 * En el ERP una tienda es un perfil publico: las rutas `/tienda/{slug}` y el
 * marketplace operan siempre sobre `PublicProfile`. Esta clase queda como
 * alias explicito para no duplicar tabla ni scope de propietario.
 */
class Tienda extends PublicProfile
{
    protected $table = 'public_profiles';
}

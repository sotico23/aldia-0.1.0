<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionSii extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'configuracion_siis';

    protected $fillable = [
        'owner_id',
        'rut',
        'razon_social',
        'giro',
        'acteco',
        'direccion',
        'comuna',
        'ciudad',
        'resolucion_numero',
        'resolucion_fecha',
        'ambiente',
        'email_dte',
        'telefono',
        'certificado_path',
        'certificado_password',
        'certificado_vencimiento',
    ];

    protected function casts(): array
    {
        return [
            'acteco' => 'integer',
            'resolucion_numero' => 'integer',
            'resolucion_fecha' => 'date',
            'certificado_password' => 'encrypted',
            'certificado_vencimiento' => 'date',
        ];
    }
}

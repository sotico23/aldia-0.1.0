<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterAppConfig extends Model
{
    use HasFactory;

    protected $table = 'master_app_configs';

    protected $fillable = [
        'app_name',
        'app_icon_url',
        'require_rut',
        'require_vehicle',
        'default_coverage_radius_km',
        'default_commission_pct',
        'require_manual_approval',
        'registration_success_message',
        'terms_conditions_url',
        'extra_metadata',
    ];

    protected $casts = [
        'require_rut' => 'boolean',
        'require_vehicle' => 'boolean',
        'default_coverage_radius_km' => 'float',
        'default_commission_pct' => 'float',
        'require_manual_approval' => 'boolean',
        'extra_metadata' => 'array',
    ];

    /**
     * Obtiene la configuración activa del sistema o crea los valores por defecto.
     */
    public static function getConfig(): self
    {
        $config = static::first();

        if (! $config) {
            $config = static::create([
                'app_name' => 'Al Día Repartidores',
                'require_rut' => false,
                'require_vehicle' => true,
                'default_coverage_radius_km' => 10.0,
                'default_commission_pct' => 80.0,
                'require_manual_approval' => true,
                'registration_success_message' => 'Tu solicitud fue enviada exitosamente. Recibirás una notificación al ser aprobada.',
            ]);
        }

        return $config;
    }
}

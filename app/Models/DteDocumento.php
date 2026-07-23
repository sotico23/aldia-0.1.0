<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DteDocumento extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'modelo_origen',
        'origen_id',
        'tipo_documento',
        'folio',
        'caf_folio_id',
        'rut_emisor',
        'rut_receptor',
        'razon_social_receptor',
        'giro_receptor',
        'direccion_receptor',
        'comuna_receptor',
        'monto_neto',
        'monto_iva',
        'monto_exento',
        'monto_total',
        'xml_sin_firma',
        'xml_firmado',
        'ted',
        'estado',
        'estado_sii',
        'track_id',
        'ambiente',
    ];

    protected function casts(): array
    {
        return [
            'tipo_documento' => 'integer',
            'folio' => 'integer',
            'monto_neto' => 'integer',
            'monto_iva' => 'integer',
            'monto_exento' => 'integer',
            'monto_total' => 'integer',
        ];
    }

    public function cafFolio(): BelongsTo
    {
        return $this->belongsTo(CafFolio::class);
    }

    public function envio(): BelongsTo
    {
        return $this->belongsTo(DteEnvio::class);
    }

    /** Nombre legible del tipo de documento. */
    public function tipoLabel(): string
    {
        return match ($this->tipo_documento) {
            33 => 'Factura Electrónica',
            34 => 'Factura No Afecta o Exenta',
            39 => 'Boleta Electrónica',
            41 => 'Boleta No Afecta o Exenta',
            56 => 'Nota de Débito',
            61 => 'Nota de Crédito',
            default => "Tipo {$this->tipo_documento}",
        };
    }

    public function scopeBorrador($query)
    {
        return $query->where('estado', 'borrador');
    }

    public function scopeAceptado($query)
    {
        return $query->where('estado', 'aceptado');
    }
}

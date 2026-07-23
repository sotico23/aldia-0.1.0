<?php

namespace App\Exports;

use App\Models\Factura;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class FacturasExport implements FromCollection
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Factura::all();
    }
}

<?php

namespace App\Exports;

use App\Models\GastoProyecto;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class GastosProyectoExport implements FromCollection
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return GastoProyecto::all();
    }
}

<?php

namespace App\Exports;

use App\Models\Asiento;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class ContabilidadExport implements FromCollection
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Asiento::all();
    }
}

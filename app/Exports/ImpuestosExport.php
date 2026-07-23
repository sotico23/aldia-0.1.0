<?php

namespace App\Exports;

use App\Models\Impuesto;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class ImpuestosExport implements FromCollection
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Impuesto::all();
    }
}

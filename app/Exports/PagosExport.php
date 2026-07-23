<?php

namespace App\Exports;

use App\Models\Pago;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class PagosExport implements FromCollection
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Pago::all();
    }
}

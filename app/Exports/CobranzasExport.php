<?php

namespace App\Exports;

use App\Models\Cobranza;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class CobranzasExport implements FromCollection
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Cobranza::all();
    }
}

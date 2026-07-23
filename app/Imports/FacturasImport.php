<?php

namespace App\Imports;

use App\Models\Factura;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class FacturasImport implements ToModel
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        return new Factura([
            //
        ]);
    }
}

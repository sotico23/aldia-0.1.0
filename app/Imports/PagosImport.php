<?php

namespace App\Imports;

use App\Models\Pago;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class PagosImport implements ToModel
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        return new Pago([
            //
        ]);
    }
}

<?php

namespace App\Imports;

use App\Models\GastoProyecto;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class GastosProyectoImport implements ToModel
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        return new GastoProyecto([
            //
        ]);
    }
}

<?php

namespace App\Imports;

use App\Models\Asiento;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class ContabilidadImport implements ToModel
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        return new Asiento([
            //
        ]);
    }
}

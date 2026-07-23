<?php

namespace App\Imports;

use App\Models\Cobranza;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class CobranzasImport implements ToModel
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        return new Cobranza([
            //
        ]);
    }
}

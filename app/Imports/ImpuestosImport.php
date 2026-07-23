<?php

namespace App\Imports;

use App\Models\Impuesto;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class ImpuestosImport implements ToModel
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        return new Impuesto([
            //
        ]);
    }
}

<?php

namespace App\Imports;

use App\Models\Conductor;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class ConductoresImport implements ToModel
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        return new Conductor([
            //
        ]);
    }
}

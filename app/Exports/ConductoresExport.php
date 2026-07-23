<?php

namespace App\Exports;

use App\Models\Conductor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class ConductoresExport implements FromCollection
{
    public function __construct(protected array $filters) {}

    /**
     * @return Collection
     */
    public function collection()
    {
        $query = Conductor::query();

        if (isset($this->filters['search']) && $this->filters['search']) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%")
                    ->orWhere('licencia', 'like', "%{$search}%");
            });
        }

        if (isset($this->filters['estado']) && $this->filters['estado']) {
            $query->where('estado', $this->filters['estado']);
        }

        return $query->orderBy('nombre')->get();
    }
}

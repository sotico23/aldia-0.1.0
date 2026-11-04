<?php

namespace App\Imports;

use App\Models\Venta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FacturacionPosMetadataImport
    implements ToCollection, WithHeadingRow
{
    private int $importedRows = 0;

    public function __construct(private readonly int $ownerId) {}

    public function collection(Collection $rows): void
    {
        $updates = [];
        $seenSaleIds = [];
        $seenFolios = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $row = $row->toArray();
            $rowNumber = $index + 2;

            if (array_filter($row, fn (mixed $value): bool => $value !== null && $value !== '') === []) {
                continue;
            }

            $validator = Validator::make($row, [
                'venta_id' => ['required', 'integer', Rule::exists('ventas', 'id')->where('owner_id', $this->ownerId)->where('es_pos', true)],
                'folio' => ['required', 'integer', 'min:1'],
                'tipo_dte' => ['required', 'integer', Rule::in([33, 39])],
            ]);

            if ($validator->fails()) {
                $errors["file.{$rowNumber}"] = $validator->errors()->all();

                continue;
            }

            $data = $validator->validated();
            $saleId = (int) $data['venta_id'];

            if (isset($seenSaleIds[$saleId])) {
                $errors["file.{$rowNumber}"] = ['La venta aparece más de una vez en el archivo.'];

                continue;
            }

            $seenSaleIds[$saleId] = true;
            $folioKey = "{$data['tipo_dte']}:{$data['folio']}";
            if (isset($seenFolios[$folioKey])) {
                $errors["file.{$rowNumber}"] = ['El folio y tipo de DTE aparecen más de una vez en el archivo.'];

                continue;
            }
            $seenFolios[$folioKey] = true;

            $sale = Venta::query()
                ->where('owner_id', $this->ownerId)
                ->where('es_pos', true)
                ->find($saleId);

            if (! $sale || ($sale->folio !== null && (
                (int) $sale->folio !== (int) $data['folio']
                || (int) $sale->tipo_dte !== (int) $data['tipo_dte']
            ))) {
                $errors["file.{$rowNumber}"] = ['La venta no existe en la empresa actual o ya tiene un folio distinto.'];

                continue;
            }

            $folioInUse = Venta::query()
                ->where('owner_id', $this->ownerId)
                ->where('tipo_dte', $data['tipo_dte'])
                ->where('folio', $data['folio'])
                ->where('id', '<>', $saleId)
                ->exists();

            if ($folioInUse) {
                $errors["file.{$rowNumber}"] = ['El folio ya está asociado a otra venta de la empresa.'];

                continue;
            }

            $updates[] = [
                'sale_id' => $saleId,
                'folio' => (int) $data['folio'],
                'tipo_dte' => (int) $data['tipo_dte'],
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($updates === []) {
            throw ValidationException::withMessages([
                'file' => 'El archivo no contiene filas válidas para importar.',
            ]);
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $update) {
                Venta::query()
                    ->where('owner_id', $this->ownerId)
                    ->where('es_pos', true)
                    ->whereKey($update['sale_id'])
                    ->update([
                        'folio' => $update['folio'],
                        'tipo_dte' => $update['tipo_dte'],
                    ]);
            }
        });

        $this->importedRows = count($updates);
    }

    public function importedCount(): int
    {
        return $this->importedRows;
    }
}

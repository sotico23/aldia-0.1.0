<?php

namespace App\Imports;

use App\Models\PosCierreCaja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CierresCajaImport
    implements ToCollection, WithHeadingRow
{
    private int $importedRows = 0;

    public function __construct(
        private readonly int $ownerId,
        private readonly int $importedBy,
        private readonly string $sourceFilename,
    ) {}

    public function collection(Collection $rows): void
    {
        $validatedRows = [];
        $seenReferences = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $row = $row->toArray();
            $rowNumber = $index + 2;

            if (array_filter($row, fn (mixed $value): bool => $value !== null && $value !== '') === []) {
                continue;
            }

            foreach (['fecha_desde', 'fecha_hasta'] as $dateColumn) {
                if (isset($row[$dateColumn]) && is_numeric($row[$dateColumn])) {
                    $row[$dateColumn] = ExcelDate::excelToDateTimeObject((float) $row[$dateColumn]);
                }
            }

            $validator = Validator::make($row, [
                'fecha_desde' => ['required', 'date'],
                'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
                'total_efectivo' => ['required', 'numeric', 'min:0'],
                'total_tarjeta' => ['required', 'numeric', 'min:0'],
                'total_transferencia' => ['required', 'numeric', 'min:0'],
                'total_otros' => ['required', 'numeric', 'min:0'],
                'total_general' => ['required', 'numeric', 'min:0'],
                'cantidad_ventas' => ['required', 'integer', 'min:0'],
                'referencia' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('pos_cierre_cajas', 'referencia')
                        ->where('owner_id', $this->ownerId),
                ],
            ]);

            if ($validator->fails()) {
                $errors["file.{$rowNumber}"] = $validator->errors()->all();

                continue;
            }

            $data = $validator->validated();
            $reference = $data['referencia'] ?? null;

            if ($reference !== null && isset($seenReferences[$reference])) {
                $errors["file.{$rowNumber}"] = ['La referencia aparece más de una vez en el archivo.'];

                continue;
            }

            if ($reference !== null) {
                $seenReferences[$reference] = true;
            }

            $validatedRows[] = [
                ...$data,
                'fecha_desde' => $this->normalizeDate($data['fecha_desde']),
                'fecha_hasta' => $this->normalizeDate($data['fecha_hasta']),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($validatedRows === []) {
            throw ValidationException::withMessages([
                'file' => 'El archivo no contiene cierres válidos para importar.',
            ]);
        }

        DB::transaction(function () use ($validatedRows): void {
            foreach ($validatedRows as $row) {
                PosCierreCaja::create([
                    ...$row,
                    'owner_id' => $this->ownerId,
                    'imported_by' => $this->importedBy,
                    'archivo_origen' => $this->sourceFilename,
                    'importado_at' => now(),
                ]);
            }
        });

        $this->importedRows = count($validatedRows);
    }

    public function importedCount(): int
    {
        return $this->importedRows;
    }

    private function normalizeDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return \Illuminate\Support\Carbon::parse($value)->toDateString();
    }
}

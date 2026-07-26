<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cupones MODIFY COLUMN tipo ENUM('porcentaje','precio_fijo','envio_gratis','vale_producto') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cupones MODIFY COLUMN tipo ENUM('porcentaje','precio_fijo','envio_gratis') NOT NULL");
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tenté vende sólo en cajitas: todo pedido es para llevar. */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo_consumo', 20)->default('LLEVAR')->change();
        });
        DB::table('ventas')->where('tipo_consumo', 'MESA')->update(['tipo_consumo' => 'LLEVAR']);
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo_consumo', 20)->default('MESA')->change();
        });
    }
};

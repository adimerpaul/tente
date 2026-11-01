<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('documento', 50)->nullable()->index(); // CI o NIT
            $table->string('celular', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('nombre');
        });

        // Cliente genérico (id 1) para las ventas de mostrador sin nombre.
        DB::table('clientes')->insert([
            'nombre' => 'SIN NOMBRE', 'documento' => '0', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};

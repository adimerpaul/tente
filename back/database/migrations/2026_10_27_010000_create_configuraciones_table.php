<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_empresa')->default('Tenté');
            $table->string('eslogan')->nullable();
            $table->string('nit', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono', 80)->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        DB::table('configuraciones')->insert([
            'nombre_empresa' => 'Tenté',
            'eslogan' => 'Sabor y Sazón',
            'logo' => 'empresa/logo_tente.webp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};

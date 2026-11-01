<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platos del menú. No llevan stock: se preparan al momento.
     * "disponible" quita un plato de la venta sin borrarlo (se acabó el pollo, etc.).
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->index();
            $table->string('nombre');
            $table->string('categoria', 100)->nullable();
            $table->string('descripcion')->nullable();
            $table->string('unidad', 20)->default('UND');
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->string('foto')->nullable();
            $table->boolean('disponible')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['categoria', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};

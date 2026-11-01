<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La venta y su detalle son snapshots: guardan el nombre del usuario que vendió,
     * el del cliente y el de cada producto tal como estaban al momento de cobrar.
     * Los reportes se calculan sobre estas columnas, no con joins a usuarios o productos.
     *
     * estado: COMPLETADA | ANULADA (lo económico).
     * estado_cocina: PENDIENTE → PREPARANDO → LISTO → ENTREGADO (lo que ve el cocinero).
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->index();
            $table->unsignedInteger('ticket')->default(0); // número de pedido del día, se reinicia cada día
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre');
            $table->string('cliente_documento', 50)->nullable();
            $table->string('tipo_consumo', 20)->default('MESA'); // MESA | LLEVAR
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('tipo_pago', 20)->default('EFECTIVO'); // EFECTIVO | QR | COMBINADO
            $table->decimal('monto_efectivo', 12, 2)->default(0);
            $table->decimal('monto_qr', 12, 2)->default(0);
            $table->string('estado', 30)->default('COMPLETADA')->index();
            $table->string('estado_cocina', 30)->default('PENDIENTE')->index();
            $table->timestamp('cocina_actualizado_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamp('fecha')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('codigo', 50);
            $table->string('nombre');
            $table->string('categoria', 100)->nullable();
            $table->string('unidad', 20)->default('UND');
            $table->string('foto')->nullable();
            $table->decimal('precio_venta', 12, 2);
            $table->unsignedInteger('cantidad');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('observacion')->nullable(); // "sin cebolla", "extra salsa"
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
    }
};

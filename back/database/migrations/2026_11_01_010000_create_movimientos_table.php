<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Movimientos de caja fuera del menú: tipo INGRESO (venta de aceite usado...) o
     * EGRESO (compra de periódicos, gas, bolsas...). Como la venta, guarda el nombre
     * de quien lo registró (snapshot).
     * tipo: INGRESO | EGRESO. estado: REGISTRADO | ANULADO. tipo_pago: EFECTIVO | QR (| COMBINADO, ver 020000).
     */
    private array $permisos = ['Ver Movimientos', 'Registrar Movimientos', 'Anular Movimientos'];

    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->string('tipo', 20)->default('EGRESO')->index();
            $table->string('concepto');
            $table->string('categoria', 100)->nullable();
            $table->decimal('monto', 12, 2);
            $table->string('tipo_pago', 20)->default('EFECTIVO');
            $table->string('estado', 30)->default('REGISTRADO')->index();
            $table->text('observacion')->nullable();
            $table->timestamp('fecha')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->permisos as $name) {
            Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['grupo' => 'Caja', 'orden' => 2]);
        }
        Role::findByName('Administrador')->givePermissionTo($this->permisos);
        // El vendedor es quien está en caja: registra lo que paga y lo ve en su cierre.
        Role::where('name', 'Vendedor')->first()?->givePermissionTo(['Ver Movimientos', 'Registrar Movimientos']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('movimientos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pago mixto en ingresos/egresos, igual que en ventas: tipo_pago EFECTIVO | QR | COMBINADO
     * con monto_efectivo + monto_qr = monto. El cierre de caja suma monto_efectivo.
     */
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->decimal('monto_efectivo', 12, 2)->default(0)->after('tipo_pago');
            $table->decimal('monto_qr', 12, 2)->default(0)->after('monto_efectivo');
        });
        DB::table('movimientos')->where('tipo_pago', 'EFECTIVO')->update(['monto_efectivo' => DB::raw('monto')]);
        DB::table('movimientos')->where('tipo_pago', 'QR')->update(['monto_qr' => DB::raw('monto')]);
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn(['monto_efectivo', 'monto_qr']);
        });
    }
};

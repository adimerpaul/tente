<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada permiso lleva su grupo y su orden de aparición, para que los formularios
     * de usuarios y roles se armen desde la base y no desde una lista fija en el frontend.
     */
    public function up(): void
    {
        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table) {
            $table->string('grupo', 60)->default('Otros')->after('guard_name');
            $table->unsignedSmallInteger('orden')->default(99)->after('grupo');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table) {
            $table->dropColumn(['grupo', 'orden']);
        });
    }
};

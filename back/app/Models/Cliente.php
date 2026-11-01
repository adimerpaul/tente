<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Cliente extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    /** Cliente genérico creado por la migración para ventas sin nombre. */
    public const SIN_NOMBRE = 1;

    protected $fillable = ['nombre', 'documento', 'celular', 'direccion'];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
}

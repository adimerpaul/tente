<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Movimiento extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    public const TIPOS = ['INGRESO', 'EGRESO'];

    /** Sugerencias para el campo categoría según el tipo; se puede escribir cualquier otra. */
    public const CATEGORIAS = [
        'EGRESO' => ['INSUMOS', 'GAS', 'CAJITAS Y BOLSAS', 'LIMPIEZA', 'SERVICIOS', 'PERSONAL', 'OTROS GASTOS'],
        'INGRESO' => ['VENTA DE ACEITE', 'VENTAS VARIAS', 'APORTE DE CAJA', 'OTROS INGRESOS'],
    ];

    protected $fillable = [
        'numero', 'user_id', 'usuario_nombre', 'tipo', 'concepto', 'categoria',
        'monto', 'tipo_pago', 'monto_efectivo', 'monto_qr', 'estado', 'observacion', 'fecha',
    ];

    protected $casts = ['monto' => 'decimal:2', 'monto_efectivo' => 'decimal:2', 'monto_qr' => 'decimal:2', 'fecha' => 'datetime'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

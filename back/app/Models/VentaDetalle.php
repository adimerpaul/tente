<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class VentaDetalle extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $table = 'venta_detalles';

    protected $fillable = [
        'venta_id', 'producto_id', 'codigo', 'nombre', 'categoria', 'unidad', 'foto',
        'precio_venta', 'cantidad', 'subtotal', 'descuento', 'total', 'observacion',
    ];

    protected $casts = [
        'precio_venta' => 'decimal:2', 'cantidad' => 'integer',
        'subtotal' => 'decimal:2', 'descuento' => 'decimal:2', 'total' => 'decimal:2',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }
}

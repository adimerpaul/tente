<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Venta extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    public const ESTADOS_COCINA = ['PENDIENTE', 'PREPARANDO', 'LISTO', 'ENTREGADO'];

    protected $fillable = [
        'numero', 'ticket', 'user_id', 'usuario_nombre', 'cliente_id', 'cliente_nombre',
        'cliente_documento', 'tipo_consumo', 'subtotal', 'descuento', 'total',
        'tipo_pago', 'monto_efectivo', 'monto_qr', 'estado', 'estado_cocina',
        'cocina_actualizado_at', 'observacion', 'fecha',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'descuento' => 'decimal:2',
        'total' => 'decimal:2', 'monto_efectivo' => 'decimal:2',
        'monto_qr' => 'decimal:2', 'fecha' => 'datetime',
        'cocina_actualizado_at' => 'datetime', 'ticket' => 'integer',
    ];

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}

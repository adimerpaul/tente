<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Producto extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'codigo', 'nombre', 'categoria', 'descripcion', 'unidad',
        'precio_venta', 'foto', 'disponible', 'orden',
    ];

    protected $casts = [
        'precio_venta' => 'decimal:2',
        'disponible' => 'boolean',
        'orden' => 'integer',
    ];
}

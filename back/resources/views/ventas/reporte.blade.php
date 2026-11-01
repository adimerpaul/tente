<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#2b2b2b}h2{margin:0;color:#9f2321}h3{margin:14px 0 4px;color:#e9682f;font-size:12px}
.head{width:100%;border-bottom:3px solid #e9682f;padding-bottom:6px}.head td{border:0;vertical-align:middle}.logo{height:52px}.meta{color:#666;font-size:9px}
table{width:100%;border-collapse:collapse}th{background:#9f2321;color:#fff;padding:5px;text-align:left}td{padding:4px 5px;border-bottom:1px solid #e5e5e5}.right{text-align:right}
.kpis td{border:1px solid #f3c9a8;background:#fdf3ea;padding:6px;text-align:center;width:25%}.kpis b{display:block;font-size:14px;color:#9f2321}
.cols td.col{width:50%;vertical-align:top;border:0;padding:0 6px 0 0}
</style></head><body>
<table class="head"><tr>
@if($logoData)<td style="width:120px"><img class="logo" src="{{ $logoData }}"></td>@endif
<td><h2>{{ $contexto->empresa }} — REPORTE DE VENTAS</h2><div class="meta">{{ ucfirst($contexto->periodo()) }} · {{ $contexto->generadoPor() }}</div>
@foreach($contexto->filtros as $k => $v)<div class="meta">{{ $k }}: {{ $v }}</div>@endforeach</td></tr></table>

@php($r = $datos['resumen'])
<h3>Resumen</h3>
<table class="kpis"><tr>
<td>Total vendido<b>Bs {{ number_format($r['total'], 2) }}</b></td>
<td>Ventas<b>{{ $r['cantidad'] }}</b></td>
<td>Platos vendidos<b>{{ $r['platos'] }}</b></td>
<td>Ticket promedio<b>Bs {{ number_format($r['ticket_promedio'], 2) }}</b></td>
</tr><tr>
<td>Efectivo<b>Bs {{ number_format($r['efectivo'], 2) }}</b></td>
<td>QR<b>Bs {{ number_format($r['qr'], 2) }}</b></td>
<td>Descuentos<b>Bs {{ number_format($r['descuento'], 2) }}</b></td>
<td>Anuladas<b>{{ $r['anuladas'] }} (Bs {{ number_format($r['total_anulado'], 2) }})</b></td>
</tr></table>

<h3>Por producto</h3>
<table><thead><tr><th>#</th><th>Producto</th><th>Categoría</th><th class="right">Cantidad</th><th class="right">Total</th></tr></thead><tbody>
@forelse($datos['por_producto'] as $i => $p)<tr><td>{{ $i + 1 }}</td><td>{{ $p->nombre }}</td><td>{{ $p->categoria }}</td><td class="right">{{ (int) $p->cantidad }}</td><td class="right">Bs {{ number_format($p->total, 2) }}</td></tr>
@empty<tr><td colspan="5">Sin ventas en el periodo</td></tr>@endforelse</tbody></table>

<table class="cols"><tr><td class="col">
<h3>Por usuario</h3>
<table><thead><tr><th>Usuario</th><th class="right">Ventas</th><th class="right">Total</th></tr></thead><tbody>
@foreach($datos['por_usuario'] as $u)<tr><td>{{ $u->nombre }}</td><td class="right">{{ $u->cantidad }}</td><td class="right">Bs {{ number_format($u->total, 2) }}</td></tr>@endforeach</tbody></table>
<h3>Por forma de pago</h3>
<table><thead><tr><th>Pago</th><th class="right">Ventas</th><th class="right">Total</th></tr></thead><tbody>
@foreach($datos['por_pago'] as $p)<tr><td>{{ $p->nombre }}</td><td class="right">{{ $p->cantidad }}</td><td class="right">Bs {{ number_format($p->total, 2) }}</td></tr>@endforeach</tbody></table>
</td><td class="col">
<h3>Por categoría</h3>
<table><thead><tr><th>Categoría</th><th class="right">Cant.</th><th class="right">Total</th></tr></thead><tbody>
@foreach($datos['por_categoria'] as $c)<tr><td>{{ $c->nombre }}</td><td class="right">{{ (int) $c->cantidad }}</td><td class="right">Bs {{ number_format($c->total, 2) }}</td></tr>@endforeach</tbody></table>
<h3>Mesa / para llevar</h3>
<table><thead><tr><th>Consumo</th><th class="right">Ventas</th><th class="right">Total</th></tr></thead><tbody>
@foreach($datos['por_consumo'] as $c)<tr><td>{{ $c->nombre === 'LLEVAR' ? 'PARA LLEVAR' : 'EN MESA' }}</td><td class="right">{{ $c->cantidad }}</td><td class="right">Bs {{ number_format($c->total, 2) }}</td></tr>@endforeach</tbody></table>
</td></tr></table>

<h3>Por día</h3>
<table><thead><tr><th>Día</th><th class="right">Ventas</th><th class="right">Efectivo</th><th class="right">QR</th><th class="right">Total</th></tr></thead><tbody>
@foreach($datos['por_dia'] as $d)<tr><td>{{ \Carbon\Carbon::parse($d->dia)->format('d/m/Y') }}</td><td class="right">{{ $d->cantidad }}</td><td class="right">Bs {{ number_format($d->efectivo, 2) }}</td><td class="right">Bs {{ number_format($d->qr, 2) }}</td><td class="right">Bs {{ number_format($d->total, 2) }}</td></tr>@endforeach</tbody></table>
</body></html>

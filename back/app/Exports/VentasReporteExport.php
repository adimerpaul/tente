<?php

namespace App\Exports;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaTabla;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reporte del periodo en ocho hojas: resumen (cierre de caja), documentos de venta, detalle
 * línea por línea, ingresos y gastos, por producto, por usuario (quién vendió), por día y por hora.
 * Ventas, detalle y movimientos incluyen lo anulado, pero sus totales sólo suman lo vigente.
 */
class VentasReporteExport implements WithMultipleSheets
{
    public function __construct(
        private readonly ContextoReporte $contexto,
        private readonly array $datos,
        private readonly Collection $ventas,
        private readonly Collection $detalles,
        private readonly Collection $movimientos,
    ) {}

    private function fecha(?string $valor): ?float
    {
        return $valor ? ExcelDate::PHPToExcel(Carbon::parse($valor)) : null;
    }

    public function sheets(): array
    {
        $moneda = fn (string $titulo, int $ancho = 14) => ['titulo' => $titulo, 'ancho' => $ancho, 'formato' => 'moneda', 'total' => true];
        $entero = fn (string $titulo, int $ancho = 11, bool $total = true) => ['titulo' => $titulo, 'ancho' => $ancho, 'formato' => 'entero', 'total' => $total];
        $texto = fn (string $titulo, int $ancho = 18) => ['titulo' => $titulo, 'ancho' => $ancho, 'formato' => 'texto'];

        $r = $this->datos['resumen'];

        return [
            new HojaTabla($this->contexto, 'Resumen', 'RESUMEN Y CIERRE DE CAJA', [
                $texto('Concepto', 34), ['titulo' => 'Monto', 'ancho' => 16, 'formato' => 'moneda'],
            ], [
                ['(+) Total vendido', (float) $r['total']],
                ['     Efectivo', (float) $r['efectivo']],
                ['     QR', (float) $r['qr']],
                ['(+) Otros ingresos', (float) $r['ingresos']],
                ['     Efectivo', (float) $r['ingresos_efectivo']],
                ['     QR', (float) $r['ingresos_qr']],
                ['(-) Gastos / egresos', (float) $r['egresos']],
                ['     Efectivo', (float) $r['egresos_efectivo']],
                ['     QR', (float) $r['egresos_qr']],
                ['(=) Neto (ventas + ingresos - egresos)', (float) $r['neto']],
                ['Efectivo en caja (efectivo + ingresos - egresos en efectivo)', (float) $r['caja_efectivo']],
                ['Descuentos otorgados', (float) $r['descuento']],
                ['Ventas anuladas ('.$r['anuladas'].')', (float) $r['total_anulado']],
            ]),

            new HojaTabla($this->contexto, 'Ventas', 'DOCUMENTOS DE VENTA', [
                $entero('#', 5, false), $texto('Nº venta', 13), $entero('Pedido', 8, false),
                ['titulo' => 'Fecha y hora', 'ancho' => 17, 'formato' => 'fechahora'],
                $texto('Vendedor', 22), $texto('Cliente', 24), $texto('Consumo', 10), $texto('Pago', 12),
                $moneda('Subtotal', 12), $moneda('Descuento', 12), $moneda('Total'), $moneda('Efectivo', 12), $moneda('QR', 12),
                $texto('Estado', 13), $texto('Observación', 28),
            ], $this->ventas->values()->map(fn ($v, $i) => [
                $i + 1, $v->numero, (int) $v->ticket, $this->fecha($v->fecha), $v->usuario_nombre, $v->cliente_nombre,
                $v->tipo_consumo === 'LLEVAR' ? 'LLEVAR' : 'MESA', $v->tipo_pago,
                (float) $v->subtotal, (float) $v->descuento, (float) $v->total, (float) $v->monto_efectivo, (float) $v->monto_qr,
                $v->estado, $v->observacion,
            ])->all(), 13),

            new HojaTabla($this->contexto, 'Detalle', 'DETALLE DE PRODUCTOS VENDIDOS', [
                $texto('Nº venta', 13), ['titulo' => 'Fecha y hora', 'ancho' => 17, 'formato' => 'fechahora'],
                $texto('Vendedor', 20), $texto('Cliente', 22), $texto('Código', 10), $texto('Producto', 26), $texto('Categoría', 16),
                $entero('Cantidad', 10), $moneda('Precio', 11), $moneda('Subtotal', 12), $moneda('Descuento', 12), $moneda('Total', 12),
                $texto('Nota', 22), $texto('Estado', 13),
            ], $this->detalles->map(fn ($d) => [
                $d->numero, $this->fecha($d->fecha), $d->usuario_nombre, $d->cliente_nombre, $d->codigo, $d->nombre, $d->categoria,
                (int) $d->cantidad, (float) $d->precio_venta, (float) $d->subtotal, (float) $d->descuento, (float) $d->total,
                $d->observacion, $d->estado,
            ])->all(), 13),

            new HojaTabla($this->contexto, 'Ingresos y gastos', 'INGRESOS Y GASTOS (EGRESOS) DE CAJA', [
                $entero('#', 5, false), $texto('Número', 13), ['titulo' => 'Fecha y hora', 'ancho' => 17, 'formato' => 'fechahora'],
                $texto('Registró', 22), $texto('Tipo', 10), $texto('Concepto', 30), $texto('Categoría', 18), $texto('Pago', 11),
                ['titulo' => 'Efectivo', 'ancho' => 12, 'formato' => 'moneda'], ['titulo' => 'QR', 'ancho' => 12, 'formato' => 'moneda'],
                $moneda('Ingreso'), $moneda('Egreso'), $texto('Estado', 13), $texto('Observación', 28),
            ], $this->movimientos->values()->map(fn ($g, $i) => [
                $i + 1, $g->numero, $this->fecha($g->fecha), $g->usuario_nombre, $g->tipo, $g->concepto, $g->categoria, $g->tipo_pago, (float) $g->monto_efectivo, (float) $g->monto_qr,
                $g->tipo === 'INGRESO' ? (float) $g->monto : 0.0, $g->tipo === 'EGRESO' ? (float) $g->monto : 0.0, $g->estado, $g->observacion,
            ])->all(), 12, 'REGISTRADO'),

            new HojaTabla($this->contexto, 'Por producto', 'VENTAS POR PRODUCTO', [
                $entero('#', 5, false), $texto('Producto', 30), $texto('Categoría', 18), $entero('Nº ventas', 11), $entero('Cantidad', 11), $moneda('Total vendido', 15),
            ], collect($this->datos['por_producto'])->values()->map(fn ($p, $i) => [
                $i + 1, $p->nombre, $p->categoria, (int) $p->ventas, (int) $p->cantidad, (float) $p->total,
            ])->all()),

            new HojaTabla($this->contexto, 'Por usuario', 'VENTAS POR USUARIO', [
                $texto('Usuario', 28), $entero('Ventas'), $moneda('Efectivo'), $moneda('QR'), $moneda('Total'),
            ], collect($this->datos['por_usuario'])->map(fn ($u) => [
                $u->nombre, (int) $u->cantidad, (float) $u->efectivo, (float) $u->qr, (float) $u->total,
            ])->all()),

            new HojaTabla($this->contexto, 'Por día', 'VENTAS, INGRESOS Y GASTOS POR DÍA', [
                ['titulo' => 'Día', 'ancho' => 14, 'formato' => 'fecha'], $entero('Ventas'), $moneda('Efectivo'), $moneda('QR'), $moneda('Total vendido'),
                $moneda('Ingresos'), $moneda('Egresos'), $moneda('Neto'),
            ], collect($this->datos['por_dia'])->map(fn ($d) => [
                $this->fecha($d->dia), (int) $d->cantidad, (float) $d->efectivo, (float) $d->qr, (float) $d->total,
                (float) $d->ingresos, (float) $d->egresos, (float) $d->neto,
            ])->all()),

            new HojaTabla($this->contexto, 'Por hora', 'VENTAS POR HORA DEL DÍA', [
                $texto('Hora', 12), $entero('Ventas'), $moneda('Total'),
            ], collect($this->datos['por_hora'])->map(fn ($h) => [
                str_pad((string) $h->hora, 2, '0', STR_PAD_LEFT).':00', (int) $h->cantidad, (float) $h->total,
            ])->all()),
        ];
    }
}

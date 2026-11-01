<?php

namespace App\Exports;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaTabla;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Reporte de ventas del periodo en seis hojas: documentos, detalle línea por línea,
 * por producto, por usuario (quién vendió), por día y por hora.
 * Ventas y detalle incluyen lo anulado, pero sus totales sólo suman lo COMPLETADO.
 */
class VentasReporteExport implements WithMultipleSheets
{
    public function __construct(
        private readonly ContextoReporte $contexto,
        private readonly array $datos,
        private readonly Collection $ventas,
        private readonly Collection $detalles,
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

        return [
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

            new HojaTabla($this->contexto, 'Por día', 'VENTAS POR DÍA', [
                ['titulo' => 'Día', 'ancho' => 14, 'formato' => 'fecha'], $entero('Ventas'), $moneda('Efectivo'), $moneda('QR'), $moneda('Total'),
            ], collect($this->datos['por_dia'])->map(fn ($d) => [
                $this->fecha($d->dia), (int) $d->cantidad, (float) $d->efectivo, (float) $d->qr, (float) $d->total,
            ])->all()),

            new HojaTabla($this->contexto, 'Por hora', 'VENTAS POR HORA DEL DÍA', [
                $texto('Hora', 12), $entero('Ventas'), $moneda('Total'),
            ], collect($this->datos['por_hora'])->map(fn ($h) => [
                str_pad((string) $h->hora, 2, '0', STR_PAD_LEFT).':00', (int) $h->cantidad, (float) $h->total,
            ])->all()),
        ];
    }
}

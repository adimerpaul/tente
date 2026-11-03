<?php

namespace App\Http\Controllers;

use App\Exports\Comun\ContextoReporte;
use App\Exports\VentasReporteExport;
use App\Models\Configuracion;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reportes de ventas por periodo. Todo sale de los snapshots de ventas y venta_detalles
 * (usuario_nombre, cliente_nombre, nombre del producto), así un producto o usuario
 * renombrado o borrado no altera lo que se vendió.
 */
class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Reportes');

        return response()->json($this->datos($request));
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeAction($request, 'Ver Reportes');

        $export = new VentasReporteExport($this->contexto($request), $this->datos($request), $this->ventas($request), $this->detalles($request), $this->movimientos($request));

        return Excel::download($export, 'reporte_ventas_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeAction($request, 'Ver Reportes');
        $datos = $this->datos($request);
        $contexto = $this->contexto($request);
        $empresa = Configuracion::first();
        $logo = $empresa?->logo && is_file(public_path('images/'.$empresa->logo)) ? public_path('images/'.$empresa->logo) : null;
        // dompdf no lee webp de forma fiable: el logo va como PNG en base64.
        $logoData = null;
        if ($logo && ($image = @imagecreatefromstring(file_get_contents($logo)))) {
            ob_start();
            imagepng($image);
            $logoData = 'data:image/png;base64,'.base64_encode(ob_get_clean());
            imagedestroy($image);
        }

        return Pdf::loadView('ventas.reporte', compact('datos', 'contexto', 'logoData'))->setPaper('letter', 'portrait')
            ->download('reporte_ventas_'.now()->format('Ymd_His').'.pdf');
    }

    /** [desde, hasta]: por defecto el día de hoy. */
    private function rango(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
        ]);
        $from = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : now()->startOfDay();
        $to = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : $from->copy()->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function ventasQuery(Request $request)
    {
        [$from, $to] = $this->rango($request);

        return DB::table('ventas')->whereNull('deleted_at')->whereBetween('fecha', [$from, $to])
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id));
    }

    private function movimientosQuery(Request $request)
    {
        [$from, $to] = $this->rango($request);

        return DB::table('movimientos')->whereNull('deleted_at')->whereBetween('fecha', [$from, $to])
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id));
    }

    private function detallesQuery(Request $request)
    {
        [$from, $to] = $this->rango($request);

        return DB::table('venta_detalles as d')->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->whereNull('v.deleted_at')->whereNull('d.deleted_at')->where('v.estado', 'COMPLETADA')
            ->whereBetween('v.fecha', [$from, $to])
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('v.user_id', $id));
    }

    private function datos(Request $request): array
    {
        [$from, $to] = $this->rango($request);
        $completadas = fn () => $this->ventasQuery($request)->where('estado', 'COMPLETADA');
        $total = (float) $completadas()->sum('total');
        $cantidad = (int) $completadas()->count();
        $registrados = fn () => $this->movimientosQuery($request)->where('estado', 'REGISTRADO');
        $mov = MovimientoController::totales(fn () => $this->movimientosQuery($request));
        $efectivo = (float) $completadas()->sum('monto_efectivo');
        $hourExpr = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%H', fecha)" : "DATE_FORMAT(fecha, '%H')";
        $dayExpr = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m-%d', fecha)" : 'DATE(fecha)';

        return [
            'desde' => $from->toDateString(),
            'hasta' => $to->toDateString(),
            'resumen' => [
                'total' => $total,
                'cantidad' => $cantidad,
                'ticket_promedio' => $cantidad ? round($total / $cantidad, 2) : 0,
                'platos' => (int) $this->detallesQuery($request)->sum('d.cantidad'),
                'descuento' => (float) $completadas()->sum('descuento'),
                'efectivo' => $efectivo,
                'qr' => (float) $completadas()->sum('monto_qr'),
                'anuladas' => (int) $this->ventasQuery($request)->where('estado', 'ANULADA')->count(),
                'total_anulado' => (float) $this->ventasQuery($request)->where('estado', 'ANULADA')->sum('total'),
                ...$mov,
                'ingresos_qr' => round($mov['ingresos'] - $mov['ingresos_efectivo'], 2),
                'egresos_qr' => round($mov['egresos'] - $mov['egresos_efectivo'], 2),
                'neto' => round($total + $mov['ingresos'] - $mov['egresos'], 2),
                'caja_efectivo' => round($efectivo + $mov['ingresos_efectivo'] - $mov['egresos_efectivo'], 2),
            ],
            'por_producto' => $this->detallesQuery($request)
                ->selectRaw('d.nombre, MAX(d.categoria) as categoria, SUM(d.cantidad) as cantidad, SUM(d.total) as total, COUNT(DISTINCT d.venta_id) as ventas')
                ->groupBy('d.nombre')->orderByDesc('cantidad')->get(),
            'por_categoria' => $this->detallesQuery($request)
                ->selectRaw("COALESCE(d.categoria, 'SIN CATEGORÍA') as nombre, SUM(d.cantidad) as cantidad, SUM(d.total) as total")
                ->groupBy('d.categoria')->orderByDesc('total')->get(),
            'por_usuario' => $completadas()
                ->selectRaw('usuario_nombre as nombre, COUNT(*) as cantidad, SUM(total) as total, SUM(monto_efectivo) as efectivo, SUM(monto_qr) as qr')
                ->groupBy('usuario_nombre')->orderByDesc('total')->get(),
            'por_dia' => $this->porDia($completadas(), $registrados(), $dayExpr),
            'por_categoria_movimiento' => $registrados()
                ->selectRaw("tipo, COALESCE(categoria, 'SIN CATEGORÍA') as nombre, COUNT(*) as cantidad, SUM(monto) as total")
                ->groupBy('tipo', 'categoria')->orderBy('tipo', 'desc')->orderByDesc('total')->get(),
            'movimientos' => $this->movimientos($request),
            'por_hora' => $completadas()
                ->selectRaw("$hourExpr as hora, COUNT(*) as cantidad, SUM(total) as total")
                ->groupByRaw($hourExpr)->orderBy('hora')->get(),
            'por_pago' => $completadas()
                ->selectRaw('tipo_pago as nombre, COUNT(*) as cantidad, SUM(total) as total')
                ->groupBy('tipo_pago')->orderByDesc('total')->get(),
            'por_consumo' => $completadas()
                ->selectRaw('tipo_consumo as nombre, COUNT(*) as cantidad, SUM(total) as total')
                ->groupBy('tipo_consumo')->orderByDesc('total')->get(),
            'por_cliente' => $completadas()->where('cliente_nombre', '!=', 'SIN NOMBRE')
                ->selectRaw('cliente_nombre as nombre, MAX(cliente_documento) as documento, COUNT(*) as cantidad, SUM(total) as total')
                ->groupBy('cliente_nombre')->orderByDesc('total')->limit(20)->get(),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ];
    }

    /** Ventas, ingresos y egresos por día en una sola tabla; aparece también el día sin ventas. */
    private function porDia($ventas, $movs, string $dayExpr)
    {
        $porVenta = $ventas->selectRaw("$dayExpr as dia, COUNT(*) as cantidad, SUM(total) as total, SUM(monto_efectivo) as efectivo, SUM(monto_qr) as qr")
            ->groupByRaw($dayExpr)->get()->keyBy(fn ($r) => substr((string) $r->dia, 0, 10));
        $porMov = $movs->selectRaw("$dayExpr as dia, SUM(CASE WHEN tipo = 'INGRESO' THEN monto ELSE 0 END) as ingresos, SUM(CASE WHEN tipo = 'EGRESO' THEN monto ELSE 0 END) as egresos")
            ->groupByRaw($dayExpr)->get()->keyBy(fn ($r) => substr((string) $r->dia, 0, 10));

        return $porVenta->keys()->merge($porMov->keys())->unique()->sort()->values()->map(function ($dia) use ($porVenta, $porMov) {
            $v = $porVenta[$dia] ?? null;
            $total = (float) ($v->total ?? 0);
            $ingresos = (float) ($porMov[$dia]->ingresos ?? 0);
            $egresos = (float) ($porMov[$dia]->egresos ?? 0);

            return (object) [
                'dia' => $dia, 'cantidad' => (int) ($v->cantidad ?? 0), 'total' => $total,
                'efectivo' => (float) ($v->efectivo ?? 0), 'qr' => (float) ($v->qr ?? 0),
                'ingresos' => $ingresos, 'egresos' => $egresos, 'neto' => round($total + $ingresos - $egresos, 2),
            ];
        });
    }

    /** Todos los ingresos y egresos del periodo, también los anulados (la hoja los marca y no los suma). */
    private function movimientos(Request $request)
    {
        return $this->movimientosQuery($request)->orderBy('fecha')
            ->get(['id', 'numero', 'fecha', 'usuario_nombre', 'tipo', 'concepto', 'categoria', 'tipo_pago', 'monto', 'monto_efectivo', 'monto_qr', 'estado', 'observacion']);
    }

    private function ventas(Request $request)
    {
        return $this->ventasQuery($request)->orderBy('fecha')->get();
    }

    private function detalles(Request $request)
    {
        [$from, $to] = $this->rango($request);

        // El detalle incluye también lo anulado: la hoja lo marca con el estado de su venta.
        return DB::table('venta_detalles as d')->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->whereNull('v.deleted_at')->whereNull('d.deleted_at')->whereBetween('v.fecha', [$from, $to])
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('v.user_id', $id))
            ->orderBy('v.fecha')->orderBy('d.id')
            ->select('d.*', 'v.numero', 'v.ticket', 'v.fecha', 'v.estado', 'v.usuario_nombre', 'v.cliente_nombre')
            ->get();
    }

    private function contexto(Request $request): ContextoReporte
    {
        [$from, $to] = $this->rango($request);
        $empresa = Configuracion::first();
        $filtros = [];
        if ($userId = $request->integer('user_id')) {
            $filtros['Usuario'] = User::find($userId)?->name ?? 'ID '.$userId;
        }

        return new ContextoReporte(
            empresa: $empresa?->nombre_empresa ?: 'Tenté',
            nit: $empresa?->nit,
            usuario: $request->user()?->name ?? $request->user()?->username ?? '-',
            generado: now(),
            desde: $from,
            hasta: $to,
            filtros: $filtros,
        );
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ingresos y gastos (egresos) de caja que no son ventas del menú.
 * Cada registro tiene tipo INGRESO o EGRESO; el cierre de caja es
 * ventas + ingresos - egresos.
 */
class MovimientoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Movimientos');
        $query = self::filtered(Movimiento::query(), $request)->latest('fecha');
        if (in_array($request->input('tipo'), Movimiento::TIPOS, true)) {
            $query->where('tipo', $request->input('tipo'));
        }
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")
                ->orWhere('concepto', 'like', "%{$search}%")
                ->orWhere('categoria', 'like', "%{$search}%")
                ->orWhere('usuario_nombre', 'like', "%{$search}%"));
        }
        $perPage = (int) $request->input('per_page', 50);

        return response()->json($query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500)));
    }

    /** Categorías sugeridas por tipo + las ya usadas. */
    public function catalogos(Request $request)
    {
        $this->authorizeAction($request, ['Ver Movimientos', 'Registrar Movimientos']);
        $categorias = [];
        foreach (Movimiento::TIPOS as $tipo) {
            $usadas = Movimiento::where('tipo', $tipo)->whereNotNull('categoria')->distinct()->pluck('categoria')->all();
            $categorias[$tipo] = array_values(array_unique([...Movimiento::CATEGORIAS[$tipo], ...$usadas]));
        }

        return response()->json(['categorias' => $categorias]);
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Registrar Movimientos');
        $data = $request->validate([
            'tipo' => ['required', Rule::in(Movimiento::TIPOS)],
            'concepto' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);
        $categoria = mb_strtoupper(trim((string) ($data['categoria'] ?? '')));
        // Como en ventas: con pago mixto, efectivo + QR debe dar el monto.
        $monto = round((float) $data['monto'], 2);
        $cash = match ($data['tipo_pago']) {
            'EFECTIVO' => $monto, 'QR' => 0.0, default => round((float) ($data['monto_efectivo'] ?? 0), 2)
        };
        $qr = match ($data['tipo_pago']) {
            'QR' => $monto, 'EFECTIVO' => 0.0, default => round((float) ($data['monto_qr'] ?? 0), 2)
        };
        abort_if(abs(($cash + $qr) - $monto) > 0.009, 422, 'Los montos de efectivo y QR deben sumar el monto');

        $movimiento = Movimiento::create([
            'user_id' => $request->user()->id,
            'usuario_nombre' => $request->user()->name,
            'tipo' => $data['tipo'],
            'concepto' => mb_strtoupper(trim($data['concepto'])),
            'categoria' => $categoria ?: null,
            'monto' => $monto,
            'tipo_pago' => $data['tipo_pago'],
            'monto_efectivo' => $cash,
            'monto_qr' => $qr,
            'estado' => 'REGISTRADO',
            'observacion' => $data['observacion'] ?? null,
            'fecha' => now(),
        ]);
        $movimiento->update(['numero' => ($data['tipo'] === 'INGRESO' ? 'I-' : 'E-').str_pad((string) $movimiento->id, 8, '0', STR_PAD_LEFT)]);

        return response()->json($movimiento, 201);
    }

    public function cancel(Request $request, Movimiento $movimiento)
    {
        $this->authorizeAction($request, 'Anular Movimientos');
        abort_if($movimiento->estado === 'ANULADO', 422, 'El registro ya está anulado');
        $movimiento->update(['estado' => 'ANULADO']);

        return response()->json($movimiento->fresh());
    }

    /** Filtro de fechas y usuario; lo comparten ventas-resumen y reportes. */
    public static function filtered($query, Request $request)
    {
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha', '<=', $to);
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    /**
     * Totales de ingresos y egresos vigentes (REGISTRADO) de una consulta ya filtrada.
     * Sirve igual para Eloquent (ventas-resumen) que para DB::table (reportes).
     *
     * @param  callable  $base  devuelve una consulta nueva cada vez
     */
    public static function totales(callable $base): array
    {
        // El efectivo sale de monto_efectivo, así el pago mixto cuenta su parte en caja.
        $sum = fn (string $tipo, string $columna = 'monto') => (float) $base()->where('estado', 'REGISTRADO')->where('tipo', $tipo)->sum($columna);

        return [
            'ingresos' => $sum('INGRESO'),
            'ingresos_efectivo' => $sum('INGRESO', 'monto_efectivo'),
            'egresos' => $sum('EGRESO'),
            'egresos_efectivo' => $sum('EGRESO', 'monto_efectivo'),
            'movimientos_cantidad' => (int) $base()->where('estado', 'REGISTRADO')->count(),
        ];
    }

    private function authorizeAction(Request $request, string|array $permission): void
    {
        abort_unless($request->user()?->hasAnyPermission((array) $permission), 403, 'No tiene permiso para realizar esta acción');
    }
}

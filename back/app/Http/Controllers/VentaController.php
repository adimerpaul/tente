<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $query = $this->filteredQuery($request)
            // Resumen corto de productos para la columna del listado: sólo lo que se pinta.
            ->with(['detalles' => fn ($q) => $q->select('id', 'venta_id', 'nombre', 'cantidad', 'observacion')])
            ->latest('fecha');

        $perPage = (int) $request->input('per_page', 50);

        return response()->json($query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500)));
    }

    public function summary(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $query = $this->filteredQuery($request)->where('estado', 'COMPLETADA');

        return response()->json([
            'efectivo' => (clone $query)->sum('monto_efectivo'),
            'qr' => (clone $query)->sum('monto_qr'),
            'total' => (clone $query)->sum('total'),
            'descuento' => (clone $query)->sum('descuento'),
            'cantidad' => (clone $query)->count(),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, ['Ver Ventas', 'Crear Ventas', 'Ver Cocina']);

        return response()->json($venta->load('detalles'));
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Ventas');
        $data = $request->validate([
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'tipo_consumo' => ['nullable', 'in:MESA,LLEVAR'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'detalles.*.observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $venta = DB::transaction(function () use ($request, $data) {
            $items = [];
            $subtotal = 0;
            foreach ($data['detalles'] as $detail) {
                $product = Producto::findOrFail($detail['producto_id']);
                abort_unless($product->disponible, 422, "{$product->nombre} no está disponible");
                $quantity = (int) $detail['cantidad'];
                // El precio siempre es el del menú: no se envía desde la pantalla.
                $lineSubtotal = round((float) $product->precio_venta * $quantity, 2);
                $subtotal += $lineSubtotal;
                $items[] = [$product, $quantity, $lineSubtotal, $detail['observacion'] ?? null];
            }

            $discount = round((float) ($data['descuento'] ?? 0), 2);
            abort_if($discount > $subtotal, 422, 'El descuento no puede superar el subtotal');
            $total = round($subtotal - $discount, 2);
            $cash = $data['tipo_pago'] === 'EFECTIVO' ? $total : round((float) ($data['monto_efectivo'] ?? 0), 2);
            $qr = $data['tipo_pago'] === 'QR' ? $total : round((float) ($data['monto_qr'] ?? 0), 2);
            abort_if(abs(($cash + $qr) - $total) > 0.009, 422, 'Los montos de efectivo y QR deben sumar el total de la venta');

            // Con cliente registrado se copia su nombre; sin él, el nombre escrito (para llamar el pedido) o SIN NOMBRE.
            $cliente = Cliente::find($data['cliente_id'] ?? Cliente::SIN_NOMBRE);
            $nombreLibre = mb_strtoupper(trim((string) ($data['cliente_nombre'] ?? '')));
            $esGenerico = ! $cliente || $cliente->id === Cliente::SIN_NOMBRE;

            // Número de pedido del día: el cocinero y el cliente se guían por él.
            $ticket = (int) Venta::whereDate('fecha', today())->lockForUpdate()->max('ticket') + 1;

            $sale = Venta::create([
                'ticket' => $ticket,
                'user_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name,
                'cliente_id' => $cliente?->id,
                'cliente_nombre' => $esGenerico ? ($nombreLibre ?: 'SIN NOMBRE') : $cliente->nombre,
                'cliente_documento' => $esGenerico ? null : $cliente->documento,
                'tipo_consumo' => $data['tipo_consumo'] ?? 'MESA',
                'subtotal' => $subtotal,
                'descuento' => $discount,
                'total' => $total,
                'tipo_pago' => $data['tipo_pago'],
                'monto_efectivo' => $cash,
                'monto_qr' => $qr,
                'estado' => 'COMPLETADA',
                'estado_cocina' => 'PENDIENTE',
                'cocina_actualizado_at' => now(),
                'observacion' => $data['observacion'] ?? null,
                'fecha' => now(),
            ]);
            $sale->update(['numero' => 'V-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT)]);

            // El descuento de cabecera se prorratea por línea; la última se lleva el redondeo.
            $allocated = 0;
            foreach ($items as $index => [$product, $quantity, $lineSubtotal, $observacion]) {
                $lineDiscount = $index === array_key_last($items)
                    ? round($discount - $allocated, 2)
                    : ($subtotal > 0 ? round($discount * ($lineSubtotal / $subtotal), 2) : 0);
                $allocated += $lineDiscount;
                $sale->detalles()->create([
                    'producto_id' => $product->id,
                    'codigo' => $product->codigo,
                    'nombre' => $product->nombre,
                    'categoria' => $product->categoria,
                    'unidad' => $product->unidad,
                    'foto' => $product->foto,
                    'precio_venta' => $product->precio_venta,
                    'cantidad' => $quantity,
                    'subtotal' => $lineSubtotal,
                    'descuento' => $lineDiscount,
                    'total' => $lineSubtotal - $lineDiscount,
                    'observacion' => $observacion ? mb_strtoupper(trim($observacion)) : null,
                ]);
            }

            return $sale;
        });

        return response()->json($venta->load('detalles'), 201);
    }

    public function cancel(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Anular Ventas');
        abort_if($venta->estado === 'ANULADA', 422, 'La venta ya está anulada');
        $venta->update(['estado' => 'ANULADA', 'cocina_actualizado_at' => now()]);

        return response()->json($venta->fresh());
    }

    // ── Cocina ───────────────────────────────────────────────

    /**
     * Pedidos para la pantalla de cocina: los de hoy que todavía no se entregaron,
     * más los últimos entregados para poder deshacer un toque equivocado.
     */
    public function cocina(Request $request)
    {
        $this->authorizeAction($request, 'Ver Cocina');
        $base = fn () => Venta::with('detalles:id,venta_id,nombre,cantidad,observacion')
            ->where('estado', 'COMPLETADA')->whereDate('fecha', today());

        return response()->json([
            'pedidos' => $base()->whereIn('estado_cocina', ['PENDIENTE', 'PREPARANDO', 'LISTO'])->orderBy('fecha')->get(),
            'entregados' => $base()->where('estado_cocina', 'ENTREGADO')->latest('cocina_actualizado_at')->limit(10)->get(),
            'hora' => now()->toDateTimeString(),
        ]);
    }

    public function updateCocina(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Actualizar Pedidos');
        $data = $request->validate(['estado_cocina' => ['required', Rule::in(Venta::ESTADOS_COCINA)]]);
        abort_if($venta->estado === 'ANULADA', 422, 'El pedido fue anulado');
        $venta->update(['estado_cocina' => $data['estado_cocina'], 'cocina_actualizado_at' => now()]);

        return response()->json($venta->fresh('detalles'));
    }

    // ── Panel de inicio ──────────────────────────────────────

    /**
     * El rango se elige con ?periodo=hoy|ayer|semana|mes|anio|rango (por defecto hoy)
     * y todos los indicadores se calculan dentro de ese rango.
     * Con periodo=rango manda el usuario las fechas: ?desde=YYYY-MM-DD&hasta=YYYY-MM-DD.
     */
    public function dashboard(Request $request)
    {
        $this->authorizeAction($request, 'Ver Estadísticas');
        [$period, $from, $to, $granularity] = $this->dashboardRange((string) $request->query('periodo', 'hoy'), $request);

        $sales = fn () => Venta::where('estado', 'COMPLETADA')->whereBetween('fecha', [$from, $to]);
        $details = fn () => DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')
            ->whereBetween('ventas.fecha', [$from, $to])
            ->whereNull('ventas.deleted_at')->whereNull('venta_detalles.deleted_at');

        $total = (float) $sales()->sum('total');
        $count = (int) $sales()->count();
        $items = (float) $details()->sum('venta_detalles.cantidad');

        $bucket = $this->periodBucket($granularity);
        $rows = $sales()->selectRaw("$bucket as periodo, SUM(total) as total, COUNT(*) as cantidad")
            ->groupBy('periodo')->get()->keyBy('periodo');
        $serie = $this->dashboardSeries($from, $to, $granularity, $rows);

        $byUser = $sales()->selectRaw('usuario_nombre as nombre, SUM(total) as total')
            ->groupBy('usuario_nombre')->orderByDesc('total')->limit(8)->get();
        $payments = $sales()->selectRaw('tipo_pago as nombre, SUM(total) as total')
            ->groupBy('tipo_pago')->get();
        $consumo = $sales()->selectRaw('tipo_consumo as nombre, COUNT(*) as cantidad, SUM(total) as total')
            ->groupBy('tipo_consumo')->get();
        $topProducts = $details()
            ->selectRaw('venta_detalles.producto_id, venta_detalles.nombre, MAX(venta_detalles.foto) as foto, SUM(venta_detalles.cantidad) as cantidad, SUM(venta_detalles.total) as total')
            ->groupBy('venta_detalles.producto_id', 'venta_detalles.nombre')
            ->orderByDesc('cantidad')->limit(8)->get();
        $topCategories = $details()
            ->selectRaw("COALESCE(venta_detalles.categoria, 'SIN CATEGORÍA') as nombre, SUM(venta_detalles.cantidad) as cantidad, SUM(venta_detalles.total) as total")
            ->groupBy('venta_detalles.categoria')
            ->orderByDesc('cantidad')->limit(8)->get();

        return response()->json([
            'periodo' => ['clave' => $period, 'titulo' => $this->periodTitle($period), 'desde' => $from->toDateTimeString(), 'hasta' => $to->toDateTimeString(), 'granularidad' => $granularity],
            'indicadores' => ['ventas' => $total, 'productos' => $items, 'cantidad_ventas' => $count, 'ticket_promedio' => $count ? $total / $count : 0],
            'diario' => $serie, 'usuarios' => $byUser, 'pagos' => $payments, 'consumo' => $consumo,
            'productos_top' => $topProducts, 'categorias_top' => $topCategories,
        ]);
    }

    /** Rango del panel: [clave, desde, hasta, granularidad de la serie]. */
    private function dashboardRange(string $period, ?Request $request = null): array
    {
        if ($period === 'rango') {
            return $this->customRange($request);
        }

        return match ($period) {
            'ayer' => ['ayer', now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), 'hora'],
            'semana' => ['semana', now()->subDays(6)->startOfDay(), now()->endOfDay(), 'dia'],
            'mes' => ['mes', now()->subDays(29)->startOfDay(), now()->endOfDay(), 'dia'],
            'anio' => ['anio', now()->subMonths(11)->startOfMonth(), now()->endOfDay(), 'mes'],
            default => ['hoy', now()->startOfDay(), now()->endOfDay(), 'hora'],
        };
    }

    /**
     * Rango elegido a mano. Fechas inválidas o faltantes caen en hoy, se ordenan si vienen
     * al revés y la granularidad de la serie se decide por el largo del rango.
     */
    private function customRange(?Request $request): array
    {
        $parse = function ($value) {
            try {
                return $value ? Carbon::parse((string) $value) : null;
            } catch (\Exception) {
                return null;
            }
        };

        $from = $parse($request?->query('desde')) ?? now();
        $to = $parse($request?->query('hasta')) ?? $from->copy();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $days = $from->diffInDays($to) + 1;
        $granularity = $days <= 2 ? 'hora' : ($days <= 92 ? 'dia' : 'mes');

        return ['rango', $from, $to, $granularity];
    }

    private function periodTitle(string $period): string
    {
        return ['ayer' => 'Ayer', 'semana' => 'Últimos 7 días', 'mes' => 'Últimos 30 días', 'anio' => 'Últimos 12 meses', 'rango' => 'Rango elegido'][$period] ?? 'Hoy';
    }

    /** Agrupación por hora/día/mes; MySQL y el SQLite de los tests usan los mismos tokens de formato. */
    private function periodBucket(string $granularity): string
    {
        $format = ['hora' => '%Y-%m-%d %H', 'mes' => '%Y-%m'][$granularity] ?? '%Y-%m-%d';

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('$format', fecha)"
            : "DATE_FORMAT(fecha, '$format')";
    }

    /** Rellena los huecos del rango para que la serie no salte periodos sin ventas. */
    private function dashboardSeries($from, $to, string $granularity, $rows)
    {
        $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $serie = [];
        $cursor = $from->copy();
        while ($cursor <= $to) {
            if ($granularity === 'hora') {
                $key = $cursor->format('Y-m-d H');
                $label = $cursor->format('H').':00';
                $cursor->addHour();
            } elseif ($granularity === 'mes') {
                $key = $cursor->format('Y-m');
                $label = $months[$cursor->month - 1];
                $cursor->addMonth();
            } else {
                $key = $cursor->format('Y-m-d');
                $label = $cursor->format('d/m');
                $cursor->addDay();
            }
            $row = $rows[$key] ?? null;
            $serie[] = ['label' => $label, 'total' => (float) ($row->total ?? 0), 'cantidad' => (int) ($row->cantidad ?? 0)];
        }

        return $serie;
    }

    // ── Filtros ──────────────────────────────────────────────

    private function filteredQuery(Request $request)
    {
        $query = Venta::query();
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")
                ->orWhere('usuario_nombre', 'like', "%{$search}%")
                ->orWhere('cliente_nombre', 'like', "%{$search}%")
                ->orWhere('cliente_documento', 'like', "%{$search}%")
                ->orWhere('estado', 'like', "%{$search}%"));
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha', '<=', $to);
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($clienteId = $request->integer('cliente_id')) {
            $query->where('cliente_id', $clienteId);
        }
        if (in_array($request->input('tipo_pago'), ['EFECTIVO', 'QR', 'COMBINADO'], true)) {
            $query->where('tipo_pago', $request->input('tipo_pago'));
        }

        return $query;
    }

    /** Con varios permisos basta tener cualquiera de ellos. */
    private function authorizeAction(Request $request, string|array $permission): void
    {
        abort_unless($request->user()?->hasAnyPermission((array) $permission), 403, 'No tiene permiso para realizar esta acción');
    }
}

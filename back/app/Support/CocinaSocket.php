<?php

namespace App\Support;

use App\Models\Venta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

/**
 * Avisos en tiempo real a la cocina y la caja por el servidor socket compartido
 * (el mismo Node para todos los proyectos). Se usa su canal genérico
 * POST /notify {event, data}, que reemite el evento a todos los conectados; por eso
 * el evento lleva el prefijo del proyecto: `tente-cocina`.
 *
 * Se envía después de responder (defer) y sin romper nada si el socket no contesta:
 * la cocina sigue refrescando por su cuenta.
 */
class CocinaSocket
{
    public const EVENTO = 'tente-cocina';

    /** @param string $tipo NUEVO | ANULADO | PENDIENTE | PREPARANDO | LISTO | ENTREGADO */
    public static function emit(string $tipo, Venta $venta): void
    {
        $url = rtrim((string) config('services.socket.url'), '/');
        if ($url === '') {
            return;
        }

        $data = [
            'tipo' => $tipo,
            'id' => $venta->id,
            'ticket' => $venta->ticket,
            'numero' => $venta->numero,
            'cliente_nombre' => $venta->cliente_nombre,
            'tipo_consumo' => $venta->tipo_consumo,
            'estado_cocina' => $venta->estado_cocina,
            'usuario_nombre' => $venta->usuario_nombre,
            'mensaje' => self::message($tipo, $venta),
            'hora' => now()->toDateTimeString(),
        ];

        defer(function () use ($url, $data) {
            try {
                Http::timeout(3)->post($url.'/notify', ['event' => self::EVENTO, 'data' => $data]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo avisar al socket: '.$e->getMessage());
            }
        });
    }

    /** Texto que se muestra y que se lee en voz alta. */
    public static function message(string $tipo, Venta $venta): string
    {
        $pedido = 'Pedido número '.$venta->ticket;
        $cliente = $venta->cliente_nombre && $venta->cliente_nombre !== 'SIN NOMBRE' ? ', '.$venta->cliente_nombre : '';

        return match ($tipo) {
            'NUEVO' => 'Nuevo pedido número '.$venta->ticket.$cliente.': '
                .$venta->detalles->map(fn ($d) => $d->cantidad.' '.$d->nombre.($d->observacion ? ' '.$d->observacion : ''))->implode(', '),
            'ANULADO' => 'Atención, el pedido número '.$venta->ticket.$cliente.' fue anulado',
            'PREPARANDO' => $pedido.$cliente.' en preparación',
            'LISTO' => $pedido.$cliente.' listo para entregar',
            'ENTREGADO' => $pedido.$cliente.' entregado',
            default => $pedido.$cliente.' volvió a pendientes',
        };
    }
}

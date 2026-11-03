<?php

namespace App\Exports\Comun;

/**
 * Hoja genérica con el formato común: recibe título, subtítulo, columnas y filas ya calculadas.
 * La fila de totales suma las columnas marcadas con 'total' => true; si se indica
 * $columnaEstado, sólo suma las filas cuyo estado sea $estadoQueSuma (COMPLETADA por defecto).
 */
class HojaTabla extends HojaBase
{
    public function __construct(
        ContextoReporte $contexto,
        private readonly string $titulo,
        private readonly string $subtituloTexto,
        private readonly array $definicion,
        private readonly array $datos,
        private readonly ?int $columnaEstado = null,
        private readonly string $estadoQueSuma = 'COMPLETADA',
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return $this->titulo;
    }

    protected function subtitulo(): string
    {
        return $this->subtituloTexto.' - '.ucfirst($this->contexto->periodo());
    }

    protected function columnas(): array
    {
        return $this->definicion;
    }

    protected function filas(): array
    {
        return $this->datos;
    }

    protected function filaTotales(array $columnas, array $filas): array
    {
        if ($this->columnaEstado !== null) {
            $filas = array_values(array_filter($filas, fn ($fila) => ($fila[$this->columnaEstado] ?? null) === $this->estadoQueSuma));
        }

        return parent::filaTotales($columnas, $filas);
    }
}

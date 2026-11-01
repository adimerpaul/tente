<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Menú inicial de Tenté. Los platos con dos precios (salchipapa, pique)
     * van como dos productos: porción chica y grande.
     * Las fotos están versionadas en public/images/productos.
     */
    private array $menu = [
        ['TEN-001', 'ALITAS DE POLLO', 'POLLO', 30, 'alitas', 'Alitas fritas bañadas en salsa BBQ'],
        ['TEN-002', 'SALCHIPAPA CHICA', 'SALCHIPAPAS', 15, 'salchipapa', 'Papas fritas con salchicha'],
        ['TEN-003', 'SALCHIPAPA GRANDE', 'SALCHIPAPAS', 20, 'salchipapa', 'Papas fritas con salchicha, porción grande'],
        ['TEN-004', 'HAMBURGUESA SIMPLE', 'HAMBURGUESAS', 15, 'hamburguesa-simple', 'Carne, queso, tomate y lechuga'],
        ['TEN-005', 'HAMBURGUESA DOBLE', 'HAMBURGUESAS', 20, 'hamburguesa-doble', 'Doble carne y doble queso'],
        ['TEN-006', 'HAMBURGUESA ESPECIAL', 'HAMBURGUESAS', 25, 'hamburguesa-especial', 'Carne, queso, tocino y huevo'],
        ['TEN-007', 'PIQUE CHICO', 'PIQUES', 25, 'pique', 'Carne, salchicha, huevo, papas y locoto'],
        ['TEN-008', 'PIQUE GRANDE', 'PIQUES', 30, 'pique', 'Carne, salchicha, huevo, papas y locoto, porción grande'],
        ['TEN-009', 'SALCHIAMBURGUESA', 'HAMBURGUESAS', 20, 'salchiamburguesa', 'Hamburguesa con salchicha'],
        ['TEN-010', 'PIQUE BURGUER', 'HAMBURGUESAS', 30, 'pique-burguer', 'Hamburguesa con pique, papas y huevo'],
    ];

    public function up(): void
    {
        foreach ($this->menu as $index => [$codigo, $nombre, $categoria, $precio, $foto, $descripcion]) {
            DB::table('productos')->insert([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'categoria' => $categoria,
                'descripcion' => $descripcion,
                'unidad' => 'UND',
                'precio_venta' => $precio,
                'foto' => "productos/{$foto}.webp",
                'disponible' => true,
                'orden' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('productos')->whereIn('codigo', array_column($this->menu, 0))->delete();
    }
};

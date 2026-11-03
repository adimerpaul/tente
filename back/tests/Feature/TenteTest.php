<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TenteTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function producto(string $codigo): Producto
    {
        return Producto::where('codigo', $codigo)->firstOrFail();
    }

    private function vender(array $extra = []): TestResponse
    {
        return $this->postJson('/api/ventas', array_merge([
            'tipo_pago' => 'EFECTIVO',
            'detalles' => [
                ['producto_id' => $this->producto('TEN-004')->id, 'cantidad' => 2, 'observacion' => 'sin cebolla'],
                ['producto_id' => $this->producto('TEN-001')->id, 'cantidad' => 1],
            ],
        ], $extra));
    }

    public function test_initial_users_login_with_their_roles(): void
    {
        foreach (['admin' => 'Administrador', 'vendedor' => 'Vendedor', 'cocinero' => 'Cocinero'] as $username => $rol) {
            $response = $this->postJson('/api/login', ['username' => $username, 'password' => $username])->assertOk();
            $this->assertSame($rol, $response->json('user.rol'));
            $this->assertNotEmpty($response->json('user.permissions'));
        }

        $this->assertTrue($this->user('admin')->hasPermissionTo('Gestionar Roles'));
        $this->assertTrue($this->user('vendedor')->hasPermissionTo('Crear Ventas'));
        $this->assertFalse($this->user('vendedor')->hasPermissionTo('Actualizar Pedidos'));
        $this->assertTrue($this->user('cocinero')->hasPermissionTo('Actualizar Pedidos'));
        $this->assertFalse($this->user('cocinero')->hasPermissionTo('Crear Ventas'));
    }

    public function test_menu_is_loaded_with_photos(): void
    {
        $this->assertSame(10, Producto::count());
        $this->assertEquals(30, $this->producto('TEN-001')->precio_venta);
        $this->assertEquals(15, $this->producto('TEN-002')->precio_venta);
        $this->assertEquals(20, $this->producto('TEN-003')->precio_venta);
        foreach (Producto::pluck('foto') as $foto) {
            $this->assertFileExists(public_path('images/'.$foto));
        }
    }

    public function test_sale_stores_snapshots_and_daily_ticket(): void
    {
        $vendedor = $this->user('vendedor');
        Sanctum::actingAs($vendedor);

        $first = $this->vender(['cliente_nombre' => 'juan', 'tipo_consumo' => 'LLEVAR'])->assertCreated();
        $this->assertSame(1, $first->json('ticket'));
        $this->assertSame('VENDEDOR TENTÉ', $first->json('usuario_nombre'));
        $this->assertSame('JUAN', $first->json('cliente_nombre'));
        // El nombre escrito registra al cliente; con el mismo nombre se reutiliza, no se duplica.
        $juan = Cliente::where('nombre', 'JUAN')->sole();
        $this->assertSame($juan->id, $first->json('cliente_id'));
        $this->assertSame($juan->id, $this->vender(['cliente_nombre' => ' Juan '])->assertCreated()->json('cliente_id'));
        $this->assertSame(Cliente::SIN_NOMBRE, $this->vender()->assertCreated()->json('cliente_id'));
        $this->vender(['tipo_consumo' => 'MESA'])->assertCreated()->assertJsonPath('tipo_consumo', 'LLEVAR');
        $this->assertEquals(60, $first->json('total'));
        $this->assertSame('PENDIENTE', $first->json('estado_cocina'));
        $this->assertSame('HAMBURGUESA SIMPLE', $first->json('detalles.0.nombre'));
        $this->assertSame('SIN CEBOLLA', $first->json('detalles.0.observacion'));

        $cliente = Cliente::create(['nombre' => 'MARÍA PÉREZ', 'documento' => '123456']);
        $second = $this->vender(['cliente_id' => $cliente->id, 'descuento' => 10])->assertCreated();
        $this->assertSame(5, $second->json('ticket'));
        $this->assertSame('MARÍA PÉREZ', $second->json('cliente_nombre'));
        $this->assertEquals(50, $second->json('total'));
        $this->assertEquals(10, collect($second->json('detalles'))->sum('descuento'));

        // Renombrar el producto no cambia lo vendido.
        $this->producto('TEN-004')->update(['nombre' => 'OTRO NOMBRE']);
        $this->assertSame('HAMBURGUESA SIMPLE', Venta::find($first->json('id'))->detalles()->first()->nombre);
    }

    public function test_unavailable_product_and_price_mismatch_are_rejected(): void
    {
        Sanctum::actingAs($this->user('vendedor'));
        $this->producto('TEN-001')->update(['disponible' => false]);
        $this->vender()->assertStatus(422);

        $this->producto('TEN-001')->update(['disponible' => true]);
        $this->vender(['tipo_pago' => 'COMBINADO', 'monto_efectivo' => 10, 'monto_qr' => 10])->assertStatus(422);
        $this->vender(['tipo_pago' => 'COMBINADO', 'monto_efectivo' => 40, 'monto_qr' => 20])->assertCreated();
    }

    public function test_kitchen_flow_and_permissions(): void
    {
        Sanctum::actingAs($this->user('vendedor'));
        $id = $this->vender()->json('id');
        $this->getJson('/api/cocina')->assertOk()->assertJsonCount(1, 'pedidos');
        $this->putJson("/api/ventas/{$id}/cocina", ['estado_cocina' => 'LISTO'])->assertForbidden();

        Sanctum::actingAs($this->user('cocinero'));
        $this->postJson('/api/ventas', [])->assertForbidden();
        $this->putJson("/api/ventas/{$id}/cocina", ['estado_cocina' => 'PREPARANDO'])->assertOk();
        $this->putJson("/api/ventas/{$id}/cocina", ['estado_cocina' => 'ENTREGADO'])->assertOk();
        $this->getJson('/api/cocina')->assertOk()->assertJsonCount(0, 'pedidos')->assertJsonCount(1, 'entregados');
        $this->getJson('/api/ventas')->assertForbidden();
    }

    public function test_kitchen_events_are_sent_to_shared_socket(): void
    {
        config(['services.socket.url' => 'https://socket.test']);
        Http::fake();
        $this->withoutDefer();

        Sanctum::actingAs($this->user('admin'));
        $id = $this->vender()->json('id');
        $this->putJson("/api/ventas/{$id}/cocina", ['estado_cocina' => 'PREPARANDO'])->assertOk();
        $this->putJson("/api/ventas/{$id}/cocina", ['estado_cocina' => 'LISTO'])->assertOk();
        $this->putJson("/api/ventas/{$id}/anular")->assertOk();

        $sent = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertSame(['NUEVO', 'PREPARANDO', 'LISTO', 'ANULADO'], $sent->map(fn ($r) => $r['data']['tipo'])->all());
        $sent->each(function ($r) {
            $this->assertSame('https://socket.test/notify', $r->url());
            $this->assertSame('tente-cocina', $r['event']);
        });
        $this->assertStringContainsString('2 HAMBURGUESA SIMPLE SIN CEBOLLA', $sent[0]['data']['mensaje']);
        $this->assertStringContainsString('listo para entregar', $sent[2]['data']['mensaje']);
    }

    public function test_cancelled_sale_leaves_kitchen_and_reports(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $id = $this->vender()->json('id');
        $this->vender();
        $this->putJson("/api/ventas/{$id}/anular")->assertOk();
        $this->getJson('/api/cocina')->assertJsonCount(1, 'pedidos');

        $report = $this->getJson('/api/reportes')->assertOk();
        $this->assertEquals(60, $report->json('resumen.total'));
        $this->assertSame(1, $report->json('resumen.anuladas'));
        $this->assertSame(3, $report->json('resumen.platos'));
        $this->assertSame('HAMBURGUESA SIMPLE', $report->json('por_producto.0.nombre'));

        $this->getJson('/api/dashboard?periodo=hoy')->assertOk()->assertJsonPath('indicadores.cantidad_ventas', 1);
        $this->get('/api/reportes-exportar/excel')->assertOk();
        $this->get('/api/reportes-exportar/pdf')->assertOk();
    }

    public function test_movimientos_ingreso_egreso_suman_en_caja_y_reportes(): void
    {
        Sanctum::actingAs($this->user('vendedor'));
        $this->vender()->assertCreated(); // 60 en efectivo
        $this->postJson('/api/movimientos', ['tipo' => 'EGRESO', 'concepto' => 'aceite', 'categoria' => 'insumos', 'monto' => 25, 'tipo_pago' => 'EFECTIVO'])
            ->assertCreated()->assertJsonPath('concepto', 'ACEITE')->assertJsonPath('categoria', 'INSUMOS');
        $this->postJson('/api/movimientos', ['tipo' => 'EGRESO', 'concepto' => 'periódicos', 'monto' => 5, 'tipo_pago' => 'QR'])->assertCreated();
        // Pago mixto: efectivo + QR debe dar el monto (23 sin decimales también vale).
        $this->postJson('/api/movimientos', ['tipo' => 'EGRESO', 'concepto' => 'gas', 'monto' => 23, 'tipo_pago' => 'COMBINADO', 'monto_efectivo' => 20, 'monto_qr' => 2])->assertStatus(422);
        $this->postJson('/api/movimientos', ['tipo' => 'EGRESO', 'concepto' => 'gas', 'monto' => 23, 'tipo_pago' => 'COMBINADO', 'monto_efectivo' => 20, 'monto_qr' => 3])
            ->assertCreated()->assertJsonPath('monto_efectivo', '20.00')->assertJsonPath('monto_qr', '3.00');
        $ingreso = $this->postJson('/api/movimientos', ['tipo' => 'INGRESO', 'concepto' => 'venta de aceite usado', 'monto' => 10, 'tipo_pago' => 'EFECTIVO'])
            ->assertCreated()->json();
        $this->assertStringStartsWith('I-', $ingreso['numero']);
        $this->postJson('/api/movimientos', ['tipo' => 'OTRO', 'concepto' => 'x', 'monto' => 1, 'tipo_pago' => 'EFECTIVO'])->assertStatus(422);
        $this->putJson("/api/movimientos/{$ingreso['id']}/anular")->assertForbidden();

        $hoy = today()->toDateString();
        $resumen = $this->getJson("/api/ventas-resumen?desde={$hoy}&hasta={$hoy}")->assertOk();
        $this->assertEquals(10, $resumen->json('ingresos'));
        $this->assertEquals(53, $resumen->json('egresos'));       // 25 + 5 + 23
        $this->assertEquals(17, $resumen->json('neto'));          // 60 + 10 - 53
        $this->assertEquals(25, $resumen->json('caja_efectivo')); // 60 + 10 - (25 + 20 del mixto)
        $this->assertSame(4, $this->getJson("/api/movimientos?desde={$hoy}")->assertOk()->json('total'));
        $this->assertContains('VENTA DE ACEITE', $this->getJson('/api/movimientos-catalogos')->json('categorias.INGRESO'));

        Sanctum::actingAs($this->user('admin'));
        $this->putJson("/api/movimientos/{$ingreso['id']}/anular")->assertOk()->assertJsonPath('estado', 'ANULADO');
        $report = $this->getJson('/api/reportes')->assertOk();
        $this->assertEquals(0, $report->json('resumen.ingresos'));
        $this->assertEquals(7, $report->json('resumen.neto'));
        $this->assertEquals(8, $report->json('resumen.egresos_qr')); // 5 por QR + 3 del mixto
        $this->assertEquals(53, $report->json('por_dia.0.egresos'));
        $this->assertCount(4, $report->json('movimientos'));
        $this->get('/api/reportes-exportar/excel')->assertOk();
        $this->get('/api/reportes-exportar/pdf')->assertOk();
    }

    public function test_vendor_cannot_see_reports_or_users(): void
    {
        Sanctum::actingAs($this->user('vendedor'));
        $this->getJson('/api/reportes')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_clients_crud(): void
    {
        Sanctum::actingAs($this->user('vendedor'));
        $id = $this->postJson('/api/clientes', ['nombre' => 'carlos', 'documento' => '777'])->assertCreated()->json('id');
        $this->postJson('/api/clientes', ['nombre' => 'otro', 'documento' => '777'])->assertStatus(422);
        $this->putJson("/api/clientes/{$id}", ['nombre' => 'carlos rojas', 'documento' => '777'])->assertOk()->assertJsonPath('nombre', 'CARLOS ROJAS');
        $this->deleteJson("/api/clientes/{$id}")->assertForbidden();

        Sanctum::actingAs($this->user('admin'));
        $this->deleteJson('/api/clientes/'.Cliente::SIN_NOMBRE)->assertStatus(422);
        $this->deleteJson("/api/clientes/{$id}")->assertOk();
    }

    public function test_roles_assign_permissions_to_users(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $roles = collect($this->getJson('/api/roles')->assertOk()->json())->keyBy('name');
        $this->assertSame(['Administrador', 'Vendedor', 'Cocinero'], $roles->keys()->all());

        $verCocina = Permission::where('name', 'Ver Cocina')->value('id');
        $role = $this->postJson('/api/roles', ['name' => 'Mesero', 'permissions' => [$verCocina]])->assertCreated()->json('id');
        $userId = $this->postJson('/api/users', ['name' => 'pedro', 'username' => 'pedro', 'password' => '123456', 'rol' => 'Mesero'])
            ->assertCreated()->json('id');
        $this->assertTrue(User::find($userId)->hasPermissionTo('Ver Cocina'));
        $this->assertFalse(User::find($userId)->hasPermissionTo('Crear Ventas'));

        $this->deleteJson("/api/roles/{$role}")->assertStatus(422);
        $this->deleteJson('/api/roles/'.$roles['Administrador']['id'])->assertStatus(422);
    }
}

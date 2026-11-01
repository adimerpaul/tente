<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Grupo => permisos, en el orden en que aparecen en los formularios. */
    private array $groups = [
        'Inicio' => ['Ver Estadísticas'],
        'Ventas' => ['Ver Ventas', 'Crear Ventas', 'Anular Ventas', 'Ver Reportes'],
        'Cocina' => ['Ver Cocina', 'Actualizar Pedidos'],
        'Clientes' => ['Ver Clientes', 'Crear Clientes', 'Editar Clientes', 'Eliminar Clientes'],
        'Productos' => ['Ver Productos', 'Crear Productos', 'Editar Productos', 'Eliminar Productos'],
        'Usuarios' => ['Ver Usuarios', 'Crear Usuarios', 'Editar Usuarios', 'Eliminar Usuarios', 'Gestionar Roles'],
        'Configuración' => ['Gestionar Configuración'],
    ];

    /** Roles iniciales: el administrador lo tiene todo, el vendedor cobra y el cocinero prepara. */
    private array $roles = [
        'Administrador' => '*',
        'Vendedor' => ['Ver Ventas', 'Crear Ventas', 'Ver Cocina', 'Ver Clientes', 'Crear Clientes', 'Editar Clientes', 'Ver Productos'],
        'Cocinero' => ['Ver Cocina', 'Actualizar Pedidos', 'Ver Productos'],
    ];

    /** La contraseña inicial es el mismo nombre de usuario: admin/admin, vendedor/vendedor, cocinero/cocinero. */
    private array $users = [
        ['username' => 'admin', 'name' => 'ADMINISTRADOR TENTÉ', 'rol' => 'Administrador'],
        ['username' => 'vendedor', 'name' => 'VENDEDOR TENTÉ', 'rol' => 'Vendedor'],
        ['username' => 'cocinero', 'name' => 'COCINERO TENTÉ', 'rol' => 'Cocinero'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys($this->groups) as $index => $group) {
            foreach ($this->groups[$group] as $name) {
                Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['grupo' => $group, 'orden' => $index + 1]);
            }
        }

        foreach ($this->roles as $name => $permissions) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
                ->syncPermissions($permissions === '*' ? Permission::all() : $permissions);
        }

        foreach ($this->users as $data) {
            User::firstOrCreate(['username' => $data['username']], [
                'name' => $data['name'],
                'password' => bcrypt($data['username']),
            ])->syncRoles([$data['rol']]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        User::whereIn('username', array_column($this->users, 'username'))->forceDelete();
        Role::whereIn('name', array_keys($this->roles))->delete();
        Permission::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

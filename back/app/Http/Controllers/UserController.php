<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    // ── Auth ─────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (! $user || ! password_verify($request->password, $user->password)) {
            return response()->json(['message' => 'Usuario o contraseña incorrectos'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->sessionPayload($user),
            'must_change_password' => password_verify('123456', $user->password),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        return response()->json($this->sessionPayload($request->user()));
    }

    /**
     * Usuario con su rol y TODOS sus permisos efectivos (los del rol más los extra).
     * El frontend y la app móvil leen `permissions[].name` para armar el menú.
     */
    private function sessionPayload(User $user): array
    {
        return array_merge($user->toArray(), [
            'rol' => $user->getRoleNames()->first(),
            'permissions' => $user->getAllPermissions()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password_actual' => 'required|string',
            'password_nuevo' => 'required|string|min:6',
            'password_nuevo_confirmation' => 'required|same:password_nuevo',
        ]);

        $user = $request->user();

        if (! password_verify($request->password_actual, $user->getAuthPassword())) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], 422);
        }

        $user->update(['password' => bcrypt($request->password_nuevo)]);

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    // ── CRUD usuarios ────────────────────────────────────────

    public function index(Request $request)
    {
        $this->requirePermission($request, 'Ver Usuarios');

        return User::with(['roles:id,name', 'permissions:id,name'])->orderBy('name')->get()
            ->map(fn (User $user) => array_merge($user->toArray(), ['rol' => $user->roles->first()?->name]));
    }

    public function store(Request $request)
    {
        $this->requirePermission($request, 'Crear Usuarios');

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username,NULL,id,deleted_at,NULL',
            'password' => 'required|string|min:6',
            'email' => 'nullable|email|max:255',
            'celular' => 'nullable|string|max:50',
            'ci' => 'nullable|string|max:50',
            'rol' => ['required', Rule::exists('roles', 'name')],
        ]);

        $user = User::create([
            'name' => mb_strtoupper($request->name),
            'username' => $request->username,
            'email' => $request->email,
            'celular' => $request->celular,
            'ci' => $request->ci,
            'password' => bcrypt($request->password),
        ]);
        $user->syncRoles([$request->rol]);

        return response()->json($this->sessionPayload($user), 201);
    }

    public function update(Request $request, $id)
    {
        $this->requirePermission($request, 'Editar Usuarios');

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'celular' => 'nullable|string|max:50',
            'ci' => 'nullable|string|max:50',
            'rol' => ['sometimes', 'required', Rule::exists('roles', 'name')],
        ]);

        $data = $request->only(['name', 'email', 'celular', 'ci']);
        if (isset($data['name'])) {
            $data['name'] = mb_strtoupper($data['name']);
        }

        $user->update($data);
        if ($request->filled('rol')) {
            // Nadie se quita a sí mismo el rol de administrador por accidente.
            abort_if($user->id === $request->user()->id && $user->hasRole('Administrador') && $request->rol !== 'Administrador', 422,
                'No puede quitarse a sí mismo el rol de Administrador');
            $user->syncRoles([$request->rol]);
        }

        return response()->json($this->sessionPayload($user->fresh()));
    }

    public function destroy(Request $request, $id)
    {
        $this->requirePermission($request, 'Eliminar Usuarios');

        $user = User::findOrFail($id);
        abort_if($user->id === $request->user()->id, 422, 'No puede eliminar su propio usuario');

        if ($user->avatar) {
            $path = public_path('images/'.$user->avatar);
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $user->delete();

        return response()->json(['message' => 'Usuario eliminado']);
    }

    public function resetPassword(Request $request, $id)
    {
        $this->requirePermission($request, 'Editar Usuarios');

        User::findOrFail($id)->update(['password' => bcrypt('123456')]);

        return response()->json(['message' => 'Contraseña restablecida a 123456']);
    }

    // ── Avatar ───────────────────────────────────────────────

    public function uploadAvatar(Request $request, $id)
    {
        $this->requirePermission($request, ['Crear Usuarios', 'Editar Usuarios']);

        $request->validate(['avatar' => 'required|image|max:8192']);

        $user = User::findOrFail($id);

        if ($user->avatar) {
            $old = public_path('images/'.$user->avatar);
            if (file_exists($old)) {
                @unlink($old);
            }
        }

        $src = imagecreatefromstring(file_get_contents($request->file('avatar')->getPathname()));
        abort_unless($src, 422, 'No se pudo procesar la imagen');

        $ow = imagesx($src);
        $oh = imagesy($src);
        $max = 300;
        $scale = ($ow > $max || $oh > $max) ? min($max / $ow, $max / $oh) : 1;
        $nw = (int) round($ow * $scale);
        $nh = (int) round($oh * $scale);

        $dst = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
        imagedestroy($src);

        $dir = public_path('images');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'avatar_'.$id.'_'.time().'.webp';
        imagewebp($dst, $dir.'/'.$filename, 85);
        imagedestroy($dst);

        $user->update(['avatar' => $filename]);

        return response()->json(['avatar' => $filename]);
    }

    // ── Permisos ─────────────────────────────────────────────

    public function permissions(Request $request)
    {
        $this->requirePermission($request, ['Crear Usuarios', 'Editar Usuarios', 'Gestionar Roles']);

        return response()->json(Permission::orderBy('orden')->orderBy('id')
            ->get(['id', 'name', 'grupo', 'orden']));
    }

    /** Permisos extra del usuario, además de los que le da su rol. */
    public function updateUserPermissions(Request $request, $id)
    {
        $this->requirePermission($request, 'Gestionar Roles');

        $user = User::findOrFail($id);
        $user->syncPermissions(Permission::whereIn('id', $request->permissions ?? [])->get());

        return response()->json($user->permissions()->pluck('name'));
    }

    // ── Roles ────────────────────────────────────────────────

    public function roles(Request $request)
    {
        $this->requirePermission($request, ['Ver Usuarios', 'Gestionar Roles']);

        $users = DB::table(config('permission.table_names.model_has_roles'))
            ->selectRaw('role_id, COUNT(*) as total')->groupBy('role_id')->pluck('total', 'role_id');

        return response()->json(Role::with('permissions:id,name')->orderBy('id')->get()
            ->map(fn (Role $role) => [
                'id' => $role->id, 'name' => $role->name, 'users_count' => (int) ($users[$role->id] ?? 0),
                'permissions' => $role->permissions->pluck('id'),
            ]));
    }

    public function storeRole(Request $request)
    {
        $this->requirePermission($request, 'Gestionar Roles');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::create(['name' => trim($data['name']), 'guard_name' => 'web']);
        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());

        return response()->json($role->load('permissions:id,name'), 201);
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->requirePermission($request, 'Gestionar Roles');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
        // El administrador siempre conserva todos los permisos: si no, se puede perder el acceso al sistema.
        abort_if($role->name === 'Administrador' && $data['name'] !== 'Administrador', 422, 'El rol Administrador no se puede renombrar');

        $role->update(['name' => trim($data['name'])]);
        $role->syncPermissions($role->name === 'Administrador'
            ? Permission::all()
            : Permission::whereIn('id', $data['permissions'] ?? [])->get());

        return response()->json($role->load('permissions:id,name'));
    }

    public function destroyRole(Request $request, Role $role)
    {
        $this->requirePermission($request, 'Gestionar Roles');
        abort_if($role->name === 'Administrador', 422, 'El rol Administrador no se puede eliminar');
        abort_if(DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $role->id)->exists(), 422, 'No se puede eliminar un rol que tiene usuarios asignados');
        $role->delete();

        return response()->json(['message' => 'Rol eliminado']);
    }

    // ── Helper ───────────────────────────────────────────────

    private function requirePermission(Request $request, string|array $permission): void
    {
        abort_unless($request->user()?->hasAnyPermission((array) $permission), 403, 'No tiene permiso para realizar esta acción');
    }
}

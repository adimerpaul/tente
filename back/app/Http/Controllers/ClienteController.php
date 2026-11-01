<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, ['Ver Clientes', 'Crear Ventas']);
        $query = Cliente::query()->withCount(['ventas' => fn ($q) => $q->where('estado', 'COMPLETADA')])
            ->withSum(['ventas' => fn ($q) => $q->where('estado', 'COMPLETADA')], 'total');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$search}%")
                ->orWhere('documento', 'like', "%{$search}%")
                ->orWhere('celular', 'like', "%{$search}%"));
        }

        $perPage = (int) $request->input('per_page', 20);

        return response()->json($query->orderBy('nombre')->paginate(min(max($perPage, 1), 200)));
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Clientes');

        return response()->json(Cliente::create($this->validatedData($request)), 201);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $this->authorizeAction($request, 'Editar Clientes');
        abort_if($cliente->id === Cliente::SIN_NOMBRE, 422, 'El cliente genérico no se puede modificar');
        $cliente->update($this->validatedData($request, $cliente));

        return response()->json($cliente->fresh());
    }

    public function destroy(Request $request, Cliente $cliente)
    {
        $this->authorizeAction($request, 'Eliminar Clientes');
        abort_if($cliente->id === Cliente::SIN_NOMBRE, 422, 'El cliente genérico no se puede eliminar');
        $cliente->delete();

        return response()->json(['message' => 'Cliente eliminado']);
    }

    private function validatedData(Request $request, ?Cliente $cliente = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:50', Rule::unique('clientes')->whereNull('deleted_at')->ignore($cliente)],
            'celular' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ]);
        $data['nombre'] = mb_strtoupper(trim($data['nombre']));

        return $data;
    }

    private function authorizeAction(Request $request, string|array $permission): void
    {
        abort_unless($request->user()?->hasAnyPermission((array) $permission), 403, 'No tiene permiso para realizar esta acción');
    }
}

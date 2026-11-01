<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    /**
     * Menú. per_page=0 devuelve todo (lo usa la pantalla de venta);
     * ?disponibles=1 deja sólo lo que se puede vender ahora.
     */
    public function index(Request $request)
    {
        $this->authorizeAction($request, ['Ver Productos', 'Crear Ventas']);
        $query = Producto::query();

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('codigo', 'like', "%{$search}%")
                ->orWhere('nombre', 'like', "%{$search}%")
                ->orWhere('categoria', 'like', "%{$search}%"));
        }
        if ($categoria = trim((string) $request->input('categoria'))) {
            $query->where('categoria', $categoria);
        }
        if ($request->boolean('disponibles')) {
            $query->where('disponible', true);
        }

        $query->orderBy('orden')->orderBy('nombre');
        $perPage = (int) $request->input('per_page', 20);

        return response()->json($query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500)));
    }

    public function catalogos(Request $request)
    {
        $this->authorizeAction($request, ['Ver Productos', 'Crear Ventas']);

        return response()->json([
            'categorias' => Producto::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Productos');
        $data = $this->validatedData($request);
        $data['orden'] ??= (int) Producto::max('orden') + 1;

        return response()->json(Producto::create($data), 201);
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $producto->update($this->validatedData($request, $producto));

        return response()->json($producto->fresh());
    }

    /** Activa o quita un plato de la venta (se acabó, fuera de temporada). */
    public function toggleDisponible(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $producto->update(['disponible' => ! $producto->disponible]);

        return response()->json($producto->fresh());
    }

    public function destroy(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Eliminar Productos');
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    private function validatedData(Request $request, ?Producto $producto = null): array
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('productos')->whereNull('deleted_at')->ignore($producto)],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'disponible' => ['sometimes', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);
        foreach (['codigo', 'nombre', 'categoria'] as $field) {
            $data[$field] = isset($data[$field]) ? mb_strtoupper(trim($data[$field])) : null;
        }
        if (! isset($data['orden'])) {
            unset($data['orden']);
        }

        return $data;
    }

    public function uploadPhoto(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $request->validate(['foto' => ['required', 'image', 'max:8192']]);

        $image = imagecreatefromstring(file_get_contents($request->file('foto')->getPathname()));
        abort_unless($image, 422, 'No se pudo procesar la fotografía');

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 800 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $output = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($output, 255, 255, 255);
        imagefill($output, 0, 0, $white);
        imagecopyresampled($output, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $directory = public_path('images/productos');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = "producto_{$producto->id}_".time().'.webp';
        imagewebp($output, "{$directory}/{$filename}", 88);
        imagedestroy($image);
        imagedestroy($output);

        $this->deletePhoto($producto);
        $producto->update(['foto' => "productos/{$filename}"]);

        return response()->json($producto->fresh());
    }

    /**
     * Sólo se borran las fotos subidas desde el sistema (producto_*.webp): las del menú
     * inicial están versionadas y pueden compartirlas varios productos (chico/grande).
     */
    private function deletePhoto(Producto $producto): void
    {
        if ($producto->foto && str_starts_with(basename($producto->foto), 'producto_')) {
            $path = public_path('images/'.$producto->foto);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function authorizeAction(Request $request, string|array $permission): void
    {
        abort_unless($request->user()?->hasAnyPermission((array) $permission), 403, 'No tiene permiso para realizar esta acción');
    }
}

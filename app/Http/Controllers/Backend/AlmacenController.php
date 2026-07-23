<?php

namespace App\Http\Controllers\Backend;

use App\Exports\AlmacenesExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\AlmacenesImport;
use App\Models\Almacen;
use App\Models\Empleado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AlmacenController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventario.almacenes.create', only: ['create', 'store']),
            new Middleware('permission:inventario.almacenes.edit', only: ['edit', 'update']),
            new Middleware('permission:inventario.almacenes.delete', only: ['destroy']),
            new Middleware('permission:inventario.almacenes.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:inventario.almacenes.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index()
    {
        $userId = Auth::user()->getOwnerId();
        $empleados = Empleado::where('estado', 'Activo')
            ->where('owner_id', $userId)
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get();

        $almacenes = Almacen::with('empleados')
            ->where('owner_id', $userId)
            ->orderBy('nombre')
            ->paginate(15)
            ->through(function ($almacen) {
                return [
                    'id' => $almacen->id,
                    'nombre' => $almacen->nombre,
                    'codigo' => $almacen->codigo,
                    'direccion' => $almacen->direccion,
                    'telefono' => $almacen->telefono,
                    'responsable' => $almacen->responsable,
                    'capacidad' => $almacen->capacidad,
                    'tipo' => $almacen->tipo,
                    'activo' => $almacen->activo,
                    'notas' => $almacen->notas,
                    'imagenes' => $almacen->imagenes ?? [],
                    'video' => $almacen->video,
                    'empleados' => $almacen->empleados,
                    'created_at' => $almacen->created_at,
                    'updated_at' => $almacen->updated_at,
                ];
            });

        return inertia('Backend/Almacenes/Index', [
            'almacenes' => $almacenes,
            'empleados' => $empleados,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('almacenes')->where('owner_id', $ownerId),
            ],
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string|max:50',
            'responsable_id' => 'nullable|integer|exists:empleados,id',
            'capacidad' => 'nullable|integer|min:0',
            'tipo' => 'required|string|max:50',
            'activo' => 'boolean',
            'notas' => 'nullable|string',
            'imagenes' => 'nullable|array',
            'imagenes.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'video' => 'nullable|mimes:mp4,webm,ogv|max:51200',
        ], [
            'codigo.unique' => 'Este código ya está en uso en otro almacén de su organización.',
        ]);

        $validated['owner_id'] = $ownerId;
        $validated['user_id'] = Auth::id();

        // Manejar imágenes
        $imagenesUrls = [];
        $archivos = $request->file('imagenes');

        if ($archivos) {
            // Filtrar solo archivos válidos (no nulls)
            $archivosValidos = array_filter($archivos, function ($file) {
                return $file !== null && $file instanceof UploadedFile;
            });

            foreach ($archivosValidos as $imagen) {
                if ($imagen && $imagen->isValid()) {
                    $path = $imagen->store('almacenes/imagenes', 'public');
                    $imagenesUrls[] = Storage::url($path);
                }
            }
        }
        $validated['imagenes'] = $imagenesUrls;

        // Manejar video
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            if ($video) {
                $videoPath = $video->store('almacenes/videos', 'public');
                $validated['video'] = Storage::url($videoPath);
            }
        }

        $almacen = Almacen::create($validated);

        if (! empty($validated['responsable_id'])) {
            $empleado = Empleado::find($validated['responsable_id']);
            if ($empleado) {
                $almacen->update(['responsable' => trim($empleado->nombre.' '.$empleado->apellido)]);
                $empleado->update(['almacen_id' => $almacen->id]);
            }
        }

        return redirect()->route('almacenes.index')->with('success', 'Almacén creado correctamente');
    }

    public function update(Request $request, Almacen $almacene): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('almacenes', 'codigo')
                    ->where('owner_id', $ownerId)
                    ->ignore($almacene->id),
            ],
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string|max:50',
            'responsable_id' => 'nullable|integer|exists:empleados,id',
            'capacidad' => 'nullable|integer|min:0',
            'tipo' => 'required|string|max:50',
            'activo' => 'boolean',
            'notas' => 'nullable|string',
            'imagenes.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'video' => 'nullable|mimes:mp4,webm,ogv|max:51200',
        ], [
            'codigo.unique' => 'Este código ya está en uso en otro almacén de su organización.',
        ]);

        // Manejar imágenes nuevas
        $imagenesUrls = $almacene->imagenes ?? [];
        $archivos = $request->file('imagenes');

        // Solo procesar si hay archivos nuevos válidos
        if ($archivos) {
            $archivosValidos = array_filter($archivos, function ($file) {
                return $file !== null && $file instanceof UploadedFile && $file->isValid();
            });

            // Solo eliminar y reemplazar si hay nuevas imágenes válidas
            if (count($archivosValidos) > 0) {
                // Eliminar imágenes anteriores
                foreach ($imagenesUrls as $imgUrl) {
                    if ($imgUrl) {
                        $path = str_replace('/storage/', '', $imgUrl);
                        Storage::delete($path);
                    }
                }
                // Guardar nuevas imágenes
                $imagenesUrls = [];
                foreach ($archivosValidos as $imagen) {
                    $path = $imagen->store('almacenes/imagenes', 'public');
                    $imagenesUrls[] = Storage::url($path);
                }
            }
        }
        $validated['imagenes'] = $imagenesUrls;

        // Manejar video nuevo
        if ($request->hasFile('video')) {
            // Eliminar video anterior
            if ($almacene->video) {
                $path = str_replace('/storage/', '', $almacene->video);
                Storage::delete($path);
            }
            $video = $request->file('video');
            if ($video) {
                $videoPath = $video->store('almacenes/videos', 'public');
                $validated['video'] = Storage::url($videoPath);
            }
        }

        // Usamos una transacción para asegurar que los empleados y el almacén se actualicen juntos
        DB::transaction(function () use (&$validated, $almacene, $request) {
            // Actualización lógica del responsable
            if ($request->has('responsable_id')) {
                // Quitamos el almacén al responsable anterior
                Empleado::where('almacen_id', $almacene->id)->update(['almacen_id' => null]);

                // Asignamos el nuevo si existe
                if ($request->filled('responsable_id')) {
                    $empleado = Empleado::find($request->responsable_id);
                    if ($empleado) {
                        $validated['responsable'] = trim($empleado->nombre.' '.$empleado->apellido);
                        $empleado->update(['almacen_id' => $almacene->id]);
                    }
                } else {
                    $validated['responsable'] = null;
                }
            }

            $almacene->update($validated);
        });

        return redirect()->back()->with('success', 'Almacén actualizado correctamente');
    }

    public function destroy(Almacen $almacene): RedirectResponse
    {
        $almacene->delete();

        return redirect()->route('almacenes.index');
    }

    public function show(Almacen $almacen)
    {
        $almacen->load('empleados');

        return Inertia::render('Backend/Almacenes/Show', [
            'almacen' => $almacen,
        ]);
    }

    public function getExportClass(array $filters): object
    {
        return new AlmacenesExport($filters);
    }

    public function getImportClass(): object
    {
        return new AlmacenesImport;
    }
}

<?php

namespace App\Http\Controllers\LMS;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Course;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InstructorCourseController extends Controller
{
    public function index()
    {
        $courses = Course::where('instructor_id', auth()->id())->latest()->get();

        return Inertia::render('LMS/Instructor/Courses/Index', ['courses' => $courses]);
    }

    public function create()
    {
        $categories = Categoria::all();

        return Inertia::render('LMS/Instructor/Courses/Create', ['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categorias,id',
            'price' => 'required|numeric|min:0',
            'cover_image' => 'nullable|image|max:2048',
            'promo_video_url' => 'nullable|url|max:255',
            'features' => 'nullable',
        ]);

        if (isset($validated['features']) && is_string($validated['features'])) {
            $validated['features'] = json_decode($validated['features'], true);
        }

        $validated['slug'] = Str::slug($validated['title']).'-'.rand(100, 999);
        $validated['instructor_id'] = auth()->id();
        $validated['owner_id'] = auth()->user()->getOwnerId();

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('courses', 'public');
        }

        $course = Course::create($validated);
        $this->syncProduct($course);

        return redirect()->route('lms.instructor.cursos.edit', $course->id);
    }

    public function edit(Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $course->load('modules.lessons');

        // Force cast for stability
        if ($course->features && is_string($course->features)) {
            $course->features = json_decode($course->features, true);
        }

        $categories = Categoria::all();

        return Inertia::render('LMS/Instructor/Courses/Edit', ['course' => $course, 'categories' => $categories]);
    }

    public function update(Request $request, Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categorias,id',
            'price' => 'required|numeric|min:0',
            'is_published' => 'boolean',
            'cover_image' => 'nullable',
            'promo_video_url' => 'nullable|url|max:255',
            'features' => 'nullable',
            'certificado_html' => 'nullable|string',
        ]);

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('courses', 'public');
        }

        // Handle possible stringified JSON from FormData
        if (isset($validated['features']) && is_string($validated['features'])) {
            $validated['features'] = json_decode($validated['features'], true);
        }

        if ($request->has('features')) {
            $validated['features'] = $request->input('features');
            if (is_string($validated['features'])) {
                $validated['features'] = json_decode($validated['features'], true);
            }
        }

        $course->update($validated);
        $this->syncProduct($course);

        return back()->with('success', 'Curso actualizado.');
    }

    public function publish(Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403);
        }
        $course->update(['is_published' => ! $course->is_published]);
        $this->syncProduct($course->fresh());

        return back();
    }

    public function certificatePreview(Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $html = $course->certificado_html;

        if (! $html) {
            return response()->json(['html' => '']);
        }

        $html = $this->replaceCertificateVars($html, [
            'student_name' => auth()->user()->name,
            'course_title' => $course->title,
            'instructor_name' => auth()->user()->name,
            'date' => now()->format('d/m/Y'),
            'certificate_number' => 'CERT-'.$course->id.'-PREVIEW',
            'institution_name' => config('app.name'),
        ]);

        return response()->json(['html' => $html]);
    }

    public function certificateDownloadPdf(Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $html = $course->certificado_html;

        if (! $html) {
            return back()->with('error', 'No hay HTML de certificado configurado.');
        }

        $html = $this->replaceCertificateVars($html, [
            'student_name' => '[Nombre del Alumno]',
            'course_title' => $course->title,
            'instructor_name' => auth()->user()->name,
            'date' => now()->format('d/m/Y'),
            'certificate_number' => 'CERT-'.$course->id.'-PREVIEW',
            'institution_name' => config('app.name'),
        ]);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('certificado-'.$course->slug.'.pdf');
    }

    public function destroy(Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $course->delete();

        return redirect()->route('lms.instructor.cursos.index')->with('success', 'Curso eliminado correctamente.');
    }

    private function replaceCertificateVars(string $html, array $vars): string
    {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{'.$key.'}'] = e($value);
        }

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    private function syncProduct(Course $course)
    {
        Producto::updateOrCreate([
            'course_id' => $course->id,
        ], [
            'nombre' => $course->title,
            'descripcion' => $course->description,
            'precio_venta' => $course->price,
            'categoria_id' => $course->category_id,
            'owner_id' => $course->owner_id,
            'unidad_medida' => 'unidad',
            'is_service' => true,
            'activo' => $course->is_published ?? false,
            'peso_base' => 0,
            'codigo' => 'COURSE-'.strtoupper($course->id).'-'.rand(100, 999),
        ]);
    }
}

<?php

namespace App\Http\Controllers\LMS;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Course;
use App\Models\DetalleVenta;
use App\Models\Enrollment;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::where('is_published', true)
            ->with('instructor')
            ->latest()
            ->get();

        return Inertia::render('LMS/Courses/Index', [
            'courses' => $courses,
        ]);
    }

    public function show(Course $course)
    {
        $course->load('instructor', 'modules.lessons');

        $isEnrolled = auth()->check()
            ? Enrollment::where('student_id', auth()->id())->where('course_id', $course->id)->exists()
            : false;

        // Force cast for stability
        if ($course->features && is_string($course->features)) {
            $course->features = json_decode($course->features, true);
        }

        return Inertia::render('LMS/Courses/Show', [
            'course' => $course,
            'isEnrolled' => $isEnrolled,
        ]);
    }

    public function enroll(Course $course)
    {
        if (! $course->is_published) {
            abort(403, 'No es posible inscribirse en un curso que no ha sido publicado.');
        }

        return DB::transaction(function () use ($course) {
            $enrollment = Enrollment::firstOrCreate([
                'student_id' => auth()->id(),
                'course_id' => $course->id,
            ], [
                'status' => 'active',
                'paid_amount' => $course->price,
            ]);

            // If it's a paid course and we just enrolled, record a sale in ERP
            if ($course->price > 0 && $enrollment->wasRecentlyCreated) {
                $ownerId = $course->owner_id ?? auth()->user()->getOwnerId();

                // Find or create associated product
                $producto = Producto::where('course_id', $course->id)->first();

                if ($producto) {
                    $cliente = Cliente::firstOrCreate([
                        'user_id' => auth()->id(),
                        'owner_id' => $ownerId,
                    ], [
                        'nombre' => auth()->user()->name,
                        'email' => auth()->user()->email,
                        'activo' => true,
                    ]);

                    $venta = Venta::create([
                        'owner_id' => $ownerId,
                        'user_id' => auth()->id(),
                        'cliente_id' => $cliente->id,
                        'fecha' => now(),
                        'subtotal' => $course->price,
                        'iva' => 0,
                        'total' => $course->price,
                        'metodo_pago' => 'transferencia',
                        'estado' => 'pagada',
                        'notas' => "Venta de curso: {$course->title}",
                        'numero' => 'LMS-'.rand(1000, 9999),
                    ]);

                    DetalleVenta::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $producto->id,
                        'cantidad' => 1,
                        'precio_unitario' => $course->price,
                        'subtotal' => $course->price,
                    ]);
                }
            }

            return redirect()->route('lms.student.courses')->with('success', 'Te has inscrito correctamente.');
        });
    }
}

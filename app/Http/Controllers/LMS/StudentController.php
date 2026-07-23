<?php

namespace App\Http\Controllers\LMS;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function myCourses()
    {
        $user = auth()->user();
        $enrollments = Enrollment::where('student_id', $user->id)
            ->with(['course.instructor', 'course.lessons'])
            ->get()
            ->map(function ($enrollment) use ($user) {
                $totalLessons = $enrollment->course->lessons->count();
                $completedLessons = CourseProgress::where('user_id', $user->id)
                    ->whereIn('lesson_id', $enrollment->course->lessons->pluck('id'))
                    ->where('is_completed', true)
                    ->count();

                $enrollment->progress_percentage = $totalLessons > 0
                    ? round(($completedLessons / $totalLessons) * 100)
                    : 0;

                return $enrollment;
            });

        return Inertia::render('LMS/Student/Courses/Index', [
            'enrollments' => $enrollments,
        ]);
    }

    public function progress()
    {
        $user = auth()->user();
        $enrollments = Enrollment::where('student_id', $user->id)
            ->with(['course.instructor', 'course.lessons'])
            ->get()
            ->map(function ($enrollment) use ($user) {
                $totalLessons = $enrollment->course->lessons->count();
                $completedLessons = CourseProgress::where('user_id', $user->id)
                    ->whereIn('lesson_id', $enrollment->course->lessons->pluck('id'))
                    ->where('is_completed', true)
                    ->count();

                $enrollment->progress_percentage = $totalLessons > 0
                    ? round(($completedLessons / $totalLessons) * 100)
                    : 0;

                // Simulación de notas baseada en progreso para el ejemplo
                $enrollment->grade = $enrollment->progress_percentage > 0 ? round(($enrollment->progress_percentage / 100) * 7.0, 1) : 0;

                return $enrollment;
            });

        return Inertia::render('LMS/Student/Progress', [
            'enrollments' => $enrollments,
        ]);
    }

    public function instructorDashboard()
    {
        $myCoursesCount = Course::where('instructor_id', auth()->id())->count();
        $totalStudents = Enrollment::whereIn('course_id', Course::where('instructor_id', auth()->id())->pluck('id'))->count();

        return Inertia::render('LMS/Instructor/Dashboard', [
            'stats' => [
                'courses' => $myCoursesCount,
                'students' => $totalStudents,
                'revenue' => 0,
            ],
        ]);
    }

    public function generateCertificate(Course $course)
    {
        $enrollment = Enrollment::where('student_id', auth()->id())
            ->where('course_id', $course->id)
            ->first();

        if (! $enrollment) {
            abort(403, 'No estás inscrito en este curso.');
        }

        $totalLessons = $course->lessons()->count();
        $completedLessons = CourseProgress::where('user_id', auth()->id())
            ->whereIn('lesson_id', $course->lessons()->pluck('id'))
            ->where('is_completed', true)
            ->count();

        if ($completedLessons < $totalLessons) {
            return back()->with('error', 'Debes completar todas las lecciones para obtener el certificado.');
        }

        $certificate = Certificate::firstOrCreate(
            [
                'user_id' => auth()->id(),
                'course_id' => $course->id,
            ],
            [
                'certificate_number' => 'CERT-'.$course->id.'-'.auth()->id().'-'.now()->format('Ymd'),
                'issue_date' => now()->toDateString(),
                'metadata' => [
                    'course_title' => $course->title,
                    'instructor' => $course->instructor->name,
                    'completed_lessons' => $completedLessons,
                    'total_lessons' => $totalLessons,
                ],
            ]
        );

        return redirect()->route('lms.student.courses')
            ->with('success', '¡Felicitaciones! Tu certificado ha sido generado.');
    }
}

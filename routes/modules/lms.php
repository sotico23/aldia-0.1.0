<?php

use App\Http\Controllers\LMS\CourseController;
use App\Http\Controllers\LMS\InstructorCourseController;
use App\Http\Controllers\LMS\LessonController;
use App\Http\Controllers\LMS\ModuleController;
use App\Http\Controllers\LMS\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Alumno Routes — require lms.cursos.viewAny (students can browse)
    Route::middleware(['permission:lms.cursos.viewAny'])->group(function () {
        Route::get('/cursos', [CourseController::class, 'index'])->name('lms.courses.index');
        Route::get('/cursos/{course:slug}', [CourseController::class, 'show'])->name('lms.courses.show');
        Route::post('/cursos/{course}/enroll', [CourseController::class, 'enroll'])->name('lms.courses.enroll');
    });

    Route::middleware(['permission:lms.alumnos.viewAny'])->group(function () {
        Route::get('/alumno/cursos', [StudentController::class, 'myCourses'])->name('lms.student.courses');
        Route::get('/alumno/progreso', [StudentController::class, 'progress'])->name('lms.student.progress');
    });

    Route::middleware(['permission:lms.lecciones.viewAny'])->group(function () {
        Route::get('/lecciones/{lesson:slug}', [LessonController::class, 'show'])->name('lms.lessons.show');
        Route::post('/lecciones/{lesson}/complete', [LessonController::class, 'complete'])->name('lms.lessons.complete');
    });

    // Instructor Routes — require create/edit permissions + ownership scoping
    Route::middleware(['permission:lms.cursos.create'])->group(function () {
        Route::name('lms.instructor.')->prefix('instructor')->group(function () {
            Route::get('/dashboard', [StudentController::class, 'instructorDashboard'])->name('dashboard');
            Route::resource('cursos', InstructorCourseController::class)
                ->except(['show'])
                ->parameters(['cursos' => 'course'])
                ->middleware('ownership:course');
            Route::post('cursos/{course}/publish', [InstructorCourseController::class, 'publish'])->name('cursos.publish')->middleware('ownership:course');

            // Módulos
            Route::post('cursos/{course}/modules', [ModuleController::class, 'store'])->name('modules.store')->middleware('ownership:course');
            Route::put('modules/{module}', [ModuleController::class, 'update'])->name('modules.update')->middleware('ownership:module');
            Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy')->middleware('ownership:module');

            // Lecciones
            Route::post('modules/{module}/lessons', [LessonController::class, 'store'])->name('lessons.store')->middleware('ownership:module');
            Route::put('lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update')->middleware('ownership:lesson');
            Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy')->middleware('ownership:lesson');

            // Quiz
            Route::post('lessons/{lesson}/quiz', [LessonController::class, 'storeQuiz'])->name('lessons.quiz.store')->middleware('ownership:lesson');
            Route::put('quizzes/{quiz}', [LessonController::class, 'updateQuiz'])->name('quizzes.update')->middleware('ownership:quiz');
            Route::delete('quizzes/{quiz}', [LessonController::class, 'destroyQuiz'])->name('quizzes.destroy')->middleware('ownership:quiz');

            // Certificates
            Route::get('courses/{course}/certificate', [StudentController::class, 'generateCertificate'])->name('courses.certificate')->middleware('ownership:course');
            Route::get('cursos/{course}/certificate-preview', [InstructorCourseController::class, 'certificatePreview'])->name('cursos.certificate-preview')->middleware('ownership:course');
            Route::get('cursos/{course}/certificate-download', [InstructorCourseController::class, 'certificateDownloadPdf'])->name('cursos.certificate-download')->middleware('ownership:course');
        });
    });
});

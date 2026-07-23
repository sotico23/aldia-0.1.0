<?php

namespace App\Http\Controllers\LMS;

use App\Http\Controllers\Controller;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LessonController extends Controller
{
    /**
     * Verify the user is enrolled in the course that owns this lesson.
     * Aborts 403 if not enrolled.
     */
    private function verifyEnrollment(Lesson $lesson): Enrollment
    {
        $courseId = $lesson->module->course_id;

        $enrollment = Enrollment::where('student_id', auth()->id())
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            abort(403, 'No estás inscrito en este curso.');
        }

        return $enrollment;
    }

    /**
     * Check if the lesson is the first in the course or if the previous lesson is completed.
     */
    private function verifySequence(Lesson $lesson, int $userId): bool
    {
        $course = $lesson->module->course;
        $course->load('modules.lessons');

        $allLessons = $course->modules
            ->sortBy('order')
            ->flatMap(fn ($module) => $module->lessons->sortBy('order'))
            ->values();

        $currentIndex = $allLessons->search(fn ($l) => $l->id === $lesson->id);

        // First lesson in the course — always allowed
        if ($currentIndex === 0) {
            return true;
        }

        // Get the previous lesson
        $previousLesson = $allLessons[$currentIndex - 1];

        return CourseProgress::where('user_id', $userId)
            ->where('lesson_id', $previousLesson->id)
            ->where('is_completed', true)
            ->exists();
    }

    /**
     * Generate a time-based view token for content consumption verification.
     */
    public static function generateViewToken(int $lessonId, int $userId): string
    {
        $payload = $lessonId.'|'.$userId.'|'.floor(time() / 300); // rotates every 5 min

        return hash_hmac('sha256', $payload, config('app.key'));
    }

    /**
     * Validate the view token sent from the frontend.
     */
    private function validateViewToken(int $lessonId, int $userId, ?string $token): bool
    {
        if (! $token) {
            return false;
        }

        // Check current window and previous window (allows token generated up to 5 min ago)
        for ($i = 0; $i <= 1; $i++) {
            $payload = $lessonId.'|'.$userId.'|'.(floor(time() / 300) - $i);
            if (hash_hmac('sha256', $payload, config('app.key')) === $token) {
                return true;
            }
        }

        return false;
    }

    public function show(Lesson $lesson)
    {
        $this->verifyEnrollment($lesson);

        $lesson->load('module.course.modules.lessons', 'quiz');

        $course = $lesson->module->course;
        $course->load('modules.lessons');

        $allLessons = $course->modules->flatMap(fn ($module) => $module->lessons)->sortBy('order')->values();

        $currentIndex = $allLessons->search(fn ($l) => $l->id === $lesson->id);
        $previousLesson = $currentIndex > 0 ? $allLessons[$currentIndex - 1] : null;
        $nextLesson = $currentIndex < $allLessons->count() - 1 ? $allLessons[$currentIndex + 1] : null;

        $courseModules = $course->modules->map(fn ($module) => [
            'id' => $module->id,
            'title' => $module->title,
            'order' => $module->order,
            'lessons' => $module->lessons->map(fn ($l) => [
                'id' => $l->id,
                'title' => $l->title,
                'slug' => $l->slug,
                'order' => $l->order,
            ]),
        ]);

        $viewToken = self::generateViewToken($lesson->id, auth()->id());

        return Inertia::render('LMS/Lessons/Show', [
            'lesson' => $lesson,
            'courseModules' => $courseModules,
            'nextLesson' => $nextLesson ? ['id' => $nextLesson->id, 'title' => $nextLesson->title, 'slug' => $nextLesson->slug] : null,
            'previousLesson' => $previousLesson ? ['id' => $previousLesson->id, 'title' => $previousLesson->title, 'slug' => $previousLesson->slug] : null,
            'viewToken' => $viewToken,
        ]);
    }

    public function store(Request $request, Module $module)
    {
        if ($module->course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'nullable|string',
        ]);

        $order = $module->lessons()->max('order') + 1;

        $lesson = $module->lessons()->create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'video_url' => $validated['video_url'],
            'slug' => Str::slug($validated['title']).'-'.rand(100, 999),
            'order' => $order,
            'content_text' => '',
        ]);

        return back()->with('success', 'Lección añadida.');
    }

    public function update(Request $request, Lesson $lesson)
    {
        if ($lesson->module->course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content_text' => 'nullable|string',
            'video_url' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $lesson->update($validated);

        return back()->with('success', 'Lección actualizada.');
    }

    public function destroy(Lesson $lesson)
    {
        if ($lesson->module->course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $lesson->delete();

        return back()->with('success', 'Lección eliminada.');
    }

    public function complete(Request $request, Lesson $lesson)
    {
        $this->verifyEnrollment($lesson);

        $validated = $request->validate([
            'view_token' => 'required|string',
        ]);

        if (! $this->validateViewToken($lesson->id, auth()->id(), $validated['view_token'])) {
            abort(403, 'Token de reproducción inválido o expirado. Debes visualizar el contenido antes de completar la lección.');
        }

        if (! $this->verifySequence($lesson, auth()->id())) {
            abort(403, 'Debes completar la lección anterior antes de avanzar.');
        }

        CourseProgress::firstOrCreate([
            'user_id' => auth()->id(),
            'lesson_id' => $lesson->id,
        ], [
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Lección completada.');
    }

    public function storeQuiz(Request $request, Lesson $lesson)
    {
        if ($lesson->module->course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $quiz = $lesson->quiz()->create([
            'title' => $validated['title'],
        ]);

        return back()->with('success', 'Quiz creado.');
    }

    public function updateQuiz(Request $request, Quiz $quiz)
    {
        if ($quiz->lesson->module->course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $quiz->update($validated);

        return back()->with('success', 'Quiz actualizado.');
    }

    public function destroyQuiz(Quiz $quiz)
    {
        if ($quiz->lesson->module->course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $quiz->delete();

        return back()->with('success', 'Quiz eliminado.');
    }
}

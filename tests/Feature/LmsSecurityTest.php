<?php

use App\Http\Controllers\LMS\LessonController;
use App\Models\Course;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'lms.cursos.viewAny']);
    Permission::firstOrCreate(['name' => 'lms.cursos.create']);
    Permission::firstOrCreate(['name' => 'lms.alumnos.viewAny']);
    Permission::firstOrCreate(['name' => 'lms.lecciones.viewAny']);

    $this->owner = User::factory()->create(['business_name' => 'LMS Test']);
    $this->instructor = User::factory()->create(['creator_id' => $this->owner->id]);
    $this->student = User::factory()->create(['creator_id' => $this->owner->id]);
    $this->outsider = User::factory()->create(['creator_id' => $this->owner->id]);

    $this->course = Course::create([
        'instructor_id' => $this->instructor->id,
        'owner_id' => $this->owner->id,
        'title' => 'Curso de Laravel',
        'slug' => 'curso-de-laravel',
        'description' => 'Un curso completo',
        'price' => 0,
        'is_published' => true,
    ]);

    $this->module = Module::create([
        'course_id' => $this->course->id,
        'owner_id' => $this->owner->id,
        'title' => 'Módulo 1',
        'order' => 1,
    ]);

    $this->lesson1 = Lesson::create([
        'module_id' => $this->module->id,
        'owner_id' => $this->owner->id,
        'title' => 'Lección 1',
        'slug' => 'leccion-1',
        'order' => 1,
        'content_text' => 'Contenido de la lección 1',
    ]);

    $this->lesson2 = Lesson::create([
        'module_id' => $this->module->id,
        'owner_id' => $this->owner->id,
        'title' => 'Lección 2',
        'slug' => 'leccion-2',
        'order' => 2,
        'content_text' => 'Contenido de la lección 2',
    ]);

    $this->student->syncPermissions([
        'lms.cursos.viewAny',
        'lms.alumnos.viewAny',
        'lms.lecciones.viewAny',
    ]);
});

// ─── Enrollment Verification (Anti-Piratería) ───────────────────

describe('Enrollment Verification', function () {

    test('unenrolled student cannot view lesson content', function () {
        $this->actingAs($this->student);

        $response = $this->get("/lecciones/{$this->lesson1->slug}");

        $response->assertStatus(403);
    });

    test('enrolled student can view lesson content', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->student);

        $response = $this->get("/lecciones/{$this->lesson1->slug}");

        $response->assertStatus(200);
    });

    test('unenrolled student cannot mark lesson as complete', function () {
        $this->actingAs($this->student);

        $token = LessonController::generateViewToken($this->lesson1->id, $this->student->id);

        $response = $this->post("/lecciones/{$this->lesson1->id}/complete", [
            'view_token' => $token,
        ]);

        $response->assertStatus(403);
    });

    test('enrolled student can mark lesson as complete with valid token', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->student);

        $token = LessonController::generateViewToken($this->lesson1->id, $this->student->id);

        $response = $this->post("/lecciones/{$this->lesson1->id}/complete", [
            'view_token' => $token,
        ]);

        $response->assertStatus(302);
    });

    test('student with cancelled enrollment cannot view lessons', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'cancelled',
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->student);

        $response = $this->get("/lecciones/{$this->lesson1->slug}");

        $response->assertStatus(403);
    });
});

// ─── Sequence Validation ────────────────────────────────────────

describe('Sequence Validation', function () {

    test('student cannot skip to lesson 2 without completing lesson 1', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->student);

        $token = LessonController::generateViewToken($this->lesson2->id, $this->student->id);

        $response = $this->post("/lecciones/{$this->lesson2->id}/complete", [
            'view_token' => $token,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('course_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson2->id,
        ]);
    });

    test('student can complete lesson 2 after completing lesson 1', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'paid_amount' => 0,
        ]);

        // Complete lesson 1 first
        CourseProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'owner_id' => $this->owner->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $this->actingAs($this->student);

        $token = LessonController::generateViewToken($this->lesson2->id, $this->student->id);

        $response = $this->post("/lecciones/{$this->lesson2->id}/complete", [
            'view_token' => $token,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('course_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson2->id,
            'is_completed' => true,
        ]);
    });

    test('first lesson in course can always be completed (no prerequisite)', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->student);

        $token = LessonController::generateViewToken($this->lesson1->id, $this->student->id);

        $response = $this->post("/lecciones/{$this->lesson1->id}/complete", [
            'view_token' => $token,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('course_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'is_completed' => true,
        ]);
    });
});

// ─── View Token (Content Consumption) ───────────────────────────

describe('View Token Validation', function () {

    test('completion is rejected without view token', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->student);

        $response = $this->post("/lecciones/{$this->lesson1->id}/complete", []);

        $response->assertSessionHasErrors('view_token');
    });

    test('completion is rejected with invalid view token', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->student);

        $response = $this->post("/lecciones/{$this->lesson1->id}/complete", [
            'view_token' => 'fake-token-abc123',
        ]);

        $response->assertStatus(403);
    });

    test('view token is tied to specific lesson and user', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->student);

        // Generate token for lesson1 but try to use on lesson2
        $wrongToken = LessonController::generateViewToken($this->lesson1->id, $this->student->id);

        $response = $this->post("/lecciones/{$this->lesson2->id}/complete", [
            'view_token' => $wrongToken,
        ]);

        $response->assertStatus(403);
    });

    test('view token generated for different user is rejected', function () {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'paid_amount' => 0,
        ]);

        $this->actingAs($this->student);

        // Generate token for outsider user
        $wrongToken = LessonController::generateViewToken($this->lesson1->id, $this->outsider->id);

        $response = $this->post("/lecciones/{$this->lesson1->id}/complete", [
            'view_token' => $wrongToken,
        ]);

        $response->assertStatus(403);
    });
});

// ─── Course Enrollment Protection ───────────────────────────────

describe('Course Enrollment Protection', function () {

    test('student cannot enroll in unpublished course', function () {
        $draft = Course::create([
            'instructor_id' => $this->instructor->id,
            'owner_id' => $this->owner->id,
            'title' => 'Curso Borrador',
            'slug' => 'curso-borrador',
            'price' => 0,
            'is_published' => false,
        ]);

        $this->actingAs($this->student);

        $response = $this->post("/cursos/{$draft->id}/enroll");

        $response->assertStatus(403);
        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $this->student->id,
            'course_id' => $draft->id,
        ]);
    });

    test('student can enroll in published course', function () {
        $this->actingAs($this->student);

        $response = $this->post("/cursos/{$this->course->id}/enroll");

        $response->assertStatus(302);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);
    });
});

<?php

namespace App\Http\Controllers\LMS;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function store(Request $request, Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'nullable|url|max:255',
        ]);

        $order = $course->modules()->max('order') + 1;

        $course->modules()->create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'video_url' => $validated['video_url'] ?? null,
            'order' => $order,
        ]);

        return back()->with('success', 'Módulo añadido.');
    }

    public function update(Request $request, Module $module)
    {
        if ($module->course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'nullable|url|max:255',
            'order' => 'nullable|integer',
        ]);

        $module->update($validated);

        return back()->with('success', 'Módulo actualizado.');
    }

    public function destroy(Module $module)
    {
        if ($module->course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $module->delete();

        return back()->with('success', 'Módulo eliminado.');
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\WebSetting;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function __invoke()
    {
        $settings = WebSetting::getSettings();

        return Inertia::render('Backend/Planes/Index', [
            'planes' => $settings->planes ?? [],
        ]);
    }
}

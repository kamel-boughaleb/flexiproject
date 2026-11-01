<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $projects = $request->user()->projects()
            ->open()
            ->with('members:id,name,color,avatar')
            ->withCount([
                'tasks as tasks_total' => fn ($q) => $q->whereNull('parent_id'),
                'tasks as tasks_done' => fn ($q) => $q->whereNull('parent_id')->where('completed', true),
            ])
            ->orderBy('projects.created_at')
            ->get();

        return Inertia::render('Dashboard', [
            'projects' => $projects,
            'colors' => Project::COLORS,
        ]);
    }
}

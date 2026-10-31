<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyTasksController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tasks = $request->user()->assignedTasks()
            ->where('completed', false)
            ->whereHas('project.members', fn ($q) => $q->whereKey($request->user()->id))
            ->with(['project:id,name,color,icon,image_path', 'section:id,name'])
            ->orderByRaw('due_date is null, due_date')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('MyTasks', ['tasks' => $tasks]);
    }
}

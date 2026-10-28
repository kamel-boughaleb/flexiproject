<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Notifications\ProjectNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('view', $task->project);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $task->comments()->create($data + ['user_id' => $request->user()->id]);

        if ($task->assignee && $task->assignee->isNot($request->user())) {
            $task->assignee->notify(new ProjectNotification("{$request->user()->name} a commenté « {$task->title} »", $task->project_id, $task->id));
        }

        return back();
    }
}

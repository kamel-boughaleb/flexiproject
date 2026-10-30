<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TaskNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(Request $request, Task $task, TaskNotifier $notifier): RedirectResponse
    {
        Gate::authorize('view', $task->project);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment = $task->comments()->create($data + ['user_id' => $request->user()->id]);
        $notifier->commented($task, $comment, $request->user());

        return back();
    }
}

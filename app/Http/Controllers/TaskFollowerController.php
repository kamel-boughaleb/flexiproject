<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskFollower;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Collaborateurs liés à une tâche, ajoutés ou retirés à la main depuis la fiche (bouton « + », croix). */
class TaskFollowerController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('edit', $task->project);
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('project_user', 'user_id')->where('project_id', $task->project_id)],
        ]);
        TaskFollower::link($task, [$data['user_id']]);

        return back();
    }

    public function destroy(Request $request, Task $task, User $user): RedirectResponse
    {
        Gate::authorize('edit', $task->project);
        TaskFollower::where('task_id', $task->id)->where('user_id', $user->id)->delete();

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskFollower;
use App\Services\TaskNotifier;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    public function store(Request $request, Task $task, TaskNotifier $notifier): RedirectResponse
    {
        Gate::authorize('view', $task->project);
        $data = $request->validate(['body' => ['required', 'string', 'max:30000']]);
        if (RichText::sanitize($data['body']) === null) {
            throw ValidationException::withMessages(['body' => 'Le commentaire est vide.']);
        }
        $comment = $task->comments()->create($data + ['user_id' => $request->user()->id]);

        // Les personnes mentionnées reçoivent l'avis de mention à la place de l'avis de commentaire.
        $actor = $request->user();
        $mentioned = $notifier->mentioned($task, $actor, RichText::mentionedIds($comment->body), $comment->body, inComment: true);
        $notifier->commented($task, $comment, $actor, except: $mentioned);

        // L'auteur et les personnes mentionnées sont liés à la tâche ; les autres voient une bulle « +1 ».
        TaskFollower::link($task, [$actor->id, ...RichText::mentionedIds($comment->body)]);
        TaskFollower::bump($task, $actor);

        return back();
    }
}

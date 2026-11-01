<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\RuleEngine;
use App\Services\TaskNotifier;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rules\Exists;

class TaskController extends Controller
{
    public function __construct(private RuleEngine $rules, private TaskNotifier $notifier) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('edit', $project);
        abort_if($project->isCompleted(), 403, 'Ce projet est terminé : on ne peut plus y ajouter de tâches.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:60000'],
            'priority' => ['nullable', ValidationRule::in(Task::PRIORITIES)],
            'section_id' => ['nullable', ValidationRule::exists('sections', 'id')->where('project_id', $project->id)],
            'parent_id' => ['nullable', ValidationRule::exists('tasks', 'id')->where('project_id', $project->id)],
            'assignee_id' => ['nullable', $this->memberRule($project)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (! empty($data['parent_id'])) {
            $data['section_id'] = Task::find($data['parent_id'])->section_id;
        }
        $data['section_id'] ??= $project->sections()->value('id');
        $data['priority'] ??= 'none';
        $data['position'] = (Task::where('section_id', $data['section_id'])
            ->where('parent_id', $data['parent_id'] ?? null)->max('position') ?? 0) + 1;

        $task = $project->tasks()->create($data);
        $project->log("a créé la tâche « {$task->title} »", $request->user(), $task);
        $this->notifier->assigned($task, $request->user());
        $this->rules->fire('task_created', $task, $request->user());

        return back();
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $project = $task->project;
        Gate::authorize('edit', $project);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:60000'],
            'assignee_id' => ['sometimes', 'nullable', $this->memberRule($project)],
            'section_id' => ['sometimes', ValidationRule::exists('sections', 'id')->where('project_id', $project->id)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', ValidationRule::in(Task::PRIORITIES)],
        ]);

        $task->fill($data);
        $changed = array_keys($task->getDirty());
        if (! $changed) {
            return back();
        }
        $before = $task->getOriginal();
        $task->save();

        $actor = $request->user();
        $changes = $this->notifier->describe($task, $before, $changed);
        $project->log(Str::limit("a modifié « {$task->title} » : ".implode(', ', $changes), 250), $actor, $task);

        // Les personnes nouvellement mentionnées dans la description reçoivent un avis de mention.
        $mentioned = [];
        if (in_array('description', $changed, true)) {
            $newIds = array_diff(RichText::mentionedIds($task->description), RichText::mentionedIds($before['description'] ?? null));
            $mentioned = $this->notifier->mentioned($task, $actor, array_values($newIds), (string) $task->description, inComment: false);
        }

        // Le nouveau responsable reçoit l'avis d'assignation, l'ancien et les autres participants l'avis de modification.
        $reassigned = in_array('assignee_id', $changed, true);
        $this->notifier->updated(
            $task, $changes, $actor,
            also: $reassigned ? [$before['assignee_id']] : [],
            except: [...($reassigned ? [$task->assignee_id] : []), ...$mentioned],
        );

        if ($reassigned) {
            $this->notifier->assigned($task->load('assignee'), $actor);
            $this->rules->fire('assignee_changed', $task, $actor);
        }
        if (in_array('due_date', $changed, true)) {
            $this->rules->fire('due_date_changed', $task, $actor);
        }
        if (in_array('section_id', $changed, true)) {
            $this->rules->fire('task_moved', $task, $actor, $task->section_id);
        }

        return back();
    }

    /** Glisser-déposer du Kanban : place la tâche dans une section, à l'index donné. */
    public function move(Request $request, Task $task): RedirectResponse
    {
        $project = $task->project;
        Gate::authorize('edit', $project);
        $data = $request->validate([
            'section_id' => ['required', ValidationRule::exists('sections', 'id')->where('project_id', $project->id)],
            'index' => ['required', 'integer', 'min:0'],
        ]);
        $fromSection = $task->section_id;
        $toSection = (int) $data['section_id'];

        DB::transaction(function () use ($task, $toSection, $data) {
            $ids = Task::where('section_id', $toSection)->whereNull('parent_id')
                ->whereKeyNot($task->id)->orderBy('position')->pluck('id')->all();
            array_splice($ids, min($data['index'], count($ids)), 0, [$task->id]);
            foreach ($ids as $i => $id) {
                Task::whereKey($id)->update(['position' => $i + 1, 'section_id' => $toSection]);
            }
            // Les sous-tâches suivent leur tâche parente.
            Task::where('parent_id', $task->id)->update(['section_id' => $toSection]);
        });

        if ($fromSection !== $toSection) {
            $task->refresh();
            $project->log("a déplacé « {$task->title} » vers « {$task->section->name} »", $request->user(), $task);
            $this->notifier->updated($task, $this->notifier->describe($task, ['section_id' => $fromSection], ['section_id']), $request->user());
            $this->rules->fire('task_moved', $task, $request->user(), $toSection);
        }

        return back();
    }

    public function toggle(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('edit', $task->project);
        $task->markCompleted(! $task->completed);
        $task->project->log(($task->completed ? 'a terminé' : 'a rouvert')." « {$task->title} »", $request->user(), $task);
        $this->notifier->updated($task, [$task->completed ? 'tâche marquée comme terminée' : 'tâche rouverte'], $request->user());
        if ($task->completed) {
            $this->rules->fire('task_completed', $task, $request->user());
        }

        return back();
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('edit', $task->project);
        $task->project->log("a supprimé la tâche « {$task->title} »", $request->user());
        $task->delete();

        return back();
    }

    private function memberRule(Project $project): Exists
    {
        return ValidationRule::exists('project_user', 'user_id')->where('project_id', $project->id);
    }
}

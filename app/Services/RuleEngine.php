<?php

namespace App\Services;

use App\Models\Rule;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectNotification;

/**
 * Exécute les règles « quand… alors… » d'un projet.
 * Les actions d'une règle ne redéclenchent pas d'autres règles, ce qui évite les boucles.
 */
class RuleEngine
{
    public function fire(string $trigger, Task $task, User $actor, ?int $sectionId = null): void
    {
        $rules = Rule::where('project_id', $task->project_id)
            ->where('enabled', true)
            ->where('trigger', $trigger)
            ->get();

        foreach ($rules as $rule) {
            if ($trigger === 'task_moved' && $rule->trigger_section_id && $rule->trigger_section_id !== $sectionId) {
                continue;
            }

            foreach ($rule->actions as $action) {
                $this->apply($task->refresh(), $action, $actor);
            }

            $task->project->log("Règle « {$rule->name} » appliquée à « {$task->title} »", null, $task);
        }
    }

    private function apply(Task $task, array $action, User $actor): void
    {
        switch ($action['type'] ?? null) {
            case 'move_to_section':
                $section = Section::where('project_id', $task->project_id)->find($action['section_id'] ?? null);
                if ($section) {
                    $task->moveToEndOf($section);
                }
                break;
            case 'set_completed':
                $task->markCompleted(true);
                break;
            case 'set_incomplete':
                $task->markCompleted(false);
                break;
            case 'assign_to':
                $task->update(['assignee_id' => $action['user_id'] ?? null]);
                break;
            case 'set_priority':
                $task->update(['priority' => $action['priority'] ?? 'none']);
                break;
            case 'set_due_in_days':
                $task->update(['due_date' => now()->addDays((int) ($action['days'] ?? 0))->toDateString()]);
                break;
            case 'notify_assignee':
                $this->notify($task->assignee, $task, $action['text'] ?? null);
                break;
            case 'notify_user':
                $this->notify(User::find($action['user_id'] ?? null), $task, $action['text'] ?? null);
                break;
            case 'add_comment':
                if (! empty($action['text'])) {
                    $task->comments()->create(['user_id' => $actor->id, 'body' => '🤖 '.$action['text']]);
                }
                break;
        }
    }

    private function notify(?User $user, Task $task, ?string $text): void
    {
        $user?->notify(new ProjectNotification(
            $text ? "{$text} : « {$task->title} »" : "Mise à jour sur « {$task->title} »",
            $task->project_id,
            $task->id,
        ));
    }
}

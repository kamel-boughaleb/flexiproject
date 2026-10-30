<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Notifications\TaskMentioned;
use App\Notifications\TaskUpdated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Prévient les collaborateurs de ce qui se passe sur une tâche (cloche + e-mail selon leurs réglages).
 * Les participants d'une tâche sont son responsable et toutes les personnes qui l'ont créée, modifiée ou commentée.
 */
class TaskNotifier
{
    private const FIELDS = [
        'title' => 'Titre',
        'description' => 'Description',
        'assignee_id' => 'Responsable',
        'section_id' => 'Section',
        'start_date' => 'Début',
        'due_date' => 'Échéance',
        'priority' => 'Priorité',
    ];

    private const PRIORITIES = ['none' => 'aucune', 'low' => 'basse', 'medium' => 'moyenne', 'high' => 'haute'];

    public function assigned(Task $task, User $actor): void
    {
        if ($task->assignee && $task->assignee->isNot($actor)) {
            $task->assignee->notify(new TaskAssigned($task, $actor));
        }
    }

    /** @param  list<int>  $except  personnes déjà prévenues autrement (ex. mentionnées dans le commentaire) */
    public function commented(Task $task, Comment $comment, User $actor, array $except = []): void
    {
        $recipients = $this->participants($task, $actor)->whereNotIn('id', $except);
        Notification::send($recipients, new TaskCommented($task, $actor, $comment));
    }

    /**
     * Prévient les membres du projet mentionnés avec « @ ».
     *
     * @param  list<int>  $ids
     * @return list<int> les personnes prévenues
     */
    public function mentioned(Task $task, User $actor, array $ids, string $html, bool $inComment): array
    {
        $ids = array_diff($ids, [$actor->id]);
        if (! $ids) {
            return [];
        }
        $users = $task->project->members()->whereIn('users.id', $ids)->get();
        Notification::send($users, new TaskMentioned($task, $actor, $html, $inComment));

        return $users->modelKeys();
    }

    /**
     * @param  list<string>  $changes
     * @param  list<int|null>  $also  personnes à prévenir en plus des participants (ex. l'ancien responsable)
     * @param  list<int|null>  $except  personnes déjà prévenues autrement (ex. le nouveau responsable)
     */
    public function updated(Task $task, array $changes, User $actor, array $also = [], array $except = []): void
    {
        if (! $changes) {
            return;
        }
        $recipients = $this->participants($task, $actor, $also)->whereNotIn('id', array_filter($except));
        Notification::send($recipients, new TaskUpdated($task, $actor, $changes));
    }

    /**
     * Décrit les champs modifiés d'une tâche, à partir de leurs valeurs avant enregistrement.
     *
     * @param  array<string, mixed>  $before
     * @param  list<string>  $changed
     * @return list<string>
     */
    public function describe(Task $task, array $before, array $changed): array
    {
        $lines = [];
        foreach (array_intersect(array_keys(self::FIELDS), $changed) as $field) {
            $label = self::FIELDS[$field];
            $lines[] = $field === 'description'
                ? 'description mise à jour'
                : "{$label} : {$this->format($field, $before[$field] ?? null)} → {$this->format($field, $task->getAttribute($field))}";
        }

        return $lines;
    }

    /** Participants de la tâche encore membres du projet, sans l'auteur de l'action. */
    public function participants(Task $task, User $actor, array $also = []): Collection
    {
        $ids = collect([$task->assignee_id, ...$also])
            ->merge($task->activities()->whereNotNull('user_id')->pluck('user_id'))
            ->merge($task->comments()->pluck('user_id'))
            ->filter()->unique()->reject(fn ($id) => $id === $actor->id);

        return $task->project->members()->whereIn('users.id', $ids)->get();
    }

    private function format(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'aucun'.(in_array($field, ['start_date', 'due_date'], true) ? 'e' : '');
        }

        return match ($field) {
            'title' => "« {$value} »",
            'assignee_id' => User::find($value)?->name ?? 'aucun',
            'section_id' => Section::find($value)?->name ?? '?',
            'priority' => self::PRIORITIES[$value] ?? $value,
            'start_date', 'due_date' => Carbon::parse($value)->format('d/m/Y'),
            default => (string) $value,
        };
    }
}

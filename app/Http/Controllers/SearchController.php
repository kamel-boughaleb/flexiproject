<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Recherche globale (barre en haut de l'écran) : tâches et projets dont le nom contient
 * tous les mots saisis, limités aux projets dont l'utilisateur est collaborateur.
 */
class SearchController extends Controller
{
    private const TASK_LIMIT = 8;

    private const PROJECT_LIMIT = 5;

    private const PARTICIPANT_LIMIT = 4;

    public function __invoke(Request $request): JsonResponse
    {
        $terms = $this->terms((string) $request->query('q', ''));
        if ($terms === []) {
            return response()->json(['projects' => [], 'tasks' => []]);
        }

        $user = $request->user();
        $projectIds = $user->projects()->pluck('projects.id');

        return response()->json([
            'projects' => $this->projects($projectIds, $terms),
            'tasks' => $this->tasks($projectIds, $terms),
        ]);
    }

    /** @return list<string> mots de la recherche (au plus 5, pour garder une requête légère) */
    private function terms(string $query): array
    {
        $words = preg_split('/\s+/u', trim(mb_substr($query, 0, 100)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique($words)), 0, 5);
    }

    /** Chaque mot doit apparaître dans la colonne (les jokers % et _ saisis sont pris au pied de la lettre). */
    private function matching(Builder $query, string $column, array $terms): Builder
    {
        foreach ($terms as $term) {
            // « ! » comme caractère d'échappement : il se comporte pareil sous MySQL et SQLite (contrairement à « \ »).
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
            $query->whereRaw("{$column} like ? escape '!'", ["%{$escaped}%"]);
        }

        return $query;
    }

    private function projects(Collection $projectIds, array $terms): Collection
    {
        return $this->matching(Project::query()->whereIn('id', $projectIds), 'name', $terms)
            ->with('members:id,name,color,avatar')
            ->withCount([
                'tasks as tasks_total' => fn ($q) => $q->whereNull('parent_id'),
                'tasks as tasks_done' => fn ($q) => $q->whereNull('parent_id')->where('completed', true),
            ])
            ->orderBy('archived')
            ->orderByDesc('updated_at')
            ->limit(self::PROJECT_LIMIT)
            ->get()
            ->map(fn (Project $p) => [
                ...$p->summary(),
                'archived' => $p->archived,
                'tasks_total' => $p->tasks_total,
                'tasks_done' => $p->tasks_done,
                'members' => $p->members->take(self::PARTICIPANT_LIMIT)->map->only(['id', 'name', 'color', 'avatar'])->values(),
                'members_count' => $p->members->count(),
                'url' => route('projects.show', $p, false),
            ]);
    }

    private function tasks(Collection $projectIds, array $terms): Collection
    {
        $tasks = $this->matching(Task::query()->whereIn('project_id', $projectIds), 'title', $terms)
            ->with(['project:id,name,color', 'section:id,name', 'parent:id,title'])
            ->orderBy('completed')
            ->orderByDesc('updated_at')
            ->limit(self::TASK_LIMIT)
            ->get();

        $participants = $this->participants($tasks);

        return $tasks->map(fn (Task $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'completed' => $t->completed,
            'completed_at' => $t->completed_at?->toIso8601String(),
            'due_date' => $t->due_date?->format('Y-m-d'),
            'section' => $t->section?->name,
            'parent' => $t->parent?->title,
            'project' => $t->project->only(['id', 'name', 'color']),
            'participants' => $participants[$t->id]->take(self::PARTICIPANT_LIMIT)->values(),
            'participants_count' => $participants[$t->id]->count(),
            'url' => route('projects.view', [$t->project_id, 'board', 'task' => $t->id], false),
        ]);
    }

    /**
     * Participants de chaque tâche, comme pour les notifications : le responsable d'abord,
     * puis les personnes qui l'ont créée, modifiée ou commentée.
     *
     * @return array<int, Collection>
     */
    private function participants(Collection $tasks): array
    {
        $ids = $tasks->pluck('id');
        $byTask = $tasks->mapWithKeys(fn (Task $t) => [$t->id => collect([$t->assignee_id])])->all();

        $others = Comment::whereIn('task_id', $ids)->orderBy('id')->get(['task_id', 'user_id'])
            ->concat(Activity::whereIn('task_id', $ids)->whereNotNull('user_id')->orderBy('id')->get(['task_id', 'user_id']));
        foreach ($others as $row) {
            $byTask[$row->task_id]->push($row->user_id);
        }

        $users = User::whereIn('id', collect($byTask)->flatten()->filter()->unique())
            ->get(['id', 'name', 'color', 'avatar'])->keyBy('id');

        return collect($byTask)->map(fn (Collection $userIds) => $userIds->filter()->unique()
            ->map(fn ($id) => $users->get($id)?->only(['id', 'name', 'color', 'avatar']))
            ->filter()->values()
        )->all();
    }
}

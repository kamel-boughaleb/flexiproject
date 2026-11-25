<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\FeedPost;
use App\Models\Task;
use App\Models\User;
use App\Support\BusinessDays;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accueil personnel. Tous les délais sont en jours ouvrés (lundi → vendredi, fuseau de Paris).
 */
class DashboardController extends Controller
{
    private const LIMIT = 5;

    /** Actualités : 5 derniers jours ouvrés. */
    private const RECENT_DAYS = 5;

    /** Mentions : moins de 48 h, soit 2 jours ouvrés. */
    private const MENTION_DAYS = 2;

    /** Une tâche est urgente si son échéance tombe aujourd'hui ou dans les 2 prochains jours ouvrés. */
    public const URGENT_DAYS = 2;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $now = BusinessDays::now();
        $alertTasks = $this->alertTasks($user, $now);

        return Inertia::render('Dashboard', [
            'posts' => $this->latestPosts($user, $now),
            'alertTasks' => $alertTasks->map(fn (Task $task) => $this->taskRow($task, $now)),
            'mentions' => $this->mentions($user, $now),
            'morningAlert' => $this->morningAlert($user, $alertTasks, $now),
        ]);
    }

    /** Instant `$days` jours ouvrés avant maintenant, dans le fuseau de la base. */
    private static function since(int $days, Carbon $now): Carbon
    {
        return BusinessDays::ago($days, $now)->setTimezone(config('app.timezone'));
    }

    /** Actualités publiées ces 5 derniers jours ouvrés, dans les fils suivis (tous les fils si aucun n'est suivi). */
    private function latestPosts(User $user, Carbon $now): Collection
    {
        $feedIds = $user->feeds()->pluck('feeds.id');

        return FeedPost::query()
            ->when($feedIds->isNotEmpty(), fn ($q) => $q->whereIn('feed_id', $feedIds))
            ->where('published_on', '>=', BusinessDays::ago(self::RECENT_DAYS, $now)->toDateString())
            ->where('published_on', '<=', $now->toDateString())
            ->with(['feed:id,name,color', 'user:id,name,color,avatar'])
            ->withCount('attachments')
            ->orderByDesc('published_on')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (FeedPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'published_on' => $post->published_on->format('Y-m-d'),
                'excerpt' => Str::limit(RichText::toText($post->body), 160),
                'attachments_count' => $post->attachments_count,
                'feed' => $post->feed->summary(),
                'user' => $post->user?->only(['id', 'name', 'color', 'avatar']),
            ]);
    }

    /** Tâches non terminées des projets en cours dont l'utilisateur est membre. */
    private function openTasks(User $user)
    {
        return Task::query()
            ->where('tasks.completed', false)
            ->whereIn('tasks.project_id', $user->projects()->open()->select('projects.id'))
            ->with('project:id,name,color,icon,image_path,start_date,completed_at');
    }

    /** Tâches affectées à l'utilisateur, en retard ou urgentes (échéance d'ici 2 jours ouvrés). */
    private function alertTasks(User $user, Carbon $now): Collection
    {
        return $this->openTasks($user)
            ->where('assignee_id', $user->id)
            ->whereNotNull('due_date')
            ->where('due_date', '<=', BusinessDays::dateIn(self::URGENT_DAYS, $now))
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /** Commentaires de collègues mentionnant l'utilisateur, datant de moins de 2 jours ouvrés (48 h). */
    private function mentions(User $user, Carbon $now): Collection
    {
        return Comment::query()
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>=', self::since(self::MENTION_DAYS, $now))
            ->where('body', 'like', '%data-id="'.$user->id.'"%')
            ->whereHas('task', fn ($q) => $q->whereIn('project_id', $user->projects()->select('projects.id')))
            ->with(['user:id,name,color,avatar', 'task:id,project_id,title', 'task.project:id,name,color,icon,image_path,start_date,completed_at'])
            ->latest()
            ->latest('id')
            ->limit(20)
            ->get()
            ->filter(fn (Comment $c) => in_array($user->id, RichText::mentionedIds($c->body), true))
            ->take(10)
            ->values()
            ->map(fn (Comment $c) => [
                'id' => $c->id,
                'excerpt' => Str::limit(RichText::toText($c->body), 200),
                'created_at' => $c->created_at->toIso8601String(),
                'user' => $c->user?->only(['id', 'name', 'color', 'avatar']),
                'task' => ['id' => $c->task->id, 'title' => $c->task->title, 'url' => self::taskUrl($c->task)],
                'project' => $c->task->project->summary(),
            ]);
    }

    /**
     * Alerte du matin : à la première ouverture de l'accueil de la journée, les tâches en retard ou urgentes
     * affectées à l'utilisateur depuis son dernier affichage de l'accueil (toutes, à sa toute première visite).
     */
    private function morningAlert(User $user, Collection $alertTasks, Carbon $now): ?array
    {
        $seen = $user->dashboard_seen_at;
        DB::table('users')->where('id', $user->id)->update(['dashboard_seen_at' => now()]);

        if ($seen && $seen->copy()->setTimezone(BusinessDays::TIMEZONE)->toDateString() >= $now->toDateString()) {
            return null;
        }

        $tasks = $alertTasks
            ->filter(fn (Task $task) => ! $seen || ($task->assigned_at && $task->assigned_at->gt($seen)))
            ->values();
        if ($tasks->isEmpty()) {
            return null;
        }
        $rows = $tasks->map(fn (Task $task) => $this->taskRow($task, $now));

        return [
            'overdue' => $rows->where('overdue', true)->count(),
            'urgent' => $rows->where('overdue', false)->count(),
            'tasks' => $rows->values(),
        ];
    }

    private function taskRow(Task $task, Carbon $now): array
    {
        $due = $task->due_date?->format('Y-m-d');

        return [
            'id' => $task->id,
            'title' => $task->title,
            'due_date' => $due,
            'overdue' => $due !== null && $due < $now->toDateString(),
            'urgent' => $due !== null && $due >= $now->toDateString() && $due <= BusinessDays::dateIn(self::URGENT_DAYS, $now),
            'url' => self::taskUrl($task),
            'project' => $task->project->summary(),
        ];
    }

    private static function taskUrl(Task $task): string
    {
        return route('projects.view', [$task->project_id, 'list'], false).'?task='.$task->id;
    }
}

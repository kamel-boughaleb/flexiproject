<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lien entre une tâche et un collaborateur (table task_user), avec le nombre de nouveautés
 * (commentaires et modifications des autres) qu'il n'a pas encore vues : la bulle « +N ».
 */
class TaskFollower extends Model
{
    protected $table = 'task_user';

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['task_id', 'user_id', 'unread'];

    /**
     * Lie des collaborateurs à la tâche (seulement les membres du projet ; ceux déjà liés restent tels quels).
     *
     * @param  iterable<int|null>  $userIds
     */
    public static function link(Task $task, iterable $userIds): void
    {
        $ids = collect($userIds)->filter()->map(fn ($id) => (int) $id)->unique();
        if ($ids->isEmpty()) {
            return;
        }
        $ids = $task->project->members()->whereIn('users.id', $ids)->pluck('users.id');
        $now = now();
        static::query()->insertOrIgnore($ids->map(fn ($id) => [
            'task_id' => $task->id, 'user_id' => $id, 'unread' => 0, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    /** Une nouveauté de plus pour tous les collaborateurs liés, sauf son auteur. */
    public static function bump(Task $task, ?User $actor): void
    {
        static::query()->where('task_id', $task->id)
            ->when($actor, fn (Builder $q) => $q->where('user_id', '!=', $actor->id))
            ->update(['unread' => DB::raw('unread + 1'), 'updated_at' => now()]);
    }

    /** L'utilisateur a ouvert la tâche : plus rien à signaler. */
    public static function seen(int $taskId, User $user): void
    {
        static::query()->where('task_id', $taskId)->where('user_id', $user->id)->where('unread', '>', 0)
            ->update(['unread' => 0, 'updated_at' => now()]);
    }

    /** Identifiants des collaborateurs liés à la tâche. */
    public static function idsOf(Task $task): Collection
    {
        return static::query()->where('task_id', $task->id)->pluck('user_id');
    }
}

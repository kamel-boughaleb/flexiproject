<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Notifications affichées en bulle (10 s) dès leur arrivée, grâce au rafraîchissement automatique :
 * mention, tâche affectée ou supprimée, ajout à un projet ou à un fil d'actualité. Seules les notifications
 * non lues des dernières minutes sont envoyées ; l'interface retient celles déjà montrées.
 */
class LiveNotifications
{
    public const KINDS = ['mention', 'assigned', 'project_member', 'feed_member', 'task_deleted'];

    private const WINDOW_MINUTES = 10;

    public static function for(User $user): Collection
    {
        $notifications = $user->unreadNotifications()
            ->where('created_at', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->limit(10)
            ->get(['id', 'data', 'created_at'])
            ->map(fn (DatabaseNotification $n) => [$n, self::kind($n->data)])
            ->filter(fn ($pair) => $pair[1] !== null)
            ->take(5);

        $actors = User::whereKey($notifications->pluck('0.data.actor_id')->filter()->unique())
            ->get(['id', 'name', 'color', 'avatar'])
            ->keyBy('id');

        return $notifications->map(fn ($pair) => [
            'id' => $pair[0]->id,
            'kind' => $pair[1],
            'message' => $pair[0]->data['message'] ?? '',
            'url' => self::url($pair[0]->data),
            'created_at' => $pair[0]->created_at->toIso8601String(),
            'actor' => $actors->get($pair[0]->data['actor_id'] ?? 0)?->only(['id', 'name', 'color', 'avatar']),
        ])->values();
    }

    private static function kind(array $data): ?string
    {
        $kind = $data['kind'] ?? null;
        // Ajout à un fil d'actualité : notification avec un lien vers le fil.
        if (! $kind && empty($data['task_id']) && str_starts_with($data['url'] ?? '', '/feeds/')) {
            $kind = 'feed_member';
        }

        return in_array($kind, self::KINDS, true) ? $kind : null;
    }

    private static function url(array $data): string
    {
        if (! empty($data['url'])) {
            return $data['url'];
        }
        if (! empty($data['project_id'])) {
            $url = route('projects.view', [$data['project_id'], 'list'], false);

            return empty($data['task_id']) ? $url : $url.'?task='.$data['task_id'];
        }

        return route('dashboard', absolute: false);
    }
}

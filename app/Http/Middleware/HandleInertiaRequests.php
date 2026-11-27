<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Models\TaskFollower;
use App\Support\LiveNotifications;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            // Langue de l'interface (traductions dans lang/{locale}.json) et langues proposées.
            'locale' => app()->getLocale(),
            'locales' => config('app.locales'),
            'auth' => [
                'user' => $user,
            ],
            // Barre latérale : projets de l'utilisateur dans son ordre (avec leur statut, filtrable) et notifications non lues.
            // Nouveautés pas encore vues sur les tâches du projet (bulle « +N ») et dernière modification
            // des tâches (léger clignotement) : le menu signale l'activité des collaborateurs.
            'sidebarProjects' => fn () => $user?->sidebarProjects()
                ->select(['projects.id', 'name', 'color', 'icon', 'image_path', 'start_date', 'completed_at'])
                ->addSelect(['unread' => TaskFollower::query()
                    ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
                    ->whereColumn('tasks.project_id', 'projects.id')
                    ->where('task_user.user_id', $user->id)
                    ->selectRaw('coalesce(sum(task_user.unread), 0)')])
                ->withMax('tasks', 'updated_at')
                ->get()
                ->map(fn ($p) => [...$p->summary(['tasks_max_updated_at']), 'unread' => (int) $p->unread, 'pinned' => $p->pivot->pinned_at !== null]),
            // Tous les fils d'actualité, dans l'ordre de l'utilisateur (épinglés puis glisser-déposer), avec son abonnement.
            'sidebarFeeds' => fn () => $user?->sidebarFeeds()
                ->withExists(['subscribers as subscribed' => fn ($q) => $q->whereKey($user->id)])
                ->get(['feeds.id', 'name', 'color', 'fsp.pinned_at'])
                ->map(fn ($feed) => [...$feed->summary(), 'subscribed' => (bool) $feed->subscribed, 'pinned' => $feed->pinned_at !== null]) ?? [],
            // Couleurs proposées à la création d'un projet ou d'un fil (boutons « + » du menu).
            'projectColors' => Project::COLORS,
            // Notifications à afficher en bulle dès leur arrivée (mention, affectation, ajout à un projet ou un fil).
            'liveNotifications' => fn () => $user ? LiveNotifications::for($user) : [],
            'unreadNotifications' => fn () => $user?->unreadNotifications()->count() ?? 0,
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
        ]);
    }
}

<?php

namespace App\Http\Middleware;

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
            'auth' => [
                'user' => $user,
            ],
            // Barre latérale : projets de l'utilisateur dans son ordre (avec leur statut, filtrable) et notifications non lues.
            'sidebarProjects' => fn () => $user?->sidebarProjects()->get(['projects.id', 'name', 'color', 'icon', 'image_path', 'start_date', 'completed_at'])
                ->map(fn ($p) => $p->summary()),
            'unreadNotifications' => fn () => $user?->unreadNotifications()->count() ?? 0,
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
        ]);
    }
}

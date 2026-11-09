<?php

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Models\Project;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const POST_LIMIT = 6;

    public function __invoke(Request $request): Response
    {
        $projects = $request->user()->projects()
            ->open()
            ->with('members:id,name,color,avatar')
            ->withCount([
                'tasks as tasks_total' => fn ($q) => $q->whereNull('parent_id'),
                'tasks as tasks_done' => fn ($q) => $q->whereNull('parent_id')->where('completed', true),
            ])
            // Projets les plus actifs d'abord : dernière ligne d'historique, sinon date de création.
            ->orderByRaw('coalesce((select max(activities.created_at) from activities where activities.project_id = projects.id), projects.created_at) desc')
            ->orderByDesc('projects.id')
            ->get();

        return Inertia::render('Dashboard', [
            'posts' => $this->latestPosts($request->user()),
            'projects' => $projects,
            'colors' => Project::COLORS,
        ]);
    }

    /** Dernières actualités des fils suivis (de tous les fils si l'utilisateur n'en suit aucun). */
    private function latestPosts(User $user): Collection
    {
        $feedIds = $user->feeds()->pluck('feeds.id');

        return FeedPost::query()
            ->when($feedIds->isNotEmpty(), fn ($q) => $q->whereIn('feed_id', $feedIds))
            ->with(['feed:id,name,color', 'user:id,name,color,avatar'])
            ->withCount('attachments')
            ->orderByDesc('published_on')
            ->orderByDesc('id')
            ->limit(self::POST_LIMIT)
            ->get()
            ->map(fn (FeedPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'published_on' => $post->published_on->format('Y-m-d'),
                'excerpt' => Str::limit(RichText::toText($post->body), 180),
                'attachments_count' => $post->attachments_count,
                'feed' => $post->feed->summary(),
                'user' => $post->user?->only(['id', 'name', 'color', 'avatar']),
            ]);
    }
}

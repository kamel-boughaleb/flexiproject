<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fils d'actualité : toute l'équipe voit tous les fils, en crée et s'y abonne.
 * Le créateur du fil (ou un administrateur de la plateforme) le modifie ou le supprime.
 */
class FeedController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Feeds/Index', [
            'feeds' => Feed::query()
                ->with(['owner:id,name,color,avatar', 'latestPost:feed_posts.id,feed_posts.feed_id,feed_posts.created_at'])
                ->withCount(['subscribers', 'posts'])
                ->withExists(['subscribers as subscribed' => fn ($q) => $q->whereKey($user->id)])
                ->orderBy('name')
                ->get()
                ->map(fn (Feed $feed) => [
                    ...$feed->only(['id', 'name', 'description', 'color', 'subscribers_count', 'posts_count', 'subscribed']),
                    'owner' => $feed->owner,
                    'last_post_at' => $feed->latestPost?->created_at,
                ]),
            'colors' => Project::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $feed = Feed::create($this->validated($request) + ['owner_id' => $request->user()->id]);
        $feed->subscribers()->attach($request->user());

        return to_route('feeds.show', $feed);
    }

    public function show(Request $request, Feed $feed): Response
    {
        $user = $request->user();
        $posts = $feed->posts()
            ->with(['user:id,name,color,avatar', 'attachments.user:id,name'])
            ->paginate(20)
            ->through(fn ($post) => [...$post->toArray(), 'can_manage' => $post->setRelation('feed', $feed)->canBeManagedBy($user)]);

        return Inertia::render('Feeds/Show', [
            'feed' => [
                ...$feed->only(['id', 'name', 'description', 'color']),
                'owner' => $feed->owner()->first(['id', 'name', 'color', 'avatar']),
                'subscribers' => $feed->subscribers()->orderBy('name')->get(['users.id', 'name', 'color', 'avatar']),
                'subscribed' => $feed->isSubscribed($user),
                'can_manage' => $feed->canBeManagedBy($user),
            ],
            'posts' => $posts,
            'colors' => Project::COLORS,
        ]);
    }

    public function update(Request $request, Feed $feed): RedirectResponse
    {
        abort_unless($feed->canBeManagedBy($request->user()), 403);
        $feed->update($this->validated($request));

        return back();
    }

    public function destroy(Request $request, Feed $feed): RedirectResponse
    {
        abort_unless($feed->canBeManagedBy($request->user()), 403);
        $feed->delete();

        return to_route('feeds.index');
    }

    public function subscribe(Request $request, Feed $feed): RedirectResponse
    {
        $feed->subscribers()->syncWithoutDetaching([$request->user()->id]);

        return back();
    }

    public function unsubscribe(Request $request, Feed $feed): RedirectResponse
    {
        $feed->subscribers()->detach($request->user());

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', Rule::in(Project::COLORS)],
        ]);
    }
}

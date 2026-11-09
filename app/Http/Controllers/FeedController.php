<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /** Liste courte des fils (choix du fil dans la fenêtre « Nouvelle actualité » d'un projet), ceux suivis en premier. */
    public function options(Request $request): JsonResponse
    {
        $subscribed = $request->user()->feeds()->pluck('feeds.id')->all();

        return response()->json(Feed::orderBy('name')->get(['id', 'name', 'color'])
            ->map(fn (Feed $feed) => [...$feed->summary(), 'subscribed' => in_array($feed->id, $subscribed, true)])
            ->sortByDesc('subscribed')->values());
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

    /** Nouvel ordre des fils suivis dans le menu de gauche (ids dans l'ordre affiché). */
    public function reorder(Request $request): RedirectResponse
    {
        $user = $request->user();
        $current = $user->sidebarFeeds()->pluck('feeds.id')->all();
        $ids = array_map('intval', $request->validate([
            'ids' => ['required', 'array', 'max:'.count($current)],
            'ids.*' => ['integer', 'distinct', Rule::in($current)],
        ])['ids']);

        // Les fils absents de la liste envoyée gardent leur place ; les autres prennent l'ordre reçu.
        $queue = $ids;
        $order = [];
        foreach ($current as $id) {
            $order[] = in_array($id, $ids, true) ? array_shift($queue) : $id;
        }

        DB::transaction(function () use ($user, $order) {
            foreach ($order as $i => $id) {
                DB::table('feed_user')->where('user_id', $user->id)->where('feed_id', $id)->update(['sidebar_position' => $i + 1]);
            }
        });

        return back();
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

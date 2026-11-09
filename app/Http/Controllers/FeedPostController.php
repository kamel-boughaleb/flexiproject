<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\FeedPost;
use App\Notifications\FeedPostPublished;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Actualités : tout collaborateur publie dans n'importe quel fil. Chaque abonné (sauf l'auteur)
 * reçoit une notification et un e-mail. L'auteur, le créateur du fil ou un administrateur
 * modifie ou supprime l'actualité.
 */
class FeedPostController extends Controller
{
    public function store(Request $request, Feed $feed): RedirectResponse
    {
        $data = $this->validated($request);
        AttachmentController::validateFiles($request, required: false);
        $author = $request->user();

        $post = DB::transaction(function () use ($feed, $data, $request, $author) {
            $post = $feed->posts()->create($data + ['user_id' => $author->id]);
            foreach ($request->file('files', []) as $file) {
                AttachmentController::attach($post->attachments(), $file, $request, $post->attachmentsDirectory());
            }

            return $post;
        });

        $recipients = $feed->subscribers()->active()->whereKeyNot($author->id)->get();
        Notification::send($recipients, new FeedPostPublished($post, $author));

        return to_route('feeds.show', $feed);
    }

    public function show(Request $request, Feed $feed, FeedPost $post): Response
    {
        abort_unless($post->feed_id === $feed->id, 404);
        $post->load(['user:id,name,color,avatar', 'attachments.user:id,name']);

        return Inertia::render('Feeds/Post', [
            'feed' => [...$feed->only(['id', 'name', 'color']), 'subscribed' => $feed->isSubscribed($request->user())],
            'post' => [...$post->toArray(), 'can_manage' => $post->canBeManagedBy($request->user())],
        ]);
    }

    public function update(Request $request, FeedPost $post): RedirectResponse
    {
        abort_unless($post->canBeManagedBy($request->user()), 403);
        $post->update($this->validated($request));

        return back();
    }

    public function destroy(Request $request, FeedPost $post): RedirectResponse
    {
        abort_unless($post->canBeManagedBy($request->user()), 403);
        $post->delete();

        return to_route('feeds.show', $post->feed_id);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:200000'],
            'published_on' => ['required', 'date'],
        ]);
        $data['body'] = RichText::sanitize($data['body'] ?? null);

        return $data;
    }
}

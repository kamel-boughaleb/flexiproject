<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Images insérées dans les descriptions, commentaires et actualités. Elles restent sur le disque privé
 * (storage/app/private/editor-images/{projet} ou feed-images/{fil}) et ne sont servies qu'aux personnes
 * qui voient le projet (membres) ou le fil (toute l'équipe).
 */
class EditorImageController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        // Tout membre peut commenter, donc insérer une image.
        Gate::authorize('view', $project);
        $name = $this->save($request, "editor-images/{$project->id}");

        return response()->json(['url' => route('editor-images.show', [$project, $name], false)]);
    }

    public function show(Project $project, string $name): BinaryFileResponse
    {
        Gate::authorize('view', $project);

        return $this->serve("editor-images/{$project->id}/{$name}");
    }

    /** Images des actualités : tout collaborateur peut publier dans un fil. */
    public function storeForFeed(Request $request, Feed $feed): JsonResponse
    {
        $name = $this->save($request, "feed-images/{$feed->id}");

        return response()->json(['url' => route('feed-images.show', [$feed, $name], false)]);
    }

    public function showForFeed(Feed $feed, string $name): BinaryFileResponse
    {
        return $this->serve("feed-images/{$feed->id}/{$name}");
    }

    private function save(Request $request, string $directory): string
    {
        $request->validate(
            ['image' => ['required', 'file', 'mimes:png,jpg,jpeg,gif,webp', 'max:10240']],
            ['image.max' => __('L’image doit faire moins de 10 Mo.'), 'image.mimes' => __('Formats acceptés : PNG, JPG, GIF, WebP.')],
        );

        $file = $request->file('image');
        $name = Str::random(32).'.'.($file->guessExtension() === 'jpeg' ? 'jpg' : $file->guessExtension());
        $file->storeAs($directory, $name, 'local');

        return $name;
    }

    private function serve(string $path): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}

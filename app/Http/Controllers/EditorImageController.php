<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Images insérées dans les descriptions et commentaires. Elles restent sur le disque privé
 * (storage/app/private/editor-images/{projet}) et ne sont servies qu'aux membres du projet.
 */
class EditorImageController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        // Tout membre peut commenter, donc insérer une image.
        Gate::authorize('view', $project);
        $request->validate(
            ['image' => ['required', 'file', 'mimes:png,jpg,jpeg,gif,webp', 'max:10240']],
            ['image.max' => 'L’image doit faire moins de 10 Mo.', 'image.mimes' => 'Formats acceptés : PNG, JPG, GIF, WebP.'],
        );

        $file = $request->file('image');
        $name = Str::random(32).'.'.($file->guessExtension() === 'jpeg' ? 'jpg' : $file->guessExtension());
        $file->storeAs("editor-images/{$project->id}", $name, 'local');

        return response()->json(['url' => route('editor-images.show', [$project, $name], false)]);
    }

    public function show(Project $project, string $name): BinaryFileResponse
    {
        Gate::authorize('view', $project);
        $path = "editor-images/{$project->id}/{$name}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}

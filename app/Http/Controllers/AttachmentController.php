<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièces jointes des tâches : les éditeurs et administrateurs en ajoutent,
 * tous les membres les consultent. Les fichiers restent sur le disque privé
 * et ne sont servis qu'après vérification des droits.
 */
class AttachmentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('edit', $task->project);
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:'.Attachment::MAX_SIZE_KB],
        ], [
            'files.*.max' => 'Chaque fichier doit faire moins de '.(Attachment::MAX_SIZE_KB / 1024).' Mo.',
        ]);

        $names = [];
        foreach ($request->file('files') as $file) {
            $task->attachments()->create([
                'user_id' => $request->user()->id,
                'name' => $file->getClientOriginalName(),
                'path' => $file->store("attachments/{$task->project_id}/{$task->id}", 'local'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
            $names[] = "« {$file->getClientOriginalName()} »";
        }
        $task->project->log('a joint '.implode(', ', $names)." à « {$task->title} »", $request->user(), $task);

        return back();
    }

    /** Affichage dans la visionneuse (lecteur vidéo, image, PDF, code). */
    public function show(Attachment $attachment): BinaryFileResponse
    {
        Gate::authorize('view', $attachment->task->project);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        // Le texte (y compris HTML ou SVG) est toujours servi brut pour ne jamais être interprété par le navigateur.
        $type = $attachment->kind === 'text' ? 'text/plain; charset=utf-8' : ($attachment->mime ?: 'application/octet-stream');
        $headers = ['Content-Type' => $type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600'];
        if ($attachment->kind !== 'pdf') {
            // La visionneuse PDF de Chrome refuse de s'afficher dans un document « sandbox ».
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        // BinaryFileResponse gère les requêtes « Range » : la vidéo peut être lue en avançant dans le temps.
        return response()->file($disk->path($attachment->path), $headers)
            ->setContentDisposition('inline', $attachment->name, $this->asciiName($attachment->name));
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->task->project);

        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $project = $attachment->task->project;
        // L'auteur (s'il peut encore modifier le projet) ou un administrateur.
        Gate::authorize($attachment->user_id === $request->user()->id ? 'edit' : 'manage', $project);
        $attachment->delete();
        $project->log("a retiré « {$attachment->name} » de « {$attachment->task->title} »", $request->user(), $attachment->task);

        return back();
    }

    private function asciiName(string $name): string
    {
        return preg_replace('/[^\x20-\x7e]|[\/\\\\%"]/', '_', $name);
    }
}

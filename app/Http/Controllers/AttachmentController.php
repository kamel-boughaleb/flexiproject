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

    /**
     * Vignette JPEG (600 px de large au plus) d'une image, pour la couverture
     * des cartes. Créée au premier affichage puis conservée ; si l'image ne
     * peut pas être réduite, l'original est renvoyé.
     */
    public function thumbnail(Attachment $attachment): BinaryFileResponse
    {
        Gate::authorize('view', $attachment->task->project);
        abort_unless($attachment->kind === 'image', 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        $thumb = Attachment::thumbnailPathFor($attachment->path);
        if (! $disk->exists($thumb)) {
            $this->makeThumbnail($disk->path($attachment->path), $thumb);
        }
        $file = $disk->exists($thumb) ? $disk->path($thumb) : $disk->path($attachment->path);
        $type = $disk->exists($thumb) ? 'image/jpeg' : ($attachment->mime ?: 'application/octet-stream');

        return response()->file($file, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function makeThumbnail(string $source, string $target): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }
        $size = @getimagesize($source);
        // Au-delà de 40 mégapixels, la réduction demanderait trop de mémoire.
        if (! $size || $size[0] * $size[1] > 40_000_000 || filesize($source) > 30 * 1024 * 1024) {
            return;
        }
        $image = @imagecreatefromstring((string) file_get_contents($source));
        if (! $image) {
            return;
        }
        $width = min(600, imagesx($image));
        $height = max(1, (int) round(imagesy($image) * $width / imagesx($image)));
        $thumb = imagecreatetruecolor($width, $height);
        // Fond blanc pour les images transparentes (PNG, GIF, WebP).
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        ob_start();
        imagejpeg($thumb, null, 82);
        Storage::disk('local')->put($target, (string) ob_get_clean());
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

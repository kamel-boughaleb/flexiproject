<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\FeedPost;
use App\Models\Task;
use App\Support\Msg;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièces jointes des tâches et des actualités. Tâches : les éditeurs et administrateurs en ajoutent,
 * tous les membres du projet les consultent. Actualités : toute l'équipe les consulte ; l'auteur,
 * le créateur du fil ou un administrateur en ajoute. Les fichiers restent sur le disque privé
 * et ne sont servis qu'après vérification des droits.
 */
class AttachmentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('edit', $task->project);
        self::validateFiles($request);

        $names = [];
        foreach ($request->file('files') as $file) {
            self::attach($task->attachments(), $file, $request, "attachments/{$task->project_id}/{$task->id}");
            $names[] = Msg::make('« :name »', ['name' => $file->getClientOriginalName()]);
        }
        $task->project->log(Msg::make('a joint :files à « :task »', ['files' => $names, 'task' => $task->title]), $request->user(), $task);

        return back();
    }

    public function storeForPost(Request $request, FeedPost $post): RedirectResponse
    {
        abort_unless($post->canBeManagedBy($request->user()), 403);
        self::validateFiles($request);
        foreach ($request->file('files') as $file) {
            self::attach($post->attachments(), $file, $request, $post->attachmentsDirectory());
        }

        return back();
    }

    /** Règles communes aux envois de fichiers (champ « files », 20 fichiers au plus). */
    public static function validateFiles(Request $request, bool $required = true): void
    {
        $request->validate([
            'files' => [$required ? 'required' : 'nullable', 'array', 'max:20'],
            'files.*' => ['file', 'max:'.Attachment::MAX_SIZE_KB],
        ], [
            'files.*.max' => __('Chaque fichier doit faire moins de :size Mo.', ['size' => Attachment::MAX_SIZE_KB / 1024]),
        ]);
    }

    public static function attach(HasMany $relation, UploadedFile $file, Request $request, string $directory): Attachment
    {
        return $relation->create([
            'user_id' => $request->user()->id,
            'name' => $file->getClientOriginalName(),
            'path' => $file->store($directory, 'local'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /** Une pièce jointe de tâche est visible des membres du projet, une pièce jointe d'actualité de toute l'équipe. */
    private function authorizeView(Attachment $attachment): void
    {
        if ($attachment->task_id) {
            Gate::authorize('view', $attachment->task->project);
        }
    }

    /** Affichage dans la visionneuse (lecteur vidéo, image, PDF, code). */
    public function show(Attachment $attachment): BinaryFileResponse
    {
        $this->authorizeView($attachment);
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
        $this->authorizeView($attachment);
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
        // GD peut être compilé sans JPEG : on renvoie alors l'original plutôt qu'une erreur.
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
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
        $this->authorizeView($attachment);

        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        if (! $attachment->task_id) {
            abort_unless($attachment->user_id === $request->user()->id || $attachment->post?->canBeManagedBy($request->user()), 403);
            $attachment->delete();

            return back();
        }

        $project = $attachment->task->project;
        // L'auteur (s'il peut encore modifier le projet) ou un administrateur.
        Gate::authorize($attachment->user_id === $request->user()->id ? 'edit' : 'manage', $project);
        $attachment->delete();
        $project->log(Msg::make('a retiré « :file » de « :task »', ['file' => $attachment->name, 'task' => $attachment->task->title]), $request->user(), $attachment->task);

        return back();
    }

    private function asciiName(string $name): string
    {
        return preg_replace('/[^\x20-\x7e]|[\/\\\\%"]/', '_', $name);
    }
}

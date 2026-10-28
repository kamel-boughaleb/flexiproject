<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    /** Taille maximale d'un fichier, en kilo-octets (100 Mo). */
    public const MAX_SIZE_KB = 102400;

    /** Extensions affichées comme du code / texte dans la visionneuse. */
    public const TEXT_EXTENSIONS = [
        'txt', 'md', 'csv', 'tsv', 'log', 'sql', 'js', 'mjs', 'cjs', 'ts', 'jsx', 'tsx', 'vue', 'json', 'php',
        'html', 'htm', 'css', 'scss', 'less', 'xml', 'svg', 'yml', 'yaml', 'ini', 'env', 'conf', 'sh', 'bash',
        'py', 'rb', 'java', 'c', 'h', 'cpp', 'cs', 'go', 'rs', 'swift', 'kt',
    ];

    protected $fillable = ['task_id', 'user_id', 'name', 'path', 'mime', 'size'];

    protected $appends = ['kind', 'extension'];

    protected static function booted(): void
    {
        static::deleted(fn (Attachment $attachment) => Storage::disk('local')->delete($attachment->path));
    }

    /**
     * Supprime les fichiers des tâches données : à appeler avant une suppression
     * en cascade par la base, qui ne déclenche pas l'événement « deleted ».
     */
    public static function deleteFilesOf(Builder|array $taskIds): void
    {
        $paths = static::whereIn('task_id', $taskIds)->pluck('path')->all();
        Storage::disk('local')->delete($paths);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /** Type de visionneuse : image | video | audio | pdf | text | other. */
    public function getKindAttribute(): string
    {
        $mime = (string) $this->mime;

        return match (true) {
            $this->extension === 'svg' => 'text',
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $mime === 'application/pdf' => 'pdf',
            in_array($this->extension, self::TEXT_EXTENSIONS, true), str_starts_with($mime, 'text/') => 'text',
            default => 'other',
        };
    }
}

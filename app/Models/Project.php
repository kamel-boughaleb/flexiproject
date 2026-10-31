<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    public const ROLES = ['admin', 'editor', 'viewer'];

    public const COLORS = ['#f97316', '#6366f1', '#10b981', '#ec4899', '#0ea5e9', '#eab308', '#8b5cf6', '#64748b'];

    public const DEFAULT_SECTIONS = ['À faire', 'En cours', 'Terminé'];

    protected $fillable = ['name', 'description', 'color', 'icon', 'start_date', 'end_date', 'archived', 'owner_id'];

    protected $hidden = ['image_path'];

    protected $appends = ['image'];

    protected static function booted(): void
    {
        static::deleted(function (Project $project) {
            Storage::disk('local')->deleteDirectory("attachments/{$project->id}");
            $project->deleteImageFile();
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'archived' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('id');
    }

    /** Image du projet (servie par /projects/{id}/avatar), ou null : l'emoji ou la couleur la remplace. */
    public function imageUrl(): ?string
    {
        return ($this->attributes['image_path'] ?? null) ? route('projects.avatar', [$this, 'v' => substr(md5($this->image_path), 0, 8)], false) : null;
    }

    /** Champs d'affichage du projet (nom, couleur, avatar) pour l'en-tête des pages du projet. */
    public function summary(array $extra = []): array
    {
        return $this->only(['id', 'name', 'color', 'icon', 'image', ...$extra]);
    }

    /** Adresse de l'image, ajoutée à chaque projet envoyé à l'interface (charger image_path avec les colonnes). */
    protected function image(): Attribute
    {
        return Attribute::get(fn () => $this->imageUrl());
    }

    /** Enregistre l'image du projet (recadrée en carré). */
    public function setImage(UploadedFile $file): void
    {
        $this->deleteImageFile();
        $path = 'project-avatars/'.$this->id.'-'.Str::random(8).'.jpg';
        Storage::disk('local')->put($path, User::squareJpeg($file));
        $this->forceFill(['image_path' => $path])->save();
    }

    public function removeImage(): void
    {
        $this->deleteImageFile();
        $this->forceFill(['image_path' => null])->save();
    }

    private function deleteImageFile(): void
    {
        if ($this->image_path) {
            Storage::disk('local')->delete($this->image_path);
        }
    }

    /**
     * Ajoute une ligne à l'historique. La même action répétée par la même personne sur la même tâche
     * dans les minutes qui suivent (ex. description enregistrée au fil de la saisie) ne crée pas de doublon :
     * la ligne existante est simplement remise à l'heure.
     */
    public function log(string $message, ?User $user = null, ?Task $task = null): void
    {
        if ($user && $task) {
            $last = $this->activities()->where('task_id', $task->id)->first();
            if ($last && $last->user_id === $user->id && $last->message === $message && $last->created_at->gt(now()->subMinutes(10))) {
                $last->forceFill(['created_at' => now()])->save();

                return;
            }
        }

        $this->activities()->create([
            'message' => $message,
            'user_id' => $user?->id,
            'task_id' => $task?->id,
        ]);
    }
}

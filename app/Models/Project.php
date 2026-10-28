<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    use HasFactory;

    public const ROLES = ['admin', 'editor', 'viewer'];

    public const COLORS = ['#f97316', '#6366f1', '#10b981', '#ec4899', '#0ea5e9', '#eab308', '#8b5cf6', '#64748b'];

    public const DEFAULT_SECTIONS = ['À faire', 'En cours', 'Terminé'];

    protected $fillable = ['name', 'description', 'color', 'start_date', 'end_date', 'archived', 'owner_id'];

    protected static function booted(): void
    {
        static::deleted(fn (Project $project) => Storage::disk('local')->deleteDirectory("attachments/{$project->id}"));
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

    public function log(string $message, ?User $user = null, ?Task $task = null): void
    {
        $this->activities()->create([
            'message' => $message,
            'user_id' => $user?->id,
            'task_id' => $task?->id,
        ]);
    }
}

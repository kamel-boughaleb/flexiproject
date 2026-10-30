<?php

namespace App\Models;

use App\Casts\RichTextCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    public const PRIORITIES = ['none', 'low', 'medium', 'high'];

    protected $fillable = [
        'project_id', 'section_id', 'parent_id', 'assignee_id', 'title', 'description',
        'start_date', 'due_date', 'priority', 'completed', 'completed_at', 'position',
    ];

    protected function casts(): array
    {
        return [
            'description' => RichTextCast::class,
            'start_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('id');
    }

    public function markCompleted(bool $completed = true): void
    {
        $this->forceFill(['completed' => $completed, 'completed_at' => $completed ? now() : null])->save();
    }

    /** Place la tâche en fin de section. */
    public function moveToEndOf(Section $section): void
    {
        $max = Task::where('section_id', $section->id)->whereNull('parent_id')->max('position') ?? 0;
        $this->forceFill(['section_id' => $section->id, 'position' => $max + 1])->save();
    }
}

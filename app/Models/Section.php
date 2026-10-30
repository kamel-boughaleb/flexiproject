<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected static function booted(): void
    {
        static::deleting(fn (Section $section) => Attachment::deleteFilesOf(Task::where('section_id', $section->id)->select('id')));
    }

    protected $fillable = ['project_id', 'name', 'position'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id')->orderBy('position');
    }
}

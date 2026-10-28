<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rule extends Model
{
    public const TRIGGERS = ['task_created', 'task_moved', 'task_completed', 'assignee_changed', 'due_date_changed'];

    public const ACTIONS = [
        'move_to_section', 'set_completed', 'set_incomplete', 'assign_to', 'set_priority',
        'set_due_in_days', 'notify_assignee', 'notify_user', 'add_comment',
    ];

    protected $fillable = ['project_id', 'name', 'enabled', 'trigger', 'trigger_section_id', 'actions'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'actions' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function triggerSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'trigger_section_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = ['project_id', 'task_id', 'user_id', 'message'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

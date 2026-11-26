<?php

namespace App\Models;

use App\Support\Msg;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Activity extends Model
{
    protected $fillable = ['project_id', 'task_id', 'user_id', 'message', 'params', 'fields'];

    /** Modifications affichées sous les commentaires d'une tâche (les autres restent dans l'historique). */
    public const NOTABLE_FIELDS = ['start_date', 'due_date', 'priority', 'assignee_id', 'attachments'];

    protected $hidden = ['params'];

    protected function casts(): array
    {
        return ['params' => 'array', 'fields' => 'array'];
    }

    /**
     * Texte affiché dans la langue du lecteur. Les entrées enregistrées avant la traduction de l'interface
     * (sans valeurs à part) restent telles quelles.
     */
    protected function message(): Attribute
    {
        return Attribute::get(fn (string $value) => $this->params === null
            ? $value
            : Str::limit(Msg::render(Msg::make($value, $this->params)), 250));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

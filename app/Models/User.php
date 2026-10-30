<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'color',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    public const COLORS = ['#f97316', '#6366f1', '#10b981', '#ec4899', '#0ea5e9', '#eab308', '#8b5cf6', '#14b8a6'];

    /** E-mails envoyés par défaut ; chaque collaborateur peut les régler dans Réglages › Notifications. */
    public const NOTIFICATION_DEFAULTS = [
        'assigned' => true,      // une tâche m'est assignée
        'comments' => true,      // commentaire sur une tâche à laquelle je participe
        'updates' => true,       // modification d'une tâche à laquelle je participe
        'daily_digest' => true,  // récapitulatif de mes tâches chaque matin à 8 h
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->color ??= self::COLORS[crc32($user->email) % count(self::COLORS)];
        });
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot('role')->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /** Rôle de l'utilisateur dans le projet, ou null s'il n'en est pas membre. */
    public function roleIn(Project $project): ?string
    {
        return $project->members()->whereKey($this->id)->first()?->pivot->role;
    }

    /** @return array<string, bool> */
    public function notificationPreferences(): array
    {
        $saved = array_intersect_key($this->notification_preferences ?? [], self::NOTIFICATION_DEFAULTS);

        return array_map('boolval', $saved + self::NOTIFICATION_DEFAULTS);
    }

    public function wantsEmail(string $type): bool
    {
        return $this->notificationPreferences()[$type] ?? false;
    }
}

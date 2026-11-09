<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * Fil d'actualité : visible par toute l'équipe. Tout collaborateur peut y publier ;
 * les abonnés reçoivent un e-mail à chaque nouvelle actualité.
 */
class Feed extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'color', 'owner_id'];

    protected static function booted(): void
    {
        // Les actualités sont supprimées en cascade par la base : leurs fichiers sont retirés ici.
        static::deleted(function (Feed $feed) {
            $disk = Storage::disk('local');
            $disk->deleteDirectory("attachments/feeds/{$feed->id}");
            $disk->deleteDirectory("thumbnails/attachments/feeds/{$feed->id}");
            $disk->deleteDirectory("feed-images/{$feed->id}");
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(FeedPost::class)->orderByDesc('published_on')->orderByDesc('id');
    }

    public function latestPost(): HasOne
    {
        return $this->hasOne(FeedPost::class)->latestOfMany();
    }

    /** Le créateur du fil ou un administrateur de la plateforme le modifie ou le supprime. */
    public function canBeManagedBy(User $user): bool
    {
        return $user->is_admin || $this->owner_id === $user->id;
    }

    public function isSubscribed(User $user): bool
    {
        return $this->subscribers()->whereKey($user->id)->exists();
    }

    /** Champs d'affichage (menu de gauche, avatar). */
    public function summary(): array
    {
        return $this->only(['id', 'name', 'color']);
    }
}

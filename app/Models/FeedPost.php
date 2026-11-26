<?php

namespace App\Models;

use App\Casts\RichTextCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Actualité d'un fil : titre, date, texte riche (images, liens…) et pièces jointes. */
class FeedPost extends Model
{
    use HasFactory;

    protected $fillable = ['feed_id', 'user_id', 'title', 'body', 'published_on'];

    protected static function booted(): void
    {
        // Les pièces jointes sont supprimées en cascade par la base : leurs fichiers sont retirés ici.
        static::deleted(function (FeedPost $post) {
            $disk = Storage::disk('local');
            $disk->deleteDirectory($post->attachmentsDirectory());
            $disk->deleteDirectory('thumbnails/'.$post->attachmentsDirectory());
        });
    }

    protected function casts(): array
    {
        return [
            'body' => RichTextCast::class,
            'published_on' => 'date:Y-m-d',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Utilisateurs qui ont déjà vu l'actualité (page de l'actualité ou du fil). */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'feed_post_reads')->withPivot('seen_at');
    }

    /**
     * Note que l'utilisateur a vu ces actualités : elles ne figurent plus dans son récapitulatif du matin.
     *
     * @param  iterable<int>  $postIds
     */
    public static function markSeen(User $user, iterable $postIds): void
    {
        $rows = collect($postIds)->unique()->map(fn ($id) => ['user_id' => $user->id, 'feed_post_id' => $id, 'seen_at' => now()]);
        if ($rows->isNotEmpty()) {
            DB::table('feed_post_reads')->insertOrIgnore($rows->values()->all());
        }
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->oldest('id');
    }

    /** Dossier des pièces jointes sur le disque privé. */
    public function attachmentsDirectory(): string
    {
        return "attachments/feeds/{$this->feed_id}/{$this->id}";
    }

    /** L'auteur, le créateur du fil ou un administrateur modifie ou supprime l'actualité. */
    public function canBeManagedBy(User $user): bool
    {
        return $this->user_id === $user->id || $this->feed->canBeManagedBy($user);
    }

    public function url(): string
    {
        return route('posts.show', [$this->feed_id, $this->id]);
    }
}

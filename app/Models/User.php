<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\InvitationNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'photo_path',
        'invitation_token',
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
            'invited_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public const INVITATION_DAYS = 14;

    public const COLORS = ['#f97316', '#6366f1', '#10b981', '#ec4899', '#0ea5e9', '#eab308', '#8b5cf6', '#14b8a6'];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->color ??= self::COLORS[crc32($user->email) % count(self::COLORS)];
        });
        static::deleting(fn (User $user) => $user->deletePhotoFile());
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot('role')->withTimestamps();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /** Invitation envoyée mais pas encore acceptée (ni mot de passe ni compte Google). */
    public function isPending(): bool
    {
        return $this->invitation_token !== null && $this->password === null && $this->google_id === null;
    }

    public function invitationExpired(): bool
    {
        return $this->invited_at === null || $this->invited_at->addDays(self::INVITATION_DAYS)->isPast();
    }

    /** Envoie (ou renvoie) l'e-mail d'invitation avec un nouveau lien. */
    public function invite(User $by, ?Project $project = null): void
    {
        $token = Str::random(48);
        $this->forceFill([
            'invitation_token' => hash('sha256', $token),
            'invited_at' => now(),
            'invited_by' => $by->id,
        ])->save();

        $this->notify(new InvitationNotification($token, $by, $project));
    }

    public static function findByInvitationToken(string $token): ?self
    {
        return self::where('invitation_token', hash('sha256', $token))->first();
    }

    /** Enregistre la photo de profil (recadrée en carré) ; elle remplace l'avatar par défaut ou Google. */
    public function setPhoto(UploadedFile $file): void
    {
        $this->deletePhotoFile();
        $path = 'avatars/'.$this->id.'-'.Str::random(8).'.jpg';
        Storage::disk('local')->put($path, self::squareJpeg($file));

        $this->forceFill([
            'photo_path' => $path,
            'avatar' => route('users.photo', [$this, 'v' => Str::random(6)], false),
        ])->save();
    }

    public function removePhoto(): void
    {
        $this->deletePhotoFile();
        $this->forceFill(['photo_path' => null, 'avatar' => null])->save();
    }

    private function deletePhotoFile(): void
    {
        if ($this->photo_path) {
            Storage::disk('local')->delete($this->photo_path);
        }
    }

    /** Recadre l'image au centre et la réduit à 256 px pour garder des avatars légers. */
    private static function squareJpeg(UploadedFile $file): string
    {
        $source = function_exists('imagecreatefromstring') ? @imagecreatefromstring($file->get()) : false;
        if (! $source) {
            return $file->get();
        }

        $side = min(imagesx($source), imagesy($source));
        $square = imagecreatetruecolor(256, 256);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled(
            $square, $source, 0, 0,
            intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2),
            256, 256, $side, $side,
        );

        ob_start();
        imagejpeg($square, null, 85);

        return ob_get_clean();
    }

    /** Rôle de l'utilisateur dans le projet, ou null s'il n'en est pas membre. */
    public function roleIn(Project $project): ?string
    {
        return $project->members()->whereKey($this->id)->first()?->pivot->role;
    }
}

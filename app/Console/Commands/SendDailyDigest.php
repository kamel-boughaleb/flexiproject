<?php

namespace App\Console\Commands;

use App\Models\FeedPost;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lancée chaque minute (routes/console.php) : envoie le récapitulatif du matin aux collaborateurs dont
 * l'heure choisie (8 h par défaut, heure de Paris) est arrivée et qui ne l'ont pas encore reçu aujourd'hui.
 */
class SendDailyDigest extends Command
{
    public const TIMEZONE = 'Europe/Paris';

    /** Si le serveur était arrêté à l'heure prévue, le récapitulatif part encore dans les 2 heures qui suivent. */
    public const CATCH_UP_MINUTES = 120;

    /** Une actualité est « nouvelle » pendant 48 h. */
    public const NEW_POST_HOURS = 48;

    public const MAX_POSTS = 10;

    protected $signature = 'notifications:daily-digest';

    protected $description = 'Envoie le récapitulatif du matin (actualités et tâches) aux collaborateurs dont l\'heure d\'envoi est arrivée';

    public function handle(): void
    {
        $now = Carbon::now(self::TIMEZONE);
        $today = $now->toDateString();
        $sent = 0;

        $users = User::active()
            ->where(fn ($q) => $q->whereNull('digest_sent_on')->orWhere('digest_sent_on', '<', $today))
            ->get()
            ->filter(fn (User $user) => self::isDue($user, $now));

        foreach ($users as $user) {
            // Noté avant l'envoi : une erreur d'envoi ne relance pas l'e-mail chaque minute.
            $user->forceFill(['digest_sent_on' => $today])->saveQuietly();

            if (! $user->wantsEmail('daily_digest')) {
                continue;
            }
            $tasks = self::tasksFor($user);
            $posts = self::newPostsFor($user);
            if ($tasks->isEmpty() && $posts->isEmpty()) {
                continue;
            }

            $user->notify(new DailyDigest($tasks, $today, $posts));
            $sent++;
        }

        if ($sent) {
            $this->info("Récapitulatif envoyé à {$sent} collaborateur(s).");
        }
    }

    /** L'heure d'envoi choisie est passée, depuis moins de 2 heures. */
    public static function isDue(User $user, Carbon $now): bool
    {
        $at = $now->copy()->setTimeFromTimeString($user->digestTime());

        return $now->gte($at) && $now->lt($at->copy()->addMinutes(self::CATCH_UP_MINUTES));
    }

    /**
     * Tâches non terminées affectées au collaborateur, triées par échéance : en retard d'abord, puis en cours
     * de la plus proche à la plus lointaine, celles sans échéance à la fin. Seulement les projets en cours
     * dont la personne est encore membre.
     *
     * @return Collection<int, Task>
     */
    public static function tasksFor(User $user): Collection
    {
        return $user->assignedTasks()
            ->where('completed', false)
            ->whereHas('project', fn ($q) => $q->open())
            ->whereIn('project_id', $user->projects()->select('projects.id'))
            ->with('project:id,name')
            ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')
            ->get();
    }

    /**
     * Actualités des fils suivis, publiées depuis moins de 48 h, que le collaborateur n'a pas encore vues
     * (ni écrites), de la plus récente à la plus ancienne.
     *
     * @return Collection<int, FeedPost>
     */
    public static function newPostsFor(User $user): Collection
    {
        return FeedPost::query()
            ->whereIn('feed_id', $user->feeds()->select('feeds.id'))
            ->where('created_at', '>=', now()->subHours(self::NEW_POST_HOURS))
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
            ->whereDoesntHave('readers', fn ($q) => $q->whereKey($user->id))
            ->with(['feed:id,name,color', 'user:id,name'])
            ->latest()
            ->latest('id')
            ->limit(self::MAX_POSTS)
            ->get();
    }

    public static function today(): string
    {
        // Dates comparées au format AAAA-MM-JJ, dans le fuseau de Paris.
        return Carbon::now(self::TIMEZONE)->toDateString();
    }
}

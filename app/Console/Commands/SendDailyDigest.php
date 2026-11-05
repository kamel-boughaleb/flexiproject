<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Planifiée chaque matin à 8 h (heure de Paris) dans routes/console.php. */
class SendDailyDigest extends Command
{
    public const TIMEZONE = 'Europe/Paris';

    protected $signature = 'notifications:daily-digest';

    protected $description = 'Envoie à chaque collaborateur le récapitulatif de ses tâches en cours';

    public function handle(): void
    {
        $today = self::today();
        $sent = 0;

        $users = User::active()->whereHas('assignedTasks', fn ($q) => self::open($q))->get();
        foreach ($users as $user) {
            if (! $user->wantsEmail('daily_digest')) {
                continue;
            }
            $tasks = self::tasksFor($user);

            if ($tasks->isEmpty()) {
                continue;
            }

            $user->notify(new DailyDigest($tasks, $today));
            $sent++;
        }

        $this->info("Récapitulatif envoyé à {$sent} collaborateur(s).");
    }

    /**
     * Tâches en cours d'un collaborateur, triées par échéance : les tâches en retard arrivent donc
     * en premier, celles sans échéance à la fin. Seulement les projets dont la personne est encore membre.
     *
     * @return Collection<int, Task>
     */
    public static function tasksFor(User $user): Collection
    {
        return self::open($user->assignedTasks())
            ->with('project:id,name')
            ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')
            ->get()
            ->filter(fn (Task $task) => $user->projects()->whereKey($task->project_id)->exists())
            ->values();
    }

    public static function today(): string
    {
        // Dates comparées au format AAAA-MM-JJ, dans le fuseau de Paris.
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    private static function open($query)
    {
        return $query->where('completed', false)
            ->whereHas('project', fn ($q) => $q->open());
    }
}

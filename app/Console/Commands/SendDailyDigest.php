<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Planifiée chaque matin à 8 h (heure de Paris) dans routes/console.php. */
class SendDailyDigest extends Command
{
    public const TIMEZONE = 'Europe/Paris';

    protected $signature = 'notifications:daily-digest';

    protected $description = 'Envoie à chaque collaborateur le récapitulatif de ses tâches en cours';

    public function handle(): void
    {
        // Dates comparées au format AAAA-MM-JJ, dans le fuseau de Paris.
        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $sent = 0;

        $users = User::active()->whereHas('assignedTasks', fn ($q) => $this->open($q))->get();
        foreach ($users as $user) {
            if (! $user->wantsEmail('daily_digest')) {
                continue;
            }
            $tasks = $this->open($user->assignedTasks())
                ->with('project:id,name')
                // Par date d'échéance : les tâches en retard arrivent donc en premier, celles sans échéance à la fin.
                ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')
                ->get()
                // Seulement les projets dont la personne est encore membre.
                ->filter(fn (Task $task) => $user->projects()->whereKey($task->project_id)->exists());

            if ($tasks->isEmpty()) {
                continue;
            }

            $user->notify(new DailyDigest($tasks->values(), $today));
            $sent++;
        }

        $this->info("Récapitulatif envoyé à {$sent} collaborateur(s).");
    }

    private function open($query)
    {
        return $query->where('completed', false)
            ->whereHas('project', fn ($q) => $q->open());
    }
}

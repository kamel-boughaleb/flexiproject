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

    private const GROUPS = ['En retard', 'Aujourd’hui', 'Dans les 7 prochains jours', 'Plus tard', 'Sans échéance'];

    protected $signature = 'notifications:daily-digest';

    protected $description = 'Envoie à chaque collaborateur le récapitulatif de ses tâches en cours';

    public function handle(): void
    {
        // Dates comparées au format AAAA-MM-JJ, dans le fuseau de Paris.
        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $inAWeek = Carbon::now(self::TIMEZONE)->addDays(7)->toDateString();
        $sent = 0;

        $users = User::whereHas('assignedTasks', fn ($q) => $this->open($q))->get();
        foreach ($users as $user) {
            if (! $user->wantsEmail('daily_digest')) {
                continue;
            }
            $tasks = $this->open($user->assignedTasks())
                ->with('project:id,name')
                ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')
                ->get()
                // Seulement les projets dont la personne est encore membre.
                ->filter(fn (Task $task) => $user->projects()->whereKey($task->project_id)->exists());

            if ($tasks->isEmpty()) {
                continue;
            }

            $groups = $tasks->groupBy(function (Task $task) use ($today, $inAWeek) {
                $due = $task->due_date?->toDateString();

                return match (true) {
                    $due === null => 'Sans échéance',
                    $due < $today => 'En retard',
                    $due === $today => 'Aujourd’hui',
                    $due <= $inAWeek => 'Dans les 7 prochains jours',
                    default => 'Plus tard',
                };
            });
            $groups = collect(self::GROUPS)->mapWithKeys(fn ($title) => [$title => $groups->get($title)])->filter()->all();

            $user->notify(new DailyDigest($groups, $tasks->count()));
            $sent++;
        }

        $this->info("Récapitulatif envoyé à {$sent} collaborateur(s).");
    }

    private function open($query)
    {
        return $query->where('completed', false)
            ->whereHas('project', fn ($q) => $q->where('archived', false));
    }
}

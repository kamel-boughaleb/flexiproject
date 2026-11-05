<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Récapitulatif matinal des tâches en cours d'un collaborateur (e-mail uniquement, envoyé directement par la tâche planifiée). */
class DailyDigest extends Notification
{
    use Queueable;

    public int $total;

    /**
     * @param  Collection<int, Task>  $tasks  tâches en cours, triées par échéance (en retard d'abord, sans échéance à la fin)
     * @param  string  $today  date du jour (AAAA-MM-JJ, heure de Paris)
     */
    public function __construct(public Collection $tasks, public string $today)
    {
        $this->total = $tasks->count();
    }

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function overdueCount(): int
    {
        return $this->tasks->filter(fn (Task $task) => $task->due_date && $task->due_date->toDateString() < $this->today)->count();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Vos tâches du jour (:count)', ['count' => $this->total]))
            ->markdown('mail.daily-digest', [
                'name' => strtok($notifiable->name, ' '),
                'tasks' => $this->tasks,
                'today' => $this->today,
                'total' => $this->total,
                'overdue' => $this->overdueCount(),
                'dueToday' => $this->tasks->filter(fn (Task $task) => $task->due_date?->toDateString() === $this->today)->count(),
            ]);
    }
}

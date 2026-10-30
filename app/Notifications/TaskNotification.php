<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification liée à une tâche : toujours dans la cloche, et par e-mail si le collaborateur l'a activé.
 * La cloche est enregistrée tout de suite ; l'e-mail part en file d'attente pour ne pas ralentir la page.
 */
abstract class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    /** Clé de User::NOTIFICATION_DEFAULTS qui active l'e-mail. */
    protected string $preference;

    public function __construct(public Task $task, public User $actor)
    {
        $this->afterCommit();
    }

    abstract protected function message(): string;

    abstract protected function mail(MailMessage $mail): MailMessage;

    public function via(User $notifiable): array
    {
        return $notifiable->wantsEmail($this->preference) ? ['database', 'mail'] : ['database'];
    }

    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toArray(User $notifiable): array
    {
        return [
            'message' => $this->message(),
            'project_id' => $this->task->project_id,
            'task_id' => $this->task->id,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[{$this->task->project->name}] {$this->task->title}")
            ->greeting('Bonjour '.strtok($notifiable->name, ' ').',');

        return $this->mail($mail)
            ->action('Ouvrir la tâche', self::taskUrl($this->task))
            ->salutation('À bientôt sur '.config('app.name').' !')
            ->line('['.'Régler mes notifications'.']('.route('notifications.settings').')');
    }

    public static function taskUrl(Task $task): string
    {
        return route('projects.view', [$task->project_id, 'list']).'?task='.$task->id;
    }
}

<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class TaskUpdated extends TaskNotification
{
    protected string $preference = 'updates';

    /** @param list<string> $changes description lisible de chaque modification */
    public function __construct(Task $task, User $actor, public array $changes)
    {
        parent::__construct($task, $actor);
    }

    protected function message(): string
    {
        return Str::limit("{$this->actor->name} a modifié « {$this->task->title} » : ".implode(', ', $this->changes), 250);
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $mail->line("{$this->actor->name} a modifié la tâche **{$this->task->title}** :");
        foreach ($this->changes as $change) {
            $mail->line('• '.$change);
        }

        return $mail;
    }
}

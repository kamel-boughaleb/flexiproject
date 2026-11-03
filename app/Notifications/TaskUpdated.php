<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Support\Msg;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class TaskUpdated extends TaskNotification
{
    protected string $preference = 'updates';

    /** @param list<array> $changes chaque modification, en message Msg traduit pour chaque destinataire */
    public function __construct(Task $task, User $actor, public array $changes)
    {
        parent::__construct($task, $actor);
    }

    protected function message(): string
    {
        return Str::limit(__(':name a modifié « :task » : :changes', [
            'name' => $this->actor->name,
            'task' => $this->task->title,
            'changes' => Msg::render($this->changes),
        ]), 250);
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $mail->line(__(':name a modifié la tâche **:task** :', ['name' => $this->actor->name, 'task' => $this->task->title]));
        foreach ($this->changes as $change) {
            $mail->line('• '.Msg::render($change));
        }

        return $mail;
    }
}

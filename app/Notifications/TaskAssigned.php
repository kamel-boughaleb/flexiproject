<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TaskAssigned extends TaskNotification
{
    protected string $preference = 'assigned';

    protected function message(): string
    {
        return __(':name vous a assigné « :task »', ['name' => $this->actor->name, 'task' => $this->task->title]);
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $mail->line(__(':name vous a assigné la tâche **:task** dans le projet **:project**.', ['name' => $this->actor->name, 'task' => $this->task->title, 'project' => $this->task->project->name]));
        // L'échéance figure sur la fiche de la tâche affichée sous ce texte.
        return $mail;
    }
}

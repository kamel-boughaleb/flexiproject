<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class TaskAssigned extends TaskNotification
{
    protected string $preference = 'assigned';

    protected function message(): string
    {
        return "{$this->actor->name} vous a assigné « {$this->task->title} »";
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $mail->line("{$this->actor->name} vous a assigné la tâche **{$this->task->title}** dans le projet **{$this->task->project->name}**.");
        if ($this->task->due_date) {
            $mail->line('Échéance : '.$this->task->due_date->translatedFormat('l j F Y').'.');
        }

        return $mail;
    }
}

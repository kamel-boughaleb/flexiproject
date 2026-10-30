<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class TaskCommented extends TaskNotification
{
    protected string $preference = 'comments';

    public function __construct(Task $task, User $actor, public Comment $comment)
    {
        parent::__construct($task, $actor);
    }

    protected function message(): string
    {
        return "{$this->actor->name} a commenté « {$this->task->title} »";
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        return $mail
            ->line("{$this->actor->name} a commenté la tâche **{$this->task->title}** :")
            ->line('> '.$this->comment->body);
    }
}

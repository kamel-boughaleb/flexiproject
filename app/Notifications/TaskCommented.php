<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Support\RichText;
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
        return __(':name a commenté « :task »', ['name' => $this->actor->name, 'task' => $this->task->title]);
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        return $mail
            ->line(__(':name a commenté la tâche **:task** :', ['name' => $this->actor->name, 'task' => $this->task->title]))
            ->line('> '.str_replace("\n", "\n> ", RichText::toText($this->comment->body)));
    }
}

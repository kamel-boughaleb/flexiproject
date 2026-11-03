<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class TaskMentioned extends TaskNotification
{
    protected string $preference = 'mentions';

    public function __construct(Task $task, User $actor, public string $html, public bool $inComment)
    {
        parent::__construct($task, $actor);
    }

    protected function message(): string
    {
        $key = $this->inComment
            ? ':name vous a mentionné dans un commentaire sur « :task »'
            : ':name vous a mentionné dans la description de « :task »';

        return __($key, ['name' => $this->actor->name, 'task' => $this->task->title]);
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $key = $this->inComment
            ? ':name vous a mentionné dans un commentaire sur la tâche **:task** :'
            : ':name vous a mentionné dans la description de la tâche **:task** :';
        $text = Str::limit(RichText::toText($this->html), 1500);

        return $mail
            ->line(__($key, ['name' => $this->actor->name, 'task' => $this->task->title]))
            ->line('> '.str_replace("\n", "\n> ", $text));
    }
}

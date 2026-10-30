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
        $where = $this->inComment ? 'un commentaire sur' : 'la description de';

        return "{$this->actor->name} vous a mentionné dans {$where} « {$this->task->title} »";
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $where = $this->inComment ? 'un commentaire sur la tâche' : 'la description de la tâche';
        $text = Str::limit(RichText::toText($this->html), 1500);

        return $mail
            ->line("{$this->actor->name} vous a mentionné dans {$where} **{$this->task->title}** :")
            ->line('> '.str_replace("\n", "\n> ", $text));
    }
}

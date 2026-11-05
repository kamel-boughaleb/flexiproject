<?php

namespace App\Console\Commands\Mail;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Notifications\TaskMentioned;
use App\Notifications\TaskNotification;
use App\Notifications\TaskUpdated;
use App\Support\Msg;
use Illuminate\Console\Command;

/**
 * Envoie tout de suite une notification de tâche par e-mail, pour tester les e-mails en production.
 * Rien n'est enregistré : pas de notification dans la cloche, pas de commentaire ni de modification.
 */
class TestTaskNotification extends Command
{
    use SendsTestMail;

    public const TYPES = ['assigned', 'comment', 'update', 'mention'];

    protected $signature = 'mail:test-notification
        {email : adresse qui reçoit la notification (un collaborateur, ou n\'importe quelle adresse)}
        {--type=assigned : assigned (assignation), comment (commentaire), update (modification) ou mention}
        {--task= : id de la tâche à utiliser (par défaut, la dernière tâche en cours du collaborateur, sinon la dernière créée)}';

    protected $description = 'Envoie une notification de tâche par e-mail (test des e-mails)';

    public function handle(): int
    {
        $type = strtolower($this->option('type'));
        if (! in_array($type, self::TYPES, true)) {
            $this->error("Type « {$type} » inconnu. Types possibles : ".implode(', ', self::TYPES).'.');

            return self::FAILURE;
        }

        $email = strtolower(trim($this->argument('email')));
        // Adresse sans compte : un destinataire de passage, rien n'est créé en base.
        $recipient = $this->findUser($email) ?? new User(['name' => strstr($email, '@', true) ?: $email, 'email' => $email]);

        $task = $this->task($recipient);
        if (! $task) {
            $this->error($this->option('task') ? "Aucune tâche avec l'id {$this->option('task')}." : 'Aucune tâche sur la plateforme.');

            return self::FAILURE;
        }
        $actor = $task->project->owner ?? $recipient;

        $this->line("Notification « {$type} » de {$actor->name} sur la tâche #{$task->id} « {$task->title} » ({$task->project->name}).");

        return $this->sendNow($recipient, $this->notification($type, $task, $actor));
    }

    private function task(User $recipient): ?Task
    {
        if ($id = $this->option('task')) {
            return Task::with('project.owner')->find($id);
        }

        $query = Task::with('project.owner')->latest('id');

        return ($recipient->exists ? (clone $query)->where('assignee_id', $recipient->id)->where('completed', false)->first() : null)
            ?? $query->first();
    }

    private function notification(string $type, Task $task, User $actor): TaskNotification
    {
        return match ($type) {
            'assigned' => new TaskAssigned($task, $actor),
            'comment' => new TaskCommented($task, $actor, $task->comments()->latest('id')->first()
                ?? new Comment(['body' => '<p>Ceci est un commentaire de test : tout est en ordre de mon côté.</p>'])),
            'update' => new TaskUpdated($task, $actor, [
                Msg::make(':field : :from → :to', ['field' => Msg::make('Échéance'), 'from' => Msg::date(now()), 'to' => Msg::date(now()->addWeek())]),
                Msg::make(':field : :from → :to', ['field' => Msg::make('Priorité'), 'from' => Msg::make('aucune'), 'to' => Msg::make('haute')]),
            ]),
            'mention' => new TaskMentioned($task, $actor, '<p>Ceci est une mention de test : peux-tu jeter un œil à cette tâche ?</p>', true),
        };
    }
}

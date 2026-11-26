<?php

namespace App\Notifications;

use App\Models\FeedPost;
use App\Models\Task;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Récapitulatif du matin d'un collaborateur (e-mail uniquement, envoyé directement par la tâche planifiée) :
 * nouvelles actualités des fils suivis, puis tâches en retard (avec avertissement) et tâches en cours.
 */
class DailyDigest extends Notification
{
    use Queueable;

    /** Longueur maximale de l'extrait de chaque actualité. */
    public const EXCERPT_LENGTH = 180;

    public int $total;

    /** @var Collection<int, Task> */
    public Collection $overdue;

    /** @var Collection<int, Task> */
    public Collection $inProgress;

    /**
     * @param  Collection<int, Task>  $tasks  tâches non terminées, triées par échéance (sans échéance à la fin)
     * @param  string  $today  date du jour (AAAA-MM-JJ, heure de Paris)
     * @param  Collection<int, FeedPost>|null  $posts  nouvelles actualités non vues des fils suivis
     */
    public function __construct(public Collection $tasks, public string $today, public ?Collection $posts = null)
    {
        $this->posts ??= collect();
        $this->total = $tasks->count();
        [$this->overdue, $this->inProgress] = $tasks
            ->partition(fn (Task $task) => $task->due_date && $task->due_date->toDateString() < $today)
            ->map->values();
    }

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function overdueCount(): int
    {
        return $this->overdue->count();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $subject = $this->overdue->isNotEmpty()
            ? trans_choice('Votre récap du jour · ⚠️ :count tâche en retard|Votre récap du jour · ⚠️ :count tâches en retard', $this->overdue->count())
            : __('Votre récap du jour');

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.daily-digest', [
                'name' => strtok($notifiable->name, ' '),
                'today' => $this->today,
                'overdue' => $this->overdue,
                'inProgress' => $this->inProgress,
                'posts' => $this->posts->map(fn (FeedPost $post) => [
                    'post' => $post,
                    'excerpt' => Str::limit(RichText::toText($post->body), self::EXCERPT_LENGTH),
                ]),
                'time' => $notifiable->digestTimeLabel(),
            ]);
    }
}

<?php

namespace App\Notifications;

use App\Models\FeedPost;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Nouvelle actualité dans un fil : dans la cloche et par e-mail pour chaque abonné.
 * S'abonner au fil vaut accord pour l'e-mail ; on se désabonne depuis la page du fil.
 */
class FeedPostPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    /** Longueur maximale de l'extrait de texte affiché dans l'e-mail. */
    public const EXCERPT_LENGTH = 800;

    public function __construct(public FeedPost $post, public User $actor)
    {
        $this->afterCommit();
    }

    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toArray(User $notifiable): array
    {
        return [
            'message' => __(':name a publié « :post » dans :feed', ['name' => $this->actor->name, 'post' => $this->post->title, 'feed' => $this->post->feed->name]),
            'project_id' => null,
            'task_id' => null,
            'url' => route('posts.show', [$this->post->feed_id, $this->post->id], false),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $post = $this->post->loadMissing(['feed', 'user', 'attachments']);

        return (new MailMessage)
            ->subject("[{$post->feed->name}] {$post->title}")
            ->greeting(__('Bonjour :name,', ['name' => strtok($notifiable->name, ' ')]))
            ->line(__(':name a publié une actualité dans le fil **:feed** :', ['name' => $this->actor->name, 'feed' => $post->feed->name]))
            ->action(__('Lire l’actualité'), $post->url())
            ->salutation(__('À bientôt sur :app !', ['app' => config('app.name')]))
            ->markdown('notifications::email', [
                'post' => $post,
                'excerpt' => Str::limit(RichText::toText($post->body), self::EXCERPT_LENGTH),
                'settingsUrl' => route('feeds.show', $post->feed_id),
                'reason' => __('Vous recevez cet e-mail car vous êtes abonné au fil « :feed ».', ['feed' => $post->feed->name]),
                'settingsLabel' => __('Se désabonner'),
            ]);
    }
}

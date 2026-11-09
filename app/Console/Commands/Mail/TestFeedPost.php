<?php

namespace App\Console\Commands\Mail;

use App\Models\FeedPost;
use App\Models\User;
use App\Notifications\FeedPostPublished;
use Illuminate\Console\Command;

/** Envoie tout de suite l'e-mail « nouvelle actualité », pour tester les e-mails en production. Rien n'est enregistré. */
class TestFeedPost extends Command
{
    use SendsTestMail;

    protected $signature = 'mail:test-feed-post
        {email : adresse qui reçoit l\'e-mail (un collaborateur, ou n\'importe quelle adresse)}
        {--post= : id de l\'actualité à utiliser (par défaut, la dernière publiée)}';

    protected $description = 'Envoie l\'e-mail d\'une nouvelle actualité dans un fil (test des e-mails)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $recipient = $this->findUser($email) ?? new User(['name' => strstr($email, '@', true) ?: $email, 'email' => $email]);

        $post = $this->option('post') ? FeedPost::with('feed', 'user')->find($this->option('post')) : FeedPost::with('feed', 'user')->latest('id')->first();
        if (! $post) {
            $this->error($this->option('post') ? "Aucune actualité avec l'id {$this->option('post')}." : 'Aucune actualité sur la plateforme.');

            return self::FAILURE;
        }
        $actor = $post->user ?? $recipient;

        $this->line("Actualité #{$post->id} « {$post->title} » de {$actor->name} ({$post->feed->name}).");

        return $this->sendNow($recipient, new FeedPostPublished($post, $actor));
    }
}

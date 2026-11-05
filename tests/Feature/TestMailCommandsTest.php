<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/** Commandes mail:test-digest et mail:test-notification, avec le vrai rendu des e-mails (mailer « array »). */
class TestMailCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function memberWithTask(): array
    {
        $project = Project::factory()->create(['name' => 'Catalogue']);
        $marie = User::factory()->create(['email' => 'marie@flexilivre.com', 'name' => 'Marie Durand']);
        $project->members()->attach($marie, ['role' => 'editor']);
        $task = Task::factory()->in($project)->create(['title' => 'Couverture', 'assignee_id' => $marie->id, 'due_date' => now()->addDay()]);

        return [$marie, $task, $project];
    }

    /** @return list<Email> */
    private function sentEmails(): array
    {
        return array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(Mail::mailer('array')->getSymfonyTransport()->messages()));
    }

    private function lastEmail(): Email
    {
        $emails = $this->sentEmails();
        $this->assertNotEmpty($emails, 'Aucun e-mail envoyé.');

        return end($emails);
    }

    public function test_digest_is_sent_even_when_the_user_turned_it_off(): void
    {
        [$marie] = $this->memberWithTask();
        $marie->update(['notification_preferences' => ['daily_digest' => false]]);

        $this->artisan('mail:test-digest', ['email' => 'MARIE@flexilivre.com'])
            ->expectsOutputToContain('envoyé quand même')
            ->expectsOutputToContain('E-mail envoyé à marie@flexilivre.com')
            ->assertSuccessful()
            ->run();

        $email = $this->lastEmail();
        $this->assertSame('marie@flexilivre.com', $email->getTo()[0]->getAddress());
        $this->assertSame('Vos tâches du jour (1)', $email->getSubject());
        $this->assertStringContainsString('Couverture', $email->getHtmlBody());
    }

    public function test_digest_can_be_sent_to_another_address(): void
    {
        $this->memberWithTask();

        $this->artisan('mail:test-digest', ['email' => 'marie@flexilivre.com', '--to' => 'kamel@flexilivre.com'])
            ->expectsOutputToContain('ne part pas vraiment')
            ->assertSuccessful()
            ->run();

        $email = $this->lastEmail();
        $this->assertSame('kamel@flexilivre.com', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('Marie', $email->getHtmlBody());
        $this->assertStringContainsString('Couverture', $email->getHtmlBody());
        $this->assertFalse(User::where('email', 'kamel@flexilivre.com')->exists());
    }

    public function test_digest_fails_for_unknown_user_or_user_without_tasks(): void
    {
        User::factory()->create(['email' => 'idle@flexilivre.com']);

        $this->artisan('mail:test-digest', ['email' => 'nobody@flexilivre.com'])->assertFailed();
        $this->artisan('mail:test-digest', ['email' => 'idle@flexilivre.com'])->expectsOutputToContain('aucune tâche en cours')->assertFailed()->run();

        $this->assertEmpty($this->sentEmails());
    }

    public function test_notification_uses_the_users_latest_open_task_and_saves_nothing(): void
    {
        [$marie, $task, $project] = $this->memberWithTask();

        $this->artisan('mail:test-notification', ['email' => 'marie@flexilivre.com'])
            ->expectsOutputToContain("tâche #{$task->id}")
            ->assertSuccessful()
            ->run();

        $email = $this->lastEmail();
        $this->assertSame('[Catalogue] Couverture', $email->getSubject());
        $this->assertStringContainsString("{$project->owner->name} vous a assigné", $email->getHtmlBody());
        $this->assertSame(0, $marie->notifications()->count());
    }

    public function test_every_notification_type_is_sent_without_saving_anything(): void
    {
        [$marie, $task] = $this->memberWithTask();

        foreach (['comment' => 'a commenté', 'update' => 'a modifié', 'mention' => 'vous a mentionné', 'assigned' => 'vous a assigné'] as $type => $text) {
            $this->artisan('mail:test-notification', ['email' => 'marie@flexilivre.com', '--type' => $type, '--task' => $task->id])->assertSuccessful()->run();
            $this->assertStringContainsString($text, $this->lastEmail()->getHtmlBody());
        }

        $this->assertCount(4, $this->sentEmails());
        $this->assertSame(0, $task->comments()->count());
        $this->assertSame(0, $marie->notifications()->count());
    }

    public function test_notification_can_go_to_an_address_without_account(): void
    {
        $this->memberWithTask();

        $this->artisan('mail:test-notification', ['email' => 'test@example.com', '--type' => 'update'])->assertSuccessful()->run();

        $this->assertSame('test@example.com', $this->lastEmail()->getTo()[0]->getAddress());
        $this->assertFalse(User::where('email', 'test@example.com')->exists());
    }

    public function test_deactivated_account_is_reported_as_not_sent(): void
    {
        [$marie] = $this->memberWithTask();
        $marie->deactivate();

        $this->artisan('mail:test-notification', ['email' => 'marie@flexilivre.com'])
            ->expectsOutputToContain('le compte est désactivé')
            ->assertFailed()
            ->run();

        $this->assertEmpty($this->sentEmails());
    }

    public function test_notification_rejects_unknown_type_and_task(): void
    {
        $this->memberWithTask();

        $this->artisan('mail:test-notification', ['email' => 'marie@flexilivre.com', '--type' => 'oops'])->assertFailed();
        $this->artisan('mail:test-notification', ['email' => 'marie@flexilivre.com', '--task' => 9999])->assertFailed();
    }
}

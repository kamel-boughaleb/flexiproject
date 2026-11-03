<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/** Langue de l'interface : choisie à l'inscription, liée au compte, modifiable dans le profil. */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    /** Invite une personne et renvoie le jeton de son invitation. */
    private function invite(User $admin, string $email = 'sam@flexilivre.com'): string
    {
        Notification::fake();
        $this->actingAs($admin)->post('/team', ['email' => $email, 'name' => 'Sam'])->assertSessionHasNoErrors();
        $token = null;
        Notification::assertSentTo(User::where('email', $email)->first(), InvitationNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        auth()->logout();

        return $token;
    }

    public function test_guest_language_follows_the_browser_then_the_switcher(): void
    {
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.8')->get('/login')
            ->assertInertia(fn ($page) => $page->where('locale', 'en')->has('locales.fr'));

        $this->post('/locale', ['locale' => 'fr'])->assertRedirect();
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.8')->get('/login')
            ->assertInertia(fn ($page) => $page->where('locale', 'fr'));

        $this->post('/locale', ['locale' => 'de'])->assertSessionHasErrors('locale');
    }

    public function test_account_created_from_an_invitation_keeps_the_language_used(): void
    {
        $token = $this->invite(User::factory()->admin()->create(['locale' => 'fr']));

        $this->post('/locale', ['locale' => 'en']);
        $this->post("/invitation/{$token}", [
            'name' => 'Sam Taylor',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ])->assertRedirect('/dashboard');

        $sam = User::where('email', 'sam@flexilivre.com')->first();
        $this->assertSame('en', $sam->locale);

        // Une fois connecté, c'est la langue du compte qui compte, pas celle du navigateur.
        $this->actingAs($sam)->get('/dashboard')->assertInertia(fn ($page) => $page->where('locale', 'en'));
    }

    public function test_first_google_sign_in_keeps_the_language_used(): void
    {
        $this->invite(User::factory()->admin()->create(['locale' => 'fr']), 'camille@flexilivre.com');
        Socialite::shouldReceive('driver->user')->andReturn((new SocialiteUser)->map([
            'id' => 'google-1', 'name' => 'Camille', 'email' => 'camille@flexilivre.com', 'avatar' => null,
        ]));

        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->assertSame('en', User::where('email', 'camille@flexilivre.com')->value('locale'));

        // Les connexions suivantes ne changent plus la langue du compte.
        auth()->logout();
        $this->withHeader('Accept-Language', 'fr-FR')->get('/auth/google/callback');
        $this->assertSame('en', User::where('email', 'camille@flexilivre.com')->value('locale'));
    }

    public function test_invitation_email_is_written_in_the_inviters_language(): void
    {
        $this->invite(User::factory()->admin()->create(['locale' => 'en']));

        $sam = User::where('email', 'sam@flexilivre.com')->first();
        $this->assertSame('en', $sam->locale);
        Notification::assertSentTo($sam, InvitationNotification::class, function ($n, $channels, $notifiable, $locale) {
            return $locale === 'en';
        });
    }

    public function test_user_changes_language_in_profile(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $this->actingAs($user)->patch('/settings/profile', ['name' => $user->name, 'email' => $user->email, 'locale' => 'en'])
            ->assertSessionHasNoErrors();
        $this->assertSame('en', $user->fresh()->locale);
        $this->get('/settings/profile')->assertInertia(fn ($page) => $page->where('locale', 'en'));

        $this->patch('/settings/profile', ['name' => $user->name, 'email' => $user->email, 'locale' => 'xx'])
            ->assertSessionHasErrors('locale');
    }

    public function test_notifications_and_history_use_each_readers_language(): void
    {
        $project = Project::factory()->create();
        $project->owner->update(['locale' => 'fr']);
        $sam = User::factory()->create(['locale' => 'en', 'name' => 'Sam']);
        $project->members()->attach($sam, ['role' => 'editor']);
        $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $project->sections()->first()->id, 'title' => 'Cover']);

        $this->actingAs($project->owner)->patch("/tasks/{$task->id}", ['assignee_id' => $sam->id])->assertSessionHasNoErrors();

        // La cloche de Sam est en anglais, même si l'action vient d'un collaborateur francophone.
        $this->assertSame("{$project->owner->name} assigned you “Cover”", $sam->notifications()->first()->data['message']);

        // L'historique est traduit au moment de l'afficher, dans la langue de chaque lecteur.
        $this->actingAs($sam)->get("/projects/{$project->id}/settings")->assertInertia(fn ($page) => $page
            ->where('activities.0.message', 'updated “Cover”: Assignee: none → Sam'));
        $this->actingAs($project->owner)->get("/projects/{$project->id}/settings")->assertInertia(fn ($page) => $page
            ->where('activities.0.message', 'a modifié « Cover » : Responsable : aucun → Sam'));
    }

    public function test_assignment_email_is_sent_in_the_recipients_language(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        $sam = User::factory()->create(['locale' => 'en']);
        $project->members()->attach($sam, ['role' => 'editor']);
        $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $project->sections()->first()->id]);

        $this->actingAs($project->owner)->patch("/tasks/{$task->id}", ['assignee_id' => $sam->id]);

        Notification::assertSentTo($sam, TaskAssigned::class, fn ($n, $channels, $notifiable, $locale) => $locale === 'en');
    }
}

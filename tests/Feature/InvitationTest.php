<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    /** Invite quelqu'un depuis la page Équipe et renvoie le jeton reçu par e-mail. */
    private function inviteFromTeam(string $email = 'lea@flexilivre.com'): string
    {
        Notification::fake();
        $this->actingAs(User::factory()->create())->post('/team', ['email' => $email, 'name' => 'Léa'])->assertSessionHasNoErrors();
        $token = null;
        Notification::assertSentTo(User::where('email', $email)->first(), InvitationNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        auth()->logout();

        return $token;
    }

    public function test_team_page_lists_pending_invitations(): void
    {
        $this->inviteFromTeam();

        $this->actingAs(User::factory()->create())->get('/team')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Team')->where('users', fn ($users) => collect($users)->contains(fn ($u) => $u['email'] === 'lea@flexilivre.com' && $u['pending'])));
    }

    public function test_inviting_an_existing_member_is_refused(): void
    {
        $existing = User::factory()->create();

        $this->actingAs(User::factory()->create())->post('/team', ['email' => $existing->email])->assertSessionHasErrors('email');
    }

    public function test_invited_person_creates_account_with_photo(): void
    {
        Storage::fake('local');
        $token = $this->inviteFromTeam();

        $this->get("/invitation/{$token}")->assertOk()->assertInertia(fn ($page) => $page->component('auth/AcceptInvitation'));

        $this->post("/invitation/{$token}", [
            'name' => 'Léa Martin',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'photo' => UploadedFile::fake()->image('moi.png', 600, 400),
        ])->assertRedirect('/dashboard');

        $lea = User::where('email', 'lea@flexilivre.com')->first();
        $this->assertAuthenticatedAs($lea);
        $this->assertSame('Léa Martin', $lea->name);
        $this->assertFalse($lea->isPending());
        $this->assertNotNull($lea->photo_path);
        Storage::disk('local')->assertExists($lea->photo_path);
        $this->assertStringStartsWith("/users/{$lea->id}/photo", $lea->avatar);
        $this->get($lea->avatar)->assertOk();

        // Le lien ne sert qu'une fois.
        auth()->logout();
        $this->get("/invitation/{$token}")->assertRedirect('/login');
    }

    public function test_expired_invitation_is_refused(): void
    {
        $token = $this->inviteFromTeam();
        $this->travel(User::INVITATION_DAYS + 1)->days();

        $this->get("/invitation/{$token}")->assertRedirect('/login');
    }

    public function test_resending_replaces_the_link(): void
    {
        $old = $this->inviteFromTeam();
        $lea = User::where('email', 'lea@flexilivre.com')->first();

        $this->actingAs(User::factory()->create())->post("/team/{$lea->id}/resend")->assertRedirect();
        Notification::assertSentToTimes($lea, InvitationNotification::class, 2);
        auth()->logout();

        $this->get("/invitation/{$old}")->assertRedirect('/login');
    }

    public function test_pending_invitation_can_be_cancelled_but_not_active_accounts(): void
    {
        $this->inviteFromTeam();
        $lea = User::where('email', 'lea@flexilivre.com')->first();
        $me = User::factory()->create();

        $this->actingAs($me)->delete("/team/{$me->id}")->assertStatus(422);
        $this->actingAs($me)->delete("/team/{$lea->id}")->assertRedirect();
        $this->assertModelMissing($lea);
    }

    public function test_user_can_change_and_remove_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/photo', ['photo' => UploadedFile::fake()->image('a.jpg')])->assertSessionHasNoErrors();
        $path = $user->fresh()->photo_path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)->delete('/settings/photo');
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($user->fresh()->avatar);
    }
}

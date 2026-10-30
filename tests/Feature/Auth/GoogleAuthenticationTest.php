<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $email): void
    {
        $google = (new SocialiteUser)->map([
            'id' => 'google-123',
            'name' => 'Camille Martin',
            'email' => $email,
            'avatar' => 'https://lh3.googleusercontent.com/photo.jpg',
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($google);
    }

    public function test_redirect_explains_when_google_is_not_configured()
    {
        config(['services.google.client_id' => null]);

        $this->get('/auth/google')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');
    }

    public function test_redirect_sends_user_to_google()
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret', 'services.google.allowed_domains' => ['flexilivre.com']]);

        $response = $this->get('/auth/google');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/', $response->headers->get('Location'));
        $this->assertStringContainsString('hd=flexilivre.com', $response->headers->get('Location'));
    }

    public function test_new_google_user_is_created_and_logged_in()
    {
        config(['services.google.allowed_domains' => ['flexilivre.com']]);
        $this->fakeGoogleUser('Camille@flexilivre.com');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $user = User::where('email', 'camille@flexilivre.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_existing_user_keeps_their_name_and_avatar()
    {
        $existing = User::factory()->create(['email' => 'camille@flexilivre.com', 'name' => 'Camille', 'avatar' => 'avatars/perso.jpg']);
        config(['services.google.allowed_domains' => []]);
        $this->fakeGoogleUser('camille@flexilivre.com');

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($existing);
        $existing->refresh();
        $this->assertSame('Camille', $existing->name);
        $this->assertSame('avatars/perso.jpg', $existing->avatar);
    }

    public function test_other_domains_are_refused()
    {
        config(['services.google.allowed_domains' => ['flexilivre.com']]);
        $this->fakeGoogleUser('someone@gmail.com');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_cancelled_google_login_returns_to_login_page()
    {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');
        $this->assertGuest();
    }
}

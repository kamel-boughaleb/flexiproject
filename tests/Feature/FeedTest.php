<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Feed;
use App\Models\FeedPost;
use App\Models\User;
use App\Notifications\FeedPostPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_anyone_creates_a_feed_and_is_subscribed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/feeds', ['name' => 'Vie de l’entreprise', 'description' => 'Les nouvelles', 'color' => '#6366f1'])
            ->assertRedirect();

        $feed = Feed::sole();
        $this->assertSame($user->id, $feed->owner_id);
        $this->assertTrue($feed->isSubscribed($user));
    }

    public function test_subscribed_feeds_are_shared_with_the_sidebar(): void
    {
        $user = User::factory()->create();
        $feed = Feed::factory()->create(['name' => 'Production']);
        Feed::factory()->create(['name' => 'Autre']);
        $feed->subscribers()->attach($user);

        $this->actingAs($user)->get('/feeds')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Feeds/Index')
                ->has('feeds', 2)
                ->where('sidebarFeeds', [['id' => $feed->id, 'name' => 'Production', 'color' => '#6366f1']]));
    }

    public function test_subscribe_and_unsubscribe(): void
    {
        $user = User::factory()->create();
        $feed = Feed::factory()->create();

        $this->actingAs($user)->post("/feeds/{$feed->id}/subscription")->assertRedirect();
        $this->actingAs($user)->post("/feeds/{$feed->id}/subscription")->assertRedirect();
        $this->assertSame(1, $feed->subscribers()->whereKey($user->id)->count());

        $this->actingAs($user)->delete("/feeds/{$feed->id}/subscription")->assertRedirect();
        $this->assertFalse($feed->isSubscribed($user));
    }

    public function test_new_post_notifies_active_subscribers_except_the_author(): void
    {
        Notification::fake();
        $feed = Feed::factory()->create();
        [$reader, $author, $inactive, $outsider] = User::factory()->count(4)->create();
        $inactive->deactivate();
        $feed->subscribers()->attach([$reader->id, $author->id, $inactive->id]);

        $this->actingAs($author)->post("/feeds/{$feed->id}/posts", [
            'title' => 'Nouveau catalogue',
            'published_on' => '2026-11-09',
            'body' => '<p>Voir <a href="https://flexilivre.com">le site</a></p><script>alert(1)</script>',
            'files' => [UploadedFile::fake()->create('catalogue.pdf', 200, 'application/pdf')],
        ])->assertRedirect("/feeds/{$feed->id}")->assertSessionHasNoErrors();

        $post = FeedPost::sole();
        $this->assertStringNotContainsString('<script', $post->body);
        $this->assertStringContainsString('href="https://flexilivre.com"', $post->body);
        $this->assertSame('catalogue.pdf', $post->attachments()->sole()->name);
        Storage::disk('local')->assertExists($post->attachments()->sole()->path);

        Notification::assertSentTo([$reader, $feed->owner], FeedPostPublished::class, fn ($n, $channels) => $channels === ['database', 'mail']);
        Notification::assertNotSentTo([$author, $inactive, $outsider], FeedPostPublished::class);
    }

    public function test_post_email_shows_the_feed_title_and_excerpt(): void
    {
        $feed = Feed::factory()->create(['name' => 'Production']);
        $post = FeedPost::factory()->create(['feed_id' => $feed->id, 'title' => 'Nouvelle presse', 'body' => '<p>Elle arrive lundi.</p>']);
        $reader = User::factory()->create(['name' => 'Marie Durand']);

        $mail = (new FeedPostPublished($post, $post->user))->toMail($reader);

        $this->assertSame('[Production] Nouvelle presse', $mail->subject);
        $this->assertStringEndsWith("/feeds/{$feed->id}/posts/{$post->id}", $mail->actionUrl);
        $html = (string) $mail->render();
        $this->assertStringContainsString('Bonjour Marie,', $html);
        $this->assertStringContainsString('Elle arrive lundi.', $html);
        $this->assertStringContainsString('abonné au fil « Production »', $html);
    }

    public function test_only_author_feed_owner_or_admin_edit_a_post(): void
    {
        $post = FeedPost::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->patch("/posts/{$post->id}", ['title' => 'Piraté', 'published_on' => '2026-11-09'])->assertForbidden();
        $this->actingAs($other)->delete("/posts/{$post->id}")->assertForbidden();

        $this->actingAs($post->user)->patch("/posts/{$post->id}", ['title' => 'Corrigé', 'published_on' => '2026-11-10', 'body' => '<p>Ok</p>'])
            ->assertRedirect();
        $this->assertSame('Corrigé', $post->fresh()->title);

        $this->actingAs($post->feed->owner)->delete("/posts/{$post->id}")->assertRedirect("/feeds/{$post->feed_id}");
        $this->assertModelMissing($post);
    }

    public function test_only_owner_or_admin_manage_a_feed_and_deleting_removes_files(): void
    {
        $feed = Feed::factory()->create();
        $post = FeedPost::factory()->create(['feed_id' => $feed->id]);
        $this->actingAs($post->user)->post("/posts/{$post->id}/attachments", ['files' => [UploadedFile::fake()->image('photo.jpg')]])->assertRedirect();
        $path = Attachment::sole()->path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs(User::factory()->create())->patch("/feeds/{$feed->id}", ['name' => 'X', 'color' => '#6366f1'])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->patch("/feeds/{$feed->id}", ['name' => 'Renommé', 'color' => '#6366f1'])->assertRedirect();
        $this->assertSame('Renommé', $feed->fresh()->name);

        $this->actingAs($feed->owner)->delete("/feeds/{$feed->id}")->assertRedirect('/feeds');
        $this->assertModelMissing($post);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_team_members_view_post_attachments_and_images(): void
    {
        $post = FeedPost::factory()->create();
        $attachment = $post->attachments()->create([
            'user_id' => $post->user_id, 'name' => 'note.txt', 'path' => 'attachments/feeds/x/note.txt', 'mime' => 'text/plain', 'size' => 5,
        ]);
        Storage::disk('local')->put($attachment->path, 'hello');
        $reader = User::factory()->create();

        $this->actingAs($reader)->get("/attachments/{$attachment->id}")->assertOk();
        $this->actingAs($reader)->delete("/attachments/{$attachment->id}")->assertForbidden();

        $url = $this->actingAs($reader)->post("/feeds/{$post->feed_id}/images", ['image' => UploadedFile::fake()->image('a.png')])
            ->assertOk()->json('url');
        $this->assertMatchesRegularExpression('#^/feeds/\d+/images/[A-Za-z0-9]+\.png$#', $url);
        $this->actingAs($reader)->get($url)->assertOk();

        // L'image insérée dans le texte est conservée par le nettoyage du HTML.
        $this->actingAs($post->user)->patch("/posts/{$post->id}", ['title' => 'T', 'published_on' => '2026-11-09', 'body' => "<p>x</p><img src=\"{$url}\">"]);
        $this->assertStringContainsString($url, $post->fresh()->body);
    }

    public function test_feed_and_post_pages_render(): void
    {
        $post = FeedPost::factory()->create();
        $reader = User::factory()->create();

        $this->actingAs($reader)->get("/feeds/{$post->feed_id}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Feeds/Show')->has('posts.data', 1)->where('posts.data.0.can_manage', false)->where('feed.subscribed', false));
        $this->actingAs($reader)->get("/feeds/{$post->feed_id}/posts/{$post->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Feeds/Post')->where('post.title', $post->title));
        $this->actingAs($reader)->get('/feeds/'.Feed::factory()->create()->id."/posts/{$post->id}")->assertNotFound();
    }

    public function test_test_mail_command_sends_the_post_email(): void
    {
        $feed = Feed::factory()->create(['name' => 'Production']);
        FeedPost::factory()->create(['feed_id' => $feed->id, 'title' => 'Nouvelle presse']);

        $this->artisan('mail:test-feed-post', ['email' => 'test@example.com'])
            ->expectsOutputToContain('E-mail envoyé à test@example.com')
            ->assertSuccessful()
            ->run();

        $messages = iterator_to_array(Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame('[Production] Nouvelle presse', end($messages)->getOriginalMessage()->getSubject());
        $this->assertSame(0, DB::table('notifications')->count());
    }
}

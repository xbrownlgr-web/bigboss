<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Models\VipFreeClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Console d'administration : toutes les pages s'affichent, et la messagerie envoie / reçoit
 * sans rechargement (réponses JSON utilisées par la page Messages).
 */
class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['data' => []])]);
        Mail::fake();
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@binhoflash.com', 'is_active' => true, 'name' => 'Admin BIG BOSS']);
    }

    private function client(array $attributes = []): User
    {
        return User::factory()->create(['is_active' => true, ...$attributes]);
    }

    public function test_every_admin_page_renders(): void
    {
        $admin = $this->admin();
        $client = $this->client(['name' => 'Koffi Mensah', 'phone' => '+229 97 00 00 00']);
        $other = $this->client(['name' => 'Aïcha Dossou']);

        Message::create(['user_id' => $client->id, 'sender' => 'user', 'content' => 'Bonjour, ma recharge de 5 000 F n’est pas encore arrivée.', 'is_read' => false]);
        Message::create(['user_id' => $client->id, 'sender' => 'admin', 'content' => 'Je vérifie tout de suite 👍', 'is_read' => false]);
        Message::create(['user_id' => $client->id, 'sender' => 'user', 'content' => 'Merci beaucoup !', 'is_read' => false]);
        Message::create(['user_id' => $other->id, 'sender' => 'user', 'content' => 'Comment avoir le VIP Gold ?', 'is_read' => true]);

        VipFreeClaim::create([
            'user_id' => $other->id,
            'level' => 'Silver',
            'promo_code' => 'CR7MILLIONS',
            'account_id' => '1234567890',
            'screenshot' => base64_encode('png'),
            'screenshot_mime' => 'image/png',
            'status' => VipFreeClaim::STATUS_PENDING,
        ]);

        $pages = [
            'dashboard' => '/admin',
            'transactions' => '/admin/transactions',
            'messages-liste' => '/admin/messages',
            'messages-non-lues' => '/admin/messages?filter=unread',
            'messages-conversation' => '/admin/messages?user_id=' . $client->id,
            'vip' => '/admin/vip',
            'settings' => '/admin/settings',
            'pronostics' => '/admin/pronostics',
            'users' => '/admin/users',
            'user-show' => '/admin/users/' . $client->id,
            'depots' => '/admin/depots',
            'videos' => '/admin/videos',
        ];

        foreach ($pages as $name => $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk();

            // Captures de contrôle (facultatives) : DUMP_ADMIN_HTML=<dossier>.
            if ($directory = env('DUMP_ADMIN_HTML')) {
                file_put_contents($directory . DIRECTORY_SEPARATOR . Str::slug($name) . '.html', $response->getContent());
            }
        }
    }

    public function test_listing_conversations_does_not_mark_them_read(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        Message::create(['user_id' => $client->id, 'sender' => 'user', 'content' => 'Allô ?', 'is_read' => false]);

        $this->actingAs($admin)->get('/admin/messages')->assertOk();
        $this->assertSame(1, Message::where('user_id', $client->id)->where('is_read', false)->count());

        $this->actingAs($admin)->get('/admin/messages?user_id=' . $client->id)->assertOk();
        $this->assertSame(0, Message::where('user_id', $client->id)->where('is_read', false)->count());
    }

    public function test_reply_without_reload_and_feed_of_new_messages(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $first = Message::create(['user_id' => $client->id, 'sender' => 'user', 'content' => 'Salut', 'is_read' => false]);

        $this->actingAs($admin)
            ->postJson('/admin/messages/' . $client->id . '/reply', ['content' => 'Bonjour, comment puis-je vous aider ?'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message.sender', 'admin')
            ->assertJsonPath('message.content', 'Bonjour, comment puis-je vous aider ?');

        // Message vide (espaces seulement) : refusé proprement, pas d'envoi fantôme.
        $this->actingAs($admin)
            ->postJson('/admin/messages/' . $client->id . '/reply', ['content' => '   '])
            ->assertStatus(422);

        Message::create(['user_id' => $client->id, 'sender' => 'user', 'content' => 'Merci', 'is_read' => false]);

        $this->actingAs($admin)
            ->getJson('/admin/messages/' . $client->id . '/feed?after=' . $first->id)
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.1.content', 'Merci');

        $this->assertSame(0, Message::where('user_id', $client->id)->where('sender', 'user')->where('is_read', false)->count());
    }

    public function test_classic_form_reply_still_works_without_javascript(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->post('/admin/messages/' . $client->id . '/reply', ['content' => 'Réponse sans JavaScript'])
            ->assertRedirect('/admin/messages?user_id=' . $client->id);

        $this->assertDatabaseHas('messages', ['user_id' => $client->id, 'sender' => 'admin', 'content' => 'Réponse sans JavaScript']);
    }
}

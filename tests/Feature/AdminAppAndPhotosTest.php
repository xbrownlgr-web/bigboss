<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\MessageImage;
use App\Models\Pronostic;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VipSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Demandes du fondateur du 2026-11-15 : photos dans le support (dans les deux sens), vocaux de
 * l'admin, et mode administrateur dans l'application.
 */
class AdminAppAndPhotosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@bigboss.app', 'is_active' => true]);
    }

    private function member(): User
    {
        return User::factory()->create(['is_active' => true, 'name' => 'Awa Diallo', 'phone' => '+237 6 99 00 11 22']);
    }

    public function test_a_client_sends_a_photo_and_it_is_deleted_after_three_days(): void
    {
        Http::fake();
        $member = $this->member();
        Sanctum::actingAs($member);

        $url = $this->post('/api/v1/messages', ['image' => UploadedFile::fake()->image('recu.jpg', 600, 800), 'content' => 'Mon reçu'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('message.image_url');

        $this->assertNotNull($url);
        $this->get($url)->assertOk();
        $this->assertSame('Mon reçu', Message::query()->sole()->content);

        $this->travel(3)->days();
        $this->travel(1)->hours();
        $this->artisan('bigboss:purge-voice-messages')->assertSuccessful();
        $this->assertSame(0, MessageImage::query()->count());
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/messages')->assertJsonPath('messages.0.image_expired', true);
    }

    public function test_the_admin_replies_with_a_photo_or_a_voice_from_the_website(): void
    {
        Http::fake();
        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin)
            ->post('/admin/messages/' . $member->id . '/reply', ['image' => UploadedFile::fake()->image('capture.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message.image_url', fn ($url) => str_starts_with((string) $url, '/admin/messages/image/'));

        $this->actingAs($admin)
            ->post('/admin/messages/' . $member->id . '/reply', [
                'audio' => UploadedFile::fake()->create('vocal.webm', 40, 'audio/webm'),
                'duration' => 7,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message.audio_duration', 7);

        // Le client reçoit les deux, avec un lien d'écoute / d'affichage.
        Sanctum::actingAs($member);
        $messages = $this->getJson('/api/v1/messages')->assertOk()->json('messages');
        $this->assertNotNull($messages[0]['image_url']);
        $this->assertNotNull($messages[1]['audio_url']);
    }

    public function test_the_admin_runs_the_support_from_the_app(): void
    {
        Http::fake();
        Mail::fake();
        $admin = $this->admin();
        $member = $this->member();
        Message::create(['user_id' => $member->id, 'sender' => 'user', 'content' => 'Bonjour, ma recharge ?', 'is_read' => false]);
        $transaction = Transaction::create([
            'user_id' => $member->id, 'type' => 'recharge', 'platform' => '1xbet', 'player_id' => '123456',
            'amount' => 5000, 'operator' => 'MTN MoMo', 'reference_code' => 'TX1', 'status' => 'pending',
        ]);

        // Un client ordinaire n'a pas accès au mode admin.
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/admin/conversations')->assertForbidden();
        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.is_admin', false);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.is_admin', true);
        $this->getJson('/api/v1/admin/summary')->assertJsonPath('pending_transactions', 1)->assertJsonPath('unread_messages', 1);
        $this->getJson('/api/v1/admin/conversations')->assertJsonPath('conversations.0.name', 'Awa Diallo');
        $this->getJson('/api/v1/admin/conversations/' . $member->id)->assertJsonPath('messages.0.content', 'Bonjour, ma recharge ?');

        $this->post('/api/v1/admin/conversations/' . $member->id, ['content' => 'C’est validé ✅'], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/api/v1/admin/transactions/' . $transaction->id . '/approve')->assertOk()->assertJsonPath('status', 'completed');
        $this->postJson('/api/v1/admin/transactions/' . $transaction->id . '/approve')->assertStatus(422);

        $this->postJson('/api/v1/admin/vip/grant', ['user_id' => $member->id, 'level' => 'Gold', 'days' => 7])->assertOk();
        $this->assertTrue(VipSubscription::query()->where('user_id', $member->id)->where('level', 'Gold')->exists());
        $this->getJson('/api/v1/admin/users?search=6990')->assertJsonPath('users.0.id', $member->id);
    }
    public function test_the_admin_sees_every_vip_coupon_unlocked_in_the_app(): void
    {
        $coupon = Pronostic::create([
            'niveau' => 'Gold', 'couleur' => '#000000', 'cote_totale' => 3.2, 'probabilite' => 70,
            'evenements' => [['match' => 'Lions vs Aigles', 'pronostic' => '1', 'cote' => 1.6]],
            'match_date' => now()->toDateString(), 'match_time' => '18:00', 'team_home' => 'A', 'team_away' => 'B',
            'competition' => 'Test', 'prediction' => 'Test', 'odds' => 3.2, 'confidence' => 70, 'analysis' => 'Test',
            'is_vip' => true, 'published_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($this->member());
        $this->getJson('/api/v1/pronostics')->assertOk()->assertJsonPath('pronostics.0.locked', true);

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/pronostics')
            ->assertOk()
            ->assertJsonPath('pronostics.0.id', $coupon->id)
            ->assertJsonPath('pronostics.0.locked', false)
            ->assertJsonPath('pronostics.0.evenements.0.match', 'Lions vs Aigles');
    }

    public function test_the_admin_lists_active_vips_grants_by_email_lists_users_and_broadcasts_push(): void
    {
        Http::fake(['https://exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $admin = $this->admin();
        $vipMember = User::factory()->create(['is_active' => true, 'name' => 'Marc Kouassi', 'email' => 'marc@example.com', 'fcm_token' => 'ExponentPushToken[marc]']);
        $freeMember = User::factory()->create(['is_active' => true, 'name' => 'Lucie Mensah', 'email' => 'lucie@example.com', 'fcm_token' => 'ExponentPushToken[lucie]']);

        // Un client normal ne peut pas envoyer de push broadcast ni voir la liste VIP
        Sanctum::actingAs($freeMember);
        $this->getJson('/api/v1/admin/vip/subscriptions')->assertForbidden();
        $this->postJson('/api/v1/admin/push/broadcast', ['title' => 'Test', 'body' => 'Message'])->assertForbidden();

        Sanctum::actingAs($admin);

        // Activation directement par e-mail (insensible à la casse)
        $this->postJson('/api/v1/admin/vip/grant', [
            'email' => 'MARC@example.com',
            'level' => 'Complet',
            'days' => 30,
        ])->assertOk()->assertJsonPath('user_name', 'Marc Kouassi');

        // E-mail inexistant -> 422 propre
        $this->postJson('/api/v1/admin/vip/grant', [
            'email' => 'inconnu@example.com',
            'level' => 'Gold',
            'days' => 30,
        ])->assertStatus(422);

        // Liste de tous les abonnés VIP actifs
        $this->getJson('/api/v1/admin/vip/subscriptions')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('subscriptions.0.user_email', 'marc@example.com')
            ->assertJsonPath('subscriptions.0.level', 'Complet');

        // Nombre total d'utilisateurs + liste sans recherche obligatoire
        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonPath('total_users', 3)
            ->assertJsonPath('active_vip_users', 1)
            ->assertJsonCount(3, 'users');

        // Push broadcast à tous les utilisateurs actifs avec token (2 appareils)
        $this->postJson('/api/v1/admin/push/broadcast', [
            'title' => 'Nouveaux pronostics prêts',
            'body' => 'Les 5 catégories du jour sont disponibles sur BIG BOSS.',
            'target' => 'all',
        ])->assertOk()->assertJsonPath('sent', 2);

        // Push broadcast aux abonnés VIP uniquement (1 appareil)
        $this->postJson('/api/v1/admin/push/broadcast', [
            'title' => 'Alerte VIP',
            'body' => 'Nouveau coupon spécial disponible.',
            'target' => 'vip',
        ])->assertOk()->assertJsonPath('sent', 1);
    }

}

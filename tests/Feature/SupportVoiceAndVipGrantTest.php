<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Message;
use App\Models\MessageAudio;
use App\Models\User;
use App\Models\VipSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Demandes du fondateur du 2026-11-11 : alerte WhatsApp à chaque abonnement payé en ligne,
 * activation manuelle (offre + durée au choix), assistant IA coupé (support humain direct),
 * messages vocaux lus dans l'admin et supprimés après 3 jours.
 */
class SupportVoiceAndVipGrantTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@bigboss.app', 'is_active' => true]);
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(['is_active' => true, 'name' => 'Kofi Mensah', 'phone' => '+229 01 97 45 12 33', ...$attributes]);
    }

    public function test_an_online_payment_alerts_the_admin_on_whatsapp(): void
    {
        AppSetting::setValue('callmebot_phone', '+229 01 97 00 00 00');
        AppSetting::setValue('callmebot_apikey', '123456');
        AppSetting::setValue('maketou_api_key', 'mk-test');
        Http::fake([
            'api.callmebot.com/*' => Http::response('Message queued.'),
            'api.maketou.net/*' => Http::response(['cart' => ['status' => 'completed']]),
        ]);

        $member = $this->member();
        Cache::put('maketou:cart:cart-1', ['user_id' => $member->id, 'level' => 'Gold', 'days' => 30], now()->addDay());
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/vip/checkout/confirm', ['cart_id' => 'cart-1'])->assertOk()->assertJsonPath('activated', true);

        $this->assertTrue(VipSubscription::query()->where('user_id', $member->id)->where('level', 'Gold')->exists());
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'callmebot')
            && str_contains($request['text'], 'Abonnement payé en ligne')
            && str_contains($request['text'], 'VIP Gold')
            && str_contains($request['text'], 'Kofi Mensah'));
    }

    public function test_the_admin_activates_any_offer_for_the_chosen_duration(): void
    {
        $admin = $this->admin();
        $member = $this->member();

        // Par téléphone, quel que soit le format, avec une durée libre.
        $this->actingAs($admin)
            ->post('/admin/vip/grant', ['client' => '0197451233', 'level' => 'Score', 'days' => 30, 'custom_days' => 45])
            ->assertSessionHas('success');

        $score = VipSubscription::query()->where('user_id', $member->id)->where('level', 'Score')->sole();
        $this->assertEqualsWithDelta(45, now()->diffInDays($score->ends_at), 0.01);

        // Depuis la fiche client : 1 semaine de Platinum, puis prolongée d'un mois.
        $this->actingAs($admin)->post('/admin/vip/grant', ['user_id' => $member->id, 'level' => 'Platinum', 'days' => 7])->assertSessionHas('success');
        $this->actingAs($admin)->post('/admin/vip/grant', ['user_id' => $member->id, 'level' => 'Platinum', 'days' => 30])->assertSessionHas('success');

        $platinum = VipSubscription::query()->where('user_id', $member->id)->where('level', 'Platinum')->sole();
        $this->assertEqualsWithDelta(37, now()->diffInDays($platinum->ends_at), 0.01);

        $this->actingAs($admin)
            ->post('/admin/vip/grant', ['client' => 'inconnu@example.com', 'level' => 'Gold', 'days' => 30])
            ->assertSessionHas('error');

        $this->actingAs($admin)->get('/admin/users/' . $member->id)->assertOk()->assertSee('Activer', false)->assertSee('Score exact', false);
        $this->actingAs($admin)->get('/admin/vip')->assertOk()->assertSee('Activer un abonnement', false);
    }

    public function test_with_the_assistant_switched_off_the_support_goes_straight_to_a_human(): void
    {
        $this->assertFalse($this->getJson('/api/v1/settings/public')->json('assistant_enabled'));

        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        Http::fake();
        $member = $this->member();
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/assistant/messages', ['content' => 'Ma recharge de 5000 n’est pas arrivée'])
            ->assertCreated()
            ->assertJsonPath('reply.handoff', true);

        // L'IA n'a pas été appelée, et le message est arrivé chez un humain (Admin > Messages).
        Http::assertNothingSent();
        $this->assertSame('Ma recharge de 5000 n’est pas arrivée', Message::query()->where('user_id', $member->id)->sole()->content);
    }

    public function test_a_voice_message_is_played_in_the_admin_and_deleted_after_three_days(): void
    {
        $member = $this->member();
        Sanctum::actingAs($member);

        $response = $this->post('/api/v1/messages', [
            'audio' => UploadedFile::fake()->create('vocal.m4a', 60, 'audio/mp4'),
            'duration' => 12,
        ], ['Accept' => 'application/json'])->assertCreated();

        $message = Message::query()->sole();
        $this->assertSame(12, $message->audio_duration);
        $this->assertSame(Message::VOICE_LABEL, $message->content);
        $this->assertNotNull($url = $response->json('message.audio_url'));

        // L'app l'écoute par le lien signé ; un lien modifié est refusé.
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'audio/mp4');
        $this->get('/api/v1/messages/' . $message->id . '/audio')->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/messages?user_id=' . $member->id)->assertOk()->assertSee('/admin/messages/audio/' . $message->id, false);
        $this->actingAs($admin)->get('/admin/messages/audio/' . $message->id)->assertOk();
        $this->actingAs($admin)->getJson('/admin/messages/threads')->assertOk()->assertJsonPath('counts.all', 1);
        $this->actingAs($admin)->getJson('/admin/messages/' . $member->id . '/conversation')->assertOk()->assertJsonPath('user_id', $member->id);

        // 3 jours plus tard : le son est supprimé de la base, le message reste (marqué supprimé).
        $this->travel(MessageAudio::RETENTION_DAYS)->days();
        $this->travel(1)->hours();
        $this->artisan('bigboss:purge-voice-messages')->assertSuccessful();

        $this->assertSame(0, MessageAudio::query()->count());
        $this->assertTrue(Message::query()->sole()->fresh()->isVoice());
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/messages')->assertOk()->assertJsonPath('messages.0.audio_expired', true)->assertJsonPath('messages.0.audio_url', null);
    }

    public function test_the_admin_sees_when_the_client_has_read_a_reply(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        Http::fake();

        Message::create(['user_id' => $member->id, 'sender' => 'user', 'content' => 'Bonjour', 'is_read' => false]);
        $reply = $this->actingAs($admin)->postJson('/admin/messages/' . $member->id . '/reply', ['content' => 'Bonjour, je regarde.'])->assertOk()->json('message');
        $this->assertFalse($reply['read']);

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/messages')->assertOk();

        $this->actingAs($admin)
            ->getJson('/admin/messages/' . $member->id . '/feed?after=' . $reply['id'])
            ->assertOk()
            ->assertJsonPath('read_ids.0', $reply['id']);
    }
}

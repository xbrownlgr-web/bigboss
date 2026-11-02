<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use App\Models\VipRequest;
use App\Models\VipSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VipSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        AppSetting::setValue('vip_whatsapp_number', '+229 01 97 00 00 00');
        AppSetting::setValue('vip_gold_price', '5000');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@binhoflash.com', 'is_active' => true]);
    }

    private function player(string $token = 'ExponentPushToken[player]'): User
    {
        return User::factory()->create(['is_active' => true, 'fcm_token' => $token, 'phone' => '0197000001']);
    }

    private function goldCoupon(): Pronostic
    {
        $this->actingAs($this->admin())
            ->post('/admin/vip/coupons', [
                'niveau' => 'Gold',
                'code_1xbet' => 'GOLD777',
                'code_melbet' => 'MEL555',
                'cote_totale' => '6.40',
                'valid_until' => now()->addHours(5)->format('Y-m-d\TH:i'),
                'note' => 'Mise conseillée : 2 %.',
            ])
            ->assertSessionHas('success');

        return Pronostic::query()->where('niveau', 'Gold')->latest('id')->firstOrFail();
    }

    private function pushesTo(string $token): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'exp.host'))
            ->flatMap(fn ($pair) => $pair[0]->data())
            ->filter(fn ($message) => $message['to'] === $token)
            ->values()
            ->all();
    }

    public function test_tapping_access_creates_one_key_and_a_prefilled_whatsapp_message(): void
    {
        Sanctum::actingAs($user = $this->player());

        $first = $this->postJson('/api/v1/vip/requests', ['level' => 'Gold'])->assertOk();
        $this->assertMatchesRegularExpression('/^BF-GOLD-[A-Z2-9]{5}$/', $first->json('key'));
        $this->assertSame('2290197000000', $first->json('whatsapp'));
        $this->assertSame(
            "Bonjour, je suis sur votre application Binho Flash et je veux intégrer le VIP Gold qui coûte 5 000 FCFA. Ma clé : {$first->json('key')}",
            $first->json('message'),
        );

        // Toucher encore « Accéder » ne crée pas de nouvelle demande.
        $this->postJson('/api/v1/vip/requests', ['level' => 'Gold'])->assertJsonPath('key', $first->json('key'));
        $this->assertSame(1, VipRequest::query()->where('user_id', $user->id)->count());

        $this->postJson('/api/v1/vip/requests', ['level' => 'Starter'])->assertUnprocessable();
    }

    public function test_the_admin_activates_a_key_for_30_days_and_the_key_works_only_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00', 'Africa/Porto-Novo'));
        $user = $this->player();
        $key = app(\App\Services\VipService::class)->requestKey($user, 'Gold')->key;
        $admin = $this->admin();

        // Étape 1 : la clé montre à qui elle appartient (clé tapée en minuscules, avec espaces).
        $this->actingAs($admin)
            ->post('/admin/vip/lookup', ['key' => ' ' . strtolower($key) . ' '])
            ->assertSessionHas('vip_lookup');
        $this->actingAs($admin)
            ->withSession(['vip_lookup' => VipRequest::query()->value('id')])
            ->get('/admin/vip')
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('5 000 FCFA');

        // Étape 2 : activation.
        $this->actingAs($admin)
            ->post('/admin/vip/requests/' . VipRequest::query()->value('id') . '/activate')
            ->assertSessionHas('success');

        $subscription = VipSubscription::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Gold', $subscription->level);
        $this->assertTrue($subscription->ends_at->equalTo(now()->addDays(30)));
        $this->assertNotEmpty($this->pushesTo('ExponentPushToken[player]'));

        // La même clé ne marche plus.
        $this->actingAs($admin)->post('/admin/vip/lookup', ['key' => $key])->assertSessionHas('error');
        $this->actingAs($admin)
            ->post('/admin/vip/requests/' . VipRequest::query()->value('id') . '/activate')
            ->assertSessionHas('error');
        $this->assertTrue($subscription->fresh()->ends_at->equalTo(now()->addDays(30)));
    }

    public function test_a_key_expires_after_7_days(): void
    {
        $key = app(\App\Services\VipService::class)->requestKey($this->player(), 'Gold')->key;
        Carbon::setTestNow(now()->addDays(8));

        $this->actingAs($this->admin())
            ->post('/admin/vip/lookup', ['key' => $key])
            ->assertSessionHas('error');
    }

    public function test_renewing_before_the_end_adds_30_days_to_the_end_date(): void
    {
        $vip = app(\App\Services\VipService::class);
        $user = $this->player();
        $admin = $this->admin();

        $vip->activate($vip->requestKey($user, 'Gold'), $admin);
        Carbon::setTestNow(now()->addDays(20));
        $renewed = $vip->activate($vip->requestKey($user, 'Gold'), $admin);

        // 10 jours restants + 30 jours : aucun jour perdu.
        $this->assertEqualsWithDelta(40, now()->diffInDays($renewed->ends_at), 0.01);
        $this->assertSame(1, VipSubscription::query()->count());
    }

    public function test_only_active_subscribers_of_the_level_see_the_vip_code(): void
    {
        $coupon = $this->goldCoupon();
        $subscriber = $this->player('ExponentPushToken[gold]');
        $platinum = $this->player('ExponentPushToken[platinum]');
        VipSubscription::create(['user_id' => $subscriber->id, 'level' => 'Gold', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        VipSubscription::create(['user_id' => $platinum->id, 'level' => 'Platinum', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);

        // Visiteur sans compte.
        $anonymous = collect($this->getJson('/api/v1/pronostics')->json('pronostics'))->firstWhere('id', $coupon->id);
        $this->assertTrue($anonymous['locked']);
        $this->assertNull($anonymous['code_1xbet']);

        // Abonné Platinum : chaque niveau est séparé.
        Sanctum::actingAs($platinum);
        $other = collect($this->getJson('/api/v1/pronostics')->json('pronostics'))->firstWhere('id', $coupon->id);
        $this->assertTrue($other['locked']);
        $this->assertNull($other['code_1xbet']);

        // Abonné Gold.
        Sanctum::actingAs($subscriber);
        $gold = collect($this->getJson('/api/v1/pronostics')->json('pronostics'))->firstWhere('id', $coupon->id);
        $this->assertFalse($gold['locked']);
        $this->assertTrue($gold['subscribed']);
        $this->assertSame('GOLD777', $gold['code_1xbet']);
        $this->assertSame('MEL555', $gold['code_melbet']);
        $this->assertNotNull($gold['valid_until']);
        $this->assertNotNull($gold['subscription_ends_at']);
    }

    public function test_an_expired_subscription_no_longer_unlocks_the_code(): void
    {
        $coupon = $this->goldCoupon();
        $user = $this->player();
        VipSubscription::create(['user_id' => $user->id, 'level' => 'Gold', 'starts_at' => now()->subDays(31), 'ends_at' => now()->subDay()]);

        Sanctum::actingAs($user);
        $payload = collect($this->getJson('/api/v1/pronostics')->json('pronostics'))->firstWhere('id', $coupon->id);
        $this->assertTrue($payload['locked']);
        $this->assertNull($payload['code_1xbet']);
    }

    public function test_publishing_a_vip_coupon_sends_the_code_to_subscribers_and_a_teaser_to_others(): void
    {
        $subscriber = $this->player('ExponentPushToken[gold]');
        $this->player('ExponentPushToken[free]');
        VipSubscription::create(['user_id' => $subscriber->id, 'level' => 'Gold', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);

        $this->goldCoupon();

        $toSubscriber = $this->pushesTo('ExponentPushToken[gold]');
        $toOther = $this->pushesTo('ExponentPushToken[free]');
        $this->assertCount(1, $toSubscriber);
        $this->assertCount(1, $toOther);
        $this->assertStringContainsString('code 1xBet est disponible', $toSubscriber[0]['body']);
        $this->assertStringContainsString('Rejoignez le VIP Gold', $toOther[0]['body']);
        $this->assertStringNotContainsString('GOLD777', json_encode(Http::recorded()->map(fn ($pair) => $pair[0]->data())));
    }

    public function test_the_app_variant_is_recorded_and_play_users_get_vip_messages_too(): void
    {
        $playUser = $this->player('ExponentPushToken[play]');
        Sanctum::actingAs($playUser);
        $this->putJson('/api/v1/auth/fcm-token', ['fcm_token' => 'ExponentPushToken[play]', 'app_variant' => 'play'])->assertOk();
        $this->player('ExponentPushToken[apk]');

        $this->goldCoupon();

        $this->assertSame('play', $playUser->fresh()->app_variant);
        $this->assertCount(1, $this->pushesTo('ExponentPushToken[play]'));
        $this->assertCount(1, $this->pushesTo('ExponentPushToken[apk]'));
    }

    public function test_reminders_are_sent_3_days_before_and_on_the_last_day_only_once(): void
    {
        $user = $this->player();
        VipSubscription::create(['user_id' => $user->id, 'level' => 'Gold', 'starts_at' => now()->subDays(28), 'ends_at' => now()->addDays(2)]);

        $this->artisan('binho:vip-reminders')->assertSuccessful();
        $this->artisan('binho:vip-reminders')->assertSuccessful();
        $this->assertCount(1, $this->pushesTo('ExponentPushToken[player]'));

        Carbon::setTestNow(now()->addDays(2)->addHour());
        $this->artisan('binho:vip-reminders')->assertSuccessful();
        $this->artisan('binho:vip-reminders')->assertSuccessful();

        $pushes = $this->pushesTo('ExponentPushToken[player]');
        $this->assertCount(2, $pushes);
        $this->assertStringContainsString('est terminé', $pushes[1]['title']);
    }

    public function test_the_admin_can_extend_or_stop_a_subscription(): void
    {
        $user = $this->player();
        $subscription = VipSubscription::create(['user_id' => $user->id, 'level' => 'Gold', 'starts_at' => now(), 'ends_at' => now()->addDays(5)]);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/vip/subscriptions/{$subscription->id}/extend")->assertSessionHas('success');
        $this->assertEqualsWithDelta(35, now()->diffInDays($subscription->fresh()->ends_at), 0.01);

        $this->actingAs($admin)->post("/admin/vip/subscriptions/{$subscription->id}/cancel")->assertSessionHas('success');
        $this->assertFalse($subscription->fresh()->isActive());
    }

    public function test_vip_results_count_every_vip_coupon_since_the_vip_launch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 12:00', 'Africa/Porto-Novo'));
        $coupon = $this->goldCoupon();
        $coupon->update(['result_status' => 'won']);
        $copy = fn (array $extra) => Pronostic::create([...$coupon->only(['niveau', 'couleur', 'cote_totale', 'match_date', 'match_time', 'team_home', 'team_away', 'prediction', 'odds', 'confidence', 'analysis', 'is_vip']), ...$extra]);
        // Coupon Gold de l'IA (ou de la page Pronostics) après le lancement : compté.
        $copy(['competition' => 'Combiné Gold', 'published_at' => now()->subDay(), 'result_status' => 'lost']);
        // Ancien combiné Gold d'avant le VIP payant : pas compté.
        $copy(['competition' => 'Combiné Gold', 'published_at' => Carbon::parse('2026-10-10 12:00'), 'result_status' => 'lost']);

        $this->getJson('/api/v1/settings/public')
            ->assertJsonPath('vip.stats.Gold.won', 1)
            ->assertJsonPath('vip.stats.Gold.settled', 2);
    }

    public function test_the_app_lists_the_user_subscriptions(): void
    {
        $user = $this->player();
        VipSubscription::create(['user_id' => $user->id, 'level' => 'Silver', 'starts_at' => now(), 'ends_at' => now()->addDays(12)]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/vip/me')
            ->assertOk()
            ->assertJsonPath('subscriptions.0.level', 'Silver')
            ->assertJsonPath('subscriptions.0.active', true);
    }

    public function test_the_ai_generates_all_five_groups_at_their_own_times(): void
    {
        $this->assertSame(['Starter', 'Bronze', 'Silver', 'Gold', 'Platinum'], \App\Services\AiPronosticGenerator::AI_LEVELS);
        $this->assertSame([
            'Starter' => ['09:00'],
            'Bronze' => ['16:30'],
            'Silver' => ['10:00', '15:00', '20:00'],
            'Gold' => ['09:00', '13:00', '18:00', '23:30'],
            'Platinum' => ['08:00', '12:00', '16:00', '21:00'],
        ], \App\Support\PronosticRules::schedule());

        $this->actingAs($this->admin())
            ->get('/admin/pronostics')
            ->assertOk()
            ->assertSee('id="lvlStarter"', false)
            ->assertSee('id="lvlSilver"', false)
            ->assertSee('id="lvlGold"', false)
            ->assertSee('id="lvlPlatinum"', false);
    }
}

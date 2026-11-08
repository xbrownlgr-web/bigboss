<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Pronostic;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAndSecurityTest extends TestCase
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
        return User::factory()->create(['email' => 'admin@bigboss.app', 'is_active' => true]);
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(['is_active' => true, ...$attributes]);
    }

    private function transaction(User $user, array $attributes = []): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id,
            'type' => 'recharge',
            'platform' => '1xbet',
            'player_id' => '123456',
            'amount' => 5000,
            'operator' => 'MTN MoMo',
            'reference_code' => 'REF-1',
            'status' => 'pending',
            ...$attributes,
        ]);
    }

    private function comboPayload(): array
    {
        return [
            'niveau' => 'Starter',
            'evenements' => [
                ['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Victoire Arsenal', 'cote' => 1.45],
            ],
        ];
    }

    public function test_a_regular_app_user_cannot_publish_a_pronostic(): void
    {
        $this->actingAs($this->member(), 'sanctum')
            ->postJson('/api/v1/pronostics', $this->comboPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('pronostics', 0);
    }

    public function test_the_admin_token_can_still_publish_a_pronostic(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/pronostics', $this->comboPayload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('pronostics', 1);
    }

    public function test_a_regular_app_user_cannot_set_a_pronostic_result(): void
    {
        $pronostic = Pronostic::create([
            'match_date' => now()->toDateString(),
            'match_time' => '20:00',
            'team_home' => 'A',
            'team_away' => 'B',
            'competition' => 'Test',
            'prediction' => '1',
            'odds' => 1.5,
            'confidence' => 70,
            'analysis' => 'Test',
            'published_at' => now(),
        ]);

        $this->actingAs($this->member(), 'sanctum')
            ->postJson("/api/v1/pronostics/{$pronostic->id}/result", ['result_status' => 'won'])
            ->assertForbidden();

        $this->assertNull($pronostic->fresh()->result_status);
    }

    public function test_admin_pages_render_for_the_admin(): void
    {
        $admin = $this->admin();
        $member = $this->member(['name' => 'Awa Diallo', 'country' => 'senegal']);
        $this->transaction($member);
        $this->transaction($member, ['type' => 'retrait', 'status' => 'completed', 'mobile_money_number' => '0700000000']);
        Message::create(['user_id' => $member->id, 'sender' => 'user', 'content' => 'Bonjour', 'is_read' => false]);

        foreach (['/admin', '/admin/transactions', '/admin/transactions?search=Awa', '/admin/users', "/admin/users/{$member->id}", '/admin/messages', '/admin/pronostics', '/admin/settings'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }

        $this->actingAs($admin)->get("/admin/users/{$member->id}")->assertSee('Sénégal');
    }

    public function test_admin_pages_are_forbidden_to_regular_users(): void
    {
        $this->actingAs($this->member())->get('/admin')->assertForbidden();
    }

    public function test_a_transaction_cannot_be_approved_twice(): void
    {
        $admin = $this->admin();
        $transaction = $this->transaction($this->member(['fcm_token' => 'ExponentPushToken[abc]']));

        $this->actingAs($admin)->post("/admin/transactions/{$transaction->id}/approve")->assertSessionHas('success');
        $this->actingAs($admin)->post("/admin/transactions/{$transaction->id}/approve")->assertSessionHas('error');
        $this->actingAs($admin)->post("/admin/transactions/{$transaction->id}/reject")->assertSessionHas('error');

        $this->assertSame('completed', $transaction->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_admin_can_take_a_transaction_in_charge_and_add_a_note(): void
    {
        $admin = $this->admin();
        $transaction = $this->transaction($this->member());

        $this->actingAs($admin)->post("/admin/transactions/{$transaction->id}/processing")->assertSessionHas('success');
        $this->actingAs($admin)->post("/admin/transactions/{$transaction->id}/note", ['admin_note' => 'Vérifié sur MoMo']);

        $transaction->refresh();
        $this->assertSame('processing', $transaction->status);
        $this->assertSame('Vérifié sur MoMo', $transaction->admin_note);
    }

    public function test_the_app_version_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/app/version')
            ->assertOk()
            ->assertJsonStructure(['latest_version', 'min_version', 'download_url', 'message']);
    }

    public function test_the_push_token_is_not_exposed_by_the_api(): void
    {
        $this->actingAs($this->member(['fcm_token' => 'ExponentPushToken[secret]']), 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonMissingPath('user.fcm_token');
    }

    public function test_push_notifications_are_sent_in_batches_of_one_hundred(): void
    {
        foreach (range(1, 150) as $index) {
            $this->member(['fcm_token' => "ExponentPushToken[{$index}]"]);
        }

        $sent = app(ExpoPushService::class)->sendToActiveUsers('Titre', 'Corps');

        $this->assertSame(150, $sent);
        Http::assertSentCount(2);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->member();

        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_the_admin_sees_why_a_test_push_was_refused(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['*' => Http::response(['data' => [[
            'status' => 'error',
            'message' => 'Unable to retrieve the FCM server key',
            'details' => ['error' => 'InvalidCredentials'],
        ]]])]);

        $user = $this->member(['fcm_token' => 'ExponentPushToken[abc]']);

        $this->actingAs($this->admin())
            ->post("/admin/users/{$user->id}/test-push")
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'InvalidCredentials'));
    }

    public function test_an_accepted_test_push_is_reported_as_such(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['*' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);

        $user = $this->member(['fcm_token' => 'ExponentPushToken[abc]']);

        $this->actingAs($this->admin())
            ->post("/admin/users/{$user->id}/test-push")
            ->assertSessionHas('success');
    }
}

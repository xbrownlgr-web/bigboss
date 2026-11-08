<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VipSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return [
            'name' => 'Koffi Mensah',
            'email' => 'koffi@example.com',
            'phone' => '0197000001',
            'country' => 'benin',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
            ...$overrides,
        ];
    }

    public function test_registration_records_the_18_plus_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registration(['adult_confirmed' => true]))->assertCreated();

        $this->assertNotNull(User::query()->where('email', 'koffi@example.com')->value('adult_confirmed_at'));
    }

    public function test_registration_is_refused_when_the_user_says_he_is_not_18(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registration(['adult_confirmed' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('adult_confirmed');
    }

    public function test_the_new_countries_can_register_and_unknown_ones_are_refused(): void
    {
        foreach (['niger', 'togo', 'gabon', 'rdc', 'cameroun'] as $index => $country) {
            $this->postJson('/api/v1/auth/register', $this->registration([
                'email' => "client{$index}@example.com",
                'country' => $country,
                'phone' => '+237 67 00 00 00 0',
            ]))->assertCreated();
        }

        $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'autre@example.com', 'country' => 'france']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('country');
    }

    public function test_older_app_versions_without_the_checkbox_can_still_register(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registration())->assertCreated();
    }

    private function userWithData(): User
    {
        $user = User::factory()->create([
            'email' => 'koffi@example.com',
            'password' => 'motdepasse1',
            'phone' => '0197000001',
            'is_active' => true,
            'fcm_token' => 'ExponentPushToken[abc]',
        ]);
        Message::create(['user_id' => $user->id, 'sender' => 'user', 'content' => 'Bonjour', 'is_read' => false]);
        VipSubscription::create(['user_id' => $user->id, 'level' => 'Gold', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        Transaction::create(['user_id' => $user->id, 'type' => 'recharge', 'platform' => '1xbet', 'player_id' => '123456', 'amount' => 5000, 'mobile_money_number' => '0197000001', 'operator' => 'mtn', 'status' => 'completed']);

        return $user;
    }

    public function test_the_user_deletes_his_account_from_the_app(): void
    {
        $user = $this->userWithData();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/delete-account', ['password' => 'mauvais'])
            ->assertUnprocessable();
        $this->assertNull($user->fresh()->account_deleted_at);

        $this->withToken($token)
            ->postJson('/api/v1/auth/delete-account', ['password' => 'motdepasse1'])
            ->assertOk();

        $deleted = $user->fresh();
        $this->assertNotNull($deleted->account_deleted_at);
        $this->assertSame('Compte supprimé', $deleted->name);
        $this->assertNull($deleted->phone);
        $this->assertNull($deleted->fcm_token);
        $this->assertFalse($deleted->is_active);
        $this->assertStringEndsWith('@bigboss.invalid', $deleted->email);
        $this->assertSame(0, $deleted->tokens()->count());
        $this->assertSame(0, Message::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, VipSubscription::query()->where('user_id', $user->id)->count());
        // Historique comptable conservé, sans les coordonnées du compte.
        $this->assertSame(1, Transaction::query()->where('user_id', $user->id)->count());

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/v1/auth/login', ['email' => 'koffi@example.com', 'password' => 'motdepasse1'])->assertUnauthorized();
        // L'adresse est libérée : on peut se réinscrire.
        $this->postJson('/api/v1/auth/register', $this->registration(['adult_confirmed' => true]))->assertCreated();
    }

    public function test_the_account_can_be_deleted_from_the_web_page(): void
    {
        $user = $this->userWithData();

        $this->get('/suppression-compte')->assertOk()->assertSee('Supprimer définitivement mon compte');

        $this->post('/suppression-compte', ['email' => 'koffi@example.com', 'password' => 'mauvais'])->assertSessionHasErrors('email');
        $this->assertNull($user->fresh()->account_deleted_at);

        $this->post('/suppression-compte', ['email' => 'koffi@example.com', 'password' => 'motdepasse1'])->assertSessionHas('deleted');
        $this->assertNotNull($user->fresh()->account_deleted_at);
    }

    public function test_the_privacy_policy_is_public(): void
    {
        $this->get('/confidentialite')
            ->assertOk()
            ->assertSee('Politique de confidentialité')
            ->assertSee('18 ans ou plus')
            ->assertSee('/suppression-compte');
    }
}

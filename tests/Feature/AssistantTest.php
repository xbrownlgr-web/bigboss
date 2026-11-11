<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\AssistantMessage;
use App\Models\DepositNumber;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ces tests vérifient l'assistant quand il est allumé (coupé par défaut depuis le 2026-11-11).
        AppSetting::setValue('assistant_enabled', 'oui');
    }

    private function customer(): User
    {
        return User::factory()->create(['name' => 'Kofi Mensah', 'country' => 'benin', 'is_active' => true]);
    }

    private function fakeOpenRouter(string $content): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => $content]]]]),
        ]);
    }

    public function test_the_assistant_answers_with_live_settings_and_a_guide(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        DepositNumber::create(['country' => 'benin', 'operator' => 'MTN MoMo', 'number' => '01 97 00 00 00', 'account_name' => 'JEAN DUPONT']);
        DepositNumber::create(['country' => 'cameroun', 'operator' => 'Orange Money', 'number' => '6 99 00 00 00', 'account_name' => 'AUTRE PAYS']);
        AppSetting::setValue('retrait_ville', 'Cotonou');
        AppSetting::setValue('retrait_point', 'Chez Boss17');
        AppSetting::setValue('assistant_instructions', 'Les recharges sont validées en 10 minutes.');
        $user = $this->customer();
        Transaction::create([
            'user_id' => $user->id,
            'type' => 'recharge',
            'platform' => '1xbet',
            'player_id' => '987654',
            'amount' => 5000,
            'operator' => 'MTN MoMo',
            'reference_code' => 'TX123',
            'status' => 'rejected',
            'rejection_reason' => 'ID de transaction introuvable',
        ]);
        $this->fakeOpenRouter("Bien sûr Kofi ! 1. Touchez **Recharger**.\n\n[[GUIDE:recharge]] [[GUIDE:inconnu]]");

        $this->actingAs($user)
            ->postJson('/api/v1/assistant/messages', ['content' => 'Comment recharger ?'])
            ->assertCreated()
            ->assertJsonPath('message.role', 'user')
            ->assertJsonPath('reply.role', 'assistant')
            ->assertJsonPath('reply.content', "Bien sûr Kofi ! 1. Touchez **Recharger**.")
            ->assertJsonPath('reply.guides', ['recharge'])
            ->assertJsonPath('reply.handoff', false);

        Http::assertSent(function (Request $request) {
            $system = $request['messages'][0]['content'];

            return $request['messages'][0]['role'] === 'system'
                && str_contains($system, '01 97 00 00 00')
                && str_contains($system, 'JEAN DUPONT')
                && ! str_contains($system, 'AUTRE PAYS')
                && str_contains($system, 'Cotonou')
                && str_contains($system, 'Les recharges sont validées en 10 minutes.')
                && str_contains($system, 'Prénom : Kofi')
                && str_contains($system, "Tu t'appelles LUC")
                && str_contains($system, 'Salut Kofi, je m\'appelle')
                && str_contains($system, 'ID de transaction introuvable')
                && collect($request['messages'])->last()['content'] === 'Comment recharger ?';
        });

        $this->assertSame(2, AssistantMessage::query()->where('user_id', $user->id)->count());
    }

    public function test_luc_says_when_recharging_is_not_yet_possible_in_the_client_country(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        DepositNumber::create(['country' => 'benin', 'operator' => 'MTN MoMo', 'number' => '01 97 00 00 00', 'account_name' => 'JEAN DUPONT']);
        $user = User::factory()->create(['name' => 'Paul Mbarga', 'country' => 'cameroun', 'is_active' => true]);
        $this->fakeOpenRouter('Pas encore au Cameroun. [[CONSEILLER]]');

        $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['content' => 'Comment recharger ?'])->assertCreated();

        Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], 'pays du client (Cameroun) : aucun pour l’instant')
            && ! str_contains($request['messages'][0]['content'], '01 97 00 00 00')
            && str_contains($request['messages'][0]['content'], 'MTN MoMo, Orange Money'));
    }

    public function test_the_conversation_history_is_sent_back_with_markers(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        $user = $this->customer();
        AssistantMessage::create(['user_id' => $user->id, 'role' => 'user', 'content' => 'Je veux retirer']);
        AssistantMessage::create(['user_id' => $user->id, 'role' => 'assistant', 'content' => 'Voici comment faire.', 'guides' => ['retrait']]);
        $this->fakeOpenRouter('Je vous passe un conseiller. [[CONSEILLER]]');

        $this->actingAs($user)
            ->postJson('/api/v1/assistant/messages', ['content' => 'Je veux parler à quelqu’un'])
            ->assertCreated()
            ->assertJsonPath('reply.content', 'Je vous passe un conseiller.')
            ->assertJsonPath('reply.handoff', true)
            ->assertJsonPath('reply.guides', null);

        Http::assertSent(fn (Request $request) => count($request['messages']) === 4
            && $request['messages'][2]['content'] === "Voici comment faire.\n[[GUIDE:retrait]]");
    }

    public function test_without_ai_key_the_assistant_offers_a_human_advisor(): void
    {
        Http::fake();
        $user = $this->customer();

        $this->actingAs($user)
            ->postJson('/api/v1/assistant/messages', ['content' => 'Bonjour'])
            ->assertCreated()
            ->assertJsonPath('reply.handoff', true);

        Http::assertNothingSent();
    }

    public function test_a_failing_ai_provider_falls_back_to_the_advisor(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'Crédit insuffisant']], 402)]);

        $this->actingAs($this->customer())
            ->postJson('/api/v1/assistant/messages', ['content' => 'Bonjour'])
            ->assertCreated()
            ->assertJsonPath('reply.handoff', true);
    }

    public function test_the_daily_limit_stops_the_assistant(): void
    {
        Http::fake();
        AppSetting::setValue('assistant_daily_limit', '2');
        $user = $this->customer();

        foreach ([1, 2] as $_) {
            $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['content' => 'Salut'])->assertCreated();
        }

        $this->actingAs($user)
            ->postJson('/api/v1/assistant/messages', ['content' => 'Salut'])
            ->assertStatus(429);
    }

    public function test_each_customer_only_sees_and_clears_their_own_conversation(): void
    {
        $user = $this->customer();
        $other = $this->customer();
        AssistantMessage::create(['user_id' => $user->id, 'role' => 'user', 'content' => 'Ma question']);
        AssistantMessage::create(['user_id' => $other->id, 'role' => 'user', 'content' => 'Question privée']);

        $this->actingAs($user)
            ->getJson('/api/v1/assistant/messages')
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', 'Ma question');

        $this->actingAs($user)->deleteJson('/api/v1/assistant/messages')->assertOk();

        $this->assertSame(0, AssistantMessage::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, AssistantMessage::query()->where('user_id', $other->id)->count());
    }

    public function test_deleting_an_account_erases_the_assistant_conversation(): void
    {
        $user = $this->customer();
        AssistantMessage::create(['user_id' => $user->id, 'role' => 'user', 'content' => 'Mon numéro est 0197000000']);

        app(AccountDeletionService::class)->delete($user);

        $this->assertSame(0, AssistantMessage::query()->where('user_id', $user->id)->count());
    }

    public function test_guests_cannot_use_the_assistant(): void
    {
        $this->postJson('/api/v1/assistant/messages', ['content' => 'Bonjour'])->assertUnauthorized();
    }

    public function test_the_admin_can_test_luc_and_sees_the_exact_failure(): void
    {
        $admin = User::factory()->create(['email' => 'admin@bigboss.app', 'is_active' => true]);

        // Réponses successives d'OpenRouter : crédit épuisé, puis une vraie réponse.
        Http::fake(['openrouter.ai/*' => Http::sequence()
            ->push(['error' => ['message' => 'Insufficient credits']], 402)
            ->push(['choices' => [['message' => ['content' => 'Salut ! Je m’appelle LUC. Touchez **Recharger**. [[GUIDE:recharge]]']]]])]);

        $this->actingAs($admin)->post('/admin/settings/test-assistant')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Clé OpenRouter absente'));

        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        $this->actingAs($admin)->post('/admin/settings/test-assistant')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Crédit OpenRouter épuisé'));

        $this->actingAs($admin)->post('/admin/settings/test-assistant')
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'LUC répond bien') && ! str_contains($message, '[[GUIDE'));
    }

    public function test_a_client_side_failure_is_shown_to_the_admin(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'Insufficient credits']], 402)]);

        $this->actingAs($this->customer())->postJson('/api/v1/assistant/messages', ['content' => 'Bonjour'])->assertCreated();

        $admin = User::factory()->create(['email' => 'admin@bigboss.app', 'is_active' => true]);
        $this->actingAs($admin)->get('/admin/settings')->assertSee('Crédit OpenRouter épuisé', false);
    }

    public function test_the_admin_settings_page_shows_the_assistant_section(): void
    {
        Http::fake();
        $admin = User::factory()->create(['email' => 'admin@bigboss.app', 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('LUC, l’assistant 24h/24 (onglet Support)', false)
            ->assertSee('Informations pour l’assistant', false);
    }
}

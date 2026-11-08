<?php

namespace Tests\Feature;

use App\Mail\AdminAlert;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminEmailAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::fake(['*' => Http::response(['data' => []])]);
    }

    private function member(): User
    {
        return User::factory()->create(['name' => 'Awa', 'phone' => '0197000000', 'is_active' => true]);
    }

    public function test_the_admin_is_emailed_for_each_new_recharge_and_withdrawal(): void
    {
        AppSetting::setValue('admin_email', 'patron@gmail.com, associe@gmail.com');
        $member = $this->member();

        $this->actingAs($member, 'sanctum')->postJson('/api/v1/transactions', [
            'type' => 'recharge',
            'platform' => '1xbet',
            'player_id' => '123456789',
            'amount' => 5000,
            'operator' => 'MTN MoMo',
            'reference_code' => '7845123690',
        ])->assertCreated();

        $this->actingAs($member, 'sanctum')->postJson('/api/v1/transactions', [
            'type' => 'retrait',
            'platform' => 'melbet',
            'player_id' => '987654321',
            'amount' => 3000,
            'withdrawal_code' => 'AB12',
            'operator' => 'Moov Money',
            'mobile_money_number' => '+229 01 60 00 00 00',
        ])->assertCreated()->assertJsonPath('transaction.player_id', '987654321');

        Mail::assertSent(AdminAlert::class, 2);
        Mail::assertSent(AdminAlert::class, fn (AdminAlert $mail) => $mail->hasTo('patron@gmail.com')
            && $mail->hasTo('associe@gmail.com')
            && ($mail->details['ID joueur à recharger'] ?? null) === '123456789'
            && ($mail->details['ID de la transaction Mobile Money'] ?? null) === '7845123690');
        Mail::assertSent(AdminAlert::class, fn (AdminAlert $mail) => ($mail->details['Numéro Mobile Money à payer'] ?? null) === '+229 01 60 00 00 00'
            && ($mail->details['ID du compte joueur'] ?? null) === '987654321');
    }

    public function test_the_placeholder_address_never_receives_alerts(): void
    {
        // Valeur d'origine du réglage : une boîte qui n'existe pas (jamais d'adresse Binho Flash).
        $this->actingAs($this->member(), 'sanctum')->postJson('/api/v1/transactions', [
            'type' => 'recharge', 'platform' => '1xbet', 'player_id' => '1', 'amount' => 500, 'reference_code' => 'X1234',
        ])->assertCreated();

        Mail::assertNotSent(AdminAlert::class);
    }

    public function test_one_email_per_support_conversation_until_the_admin_reads_it(): void
    {
        AppSetting::setValue('admin_email', 'patron@gmail.com');
        $member = $this->member();

        $this->actingAs($member, 'sanctum')->postJson('/api/v1/messages', ['content' => 'Bonjour'])->assertCreated();
        $this->actingAs($member, 'sanctum')->postJson('/api/v1/messages', ['content' => 'Vous êtes là ?'])->assertCreated();

        Mail::assertSent(AdminAlert::class, 1);
    }

    public function test_the_admin_can_send_a_test_email(): void
    {
        $admin = User::factory()->create(['email' => 'admin@bigboss.app', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/settings/test-email')->assertSessionHas('error');

        AppSetting::setValue('admin_email', 'patron@gmail.com');
        $this->actingAs($admin)->post('/admin/settings/test-email')->assertSessionHas('success');

        Mail::assertSent(AdminAlert::class, fn (AdminAlert $mail) => $mail->hasTo('patron@gmail.com'));
    }

    public function test_a_withdrawal_from_an_older_app_without_player_id_is_still_accepted(): void
    {
        $this->actingAs($this->member(), 'sanctum')->postJson('/api/v1/transactions', [
            'type' => 'retrait', 'platform' => '1xbet', 'amount' => 2000, 'withdrawal_code' => 'ZX99',
            'operator' => 'MTN MoMo', 'mobile_money_number' => '0166038451',
        ])->assertCreated();
    }
}

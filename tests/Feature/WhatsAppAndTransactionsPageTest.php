<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WhatsAppAndTransactionsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@binhoflash.com', 'is_active' => true]);
    }

    public function test_any_phone_format_becomes_international(): void
    {
        $this->assertSame('+2290197000000', WhatsAppNotifier::normalizePhone('+229 01 97 00 00 00'));
        $this->assertSame('+2290197000000', WhatsAppNotifier::normalizePhone('00229 01-97-00-00-00'));
        $this->assertSame('+2290197000000', WhatsAppNotifier::normalizePhone('2290197000000'));
        $this->assertNull(WhatsAppNotifier::normalizePhone(''));
    }

    public function test_the_plus_sign_is_encoded_and_success_is_detected(): void
    {
        AppSetting::setValue('callmebot_phone', '+229 01 97 00 00 00');
        AppSetting::setValue('callmebot_apikey', '123456');
        Http::fake(['api.callmebot.com/*' => Http::response('Message queued. You will receive it in a few seconds.')]);

        $this->actingAs($this->admin())->post('/admin/settings/test-whatsapp')->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'phone=%2B2290197000000')
            && str_contains($request->url(), 'apikey=123456'));
    }

    public function test_a_callmebot_refusal_is_shown_to_the_admin(): void
    {
        AppSetting::setValue('callmebot_phone', '+2290197000000');
        AppSetting::setValue('callmebot_apikey', 'mauvaise');
        Http::fake(['api.callmebot.com/*' => Http::response('<p>APIKey is invalid. Please check it.</p>')]);

        $this->actingAs($this->admin())
            ->post('/admin/settings/test-whatsapp')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'APIKey is invalid'));
    }

    public function test_the_transactions_page_shows_the_approval_checklist_data(): void
    {
        Mail::fake();
        Http::fake();
        $client = User::factory()->create(['name' => 'Awa', 'phone' => '0197000000']);
        Transaction::create([
            'user_id' => $client->id,
            'type' => 'recharge',
            'platform' => '1xbet',
            'player_id' => '123456789',
            'amount' => 5000,
            'operator' => 'MTN MoMo',
            'reference_code' => '7845123690',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/transactions')
            ->assertOk()
            ->assertSee('id="approveModal"', false)
            ->assertSee('id="bfConfirmModal"', false)
            ->assertSee('7845123690')
            ->assertDontSee('window.confirm', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppCloudTransactionAlertTest extends TestCase
{
    use RefreshDatabase;

    private function configureCloud(): void
    {
        Config::set('services.whatsapp_cloud', [
            'version' => 'v21.0',
            'phone_number_id' => '1234567890',
            'token' => 'cloud-token-secret',
            'template' => 'nouvelle_demande',
            'admin_number' => '+2290197000000',
        ]);
    }

    private function createTransaction(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Awa', 'is_active' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transactions', [
            'type' => 'recharge',
            'platform' => '1xbet',
            'player_id' => '123456789',
            'amount' => 1000,
            'operator' => 'MTN MoMo',
            'reference_code' => '7845123690',
        ])->assertCreated();
    }

    public function test_afterresponse_dispatch_actually_runs_within_a_feature_test(): void
    {
        $this->configureCloud();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]])]);

        $this->createTransaction();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'graph.facebook.com')
            && $request->hasHeader('Authorization', 'Bearer cloud-token-secret')
            && $request['template']['name'] === 'nouvelle_demande'
            && $request['template']['components'][0]['parameters'][0]['text'] === 'recharge'
            && $request['template']['components'][0]['parameters'][1]['text'] === '1 000'
            && $request['template']['components'][0]['parameters'][2]['text'] === 'MTN MoMo'
            && $request['template']['components'][0]['parameters'][3]['text'] === 'Awa');
    }

    public function test_cloud_failure_falls_back_to_callmebot_automatically(): void
    {
        $this->configureCloud();
        AppSetting::setValue('callmebot_phone', '+2290197000000');
        AppSetting::setValue('callmebot_apikey', '123456');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter']], 400),
            'api.callmebot.com/*' => Http::response('Message queued. You will receive it in a few seconds.'),
        ]);

        $this->createTransaction();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'graph.facebook.com'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.callmebot.com')
            && str_contains($request->url(), 'NOUVELLE%20RECHARGE'));
    }

    public function test_a_server_error_is_retried_up_to_three_attempts_total(): void
    {
        $this->configureCloud();

        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'temp down']], 503)
                ->push(['error' => ['message' => 'temp down']], 503)
                ->push(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $this->createTransaction();

        Http::assertSentCount(3);
    }

    public function test_when_both_cloud_and_callmebot_are_unreachable_the_request_still_succeeds(): void
    {
        $this->configureCloud();
        AppSetting::setValue('callmebot_phone', '+2290197000000');
        AppSetting::setValue('callmebot_apikey', '123456');

        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);

        // N'importe quelle exception dans l'alerte ne doit jamais faire échouer la création.
        $this->createTransaction();
        $this->assertDatabaseCount('transactions', 1);
    }
}

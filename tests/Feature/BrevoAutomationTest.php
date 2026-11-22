<?php

namespace Tests\Feature;

use App\Mail\BigBossAutoMail;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\VipSubscription;
use App\Services\BrevoAutomationService;
use App\Services\VipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrevoAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@bigboss.app', 'is_active' => true]);
    }

    public function test_daily_brevo_sequence_sends_j1_and_j3_pack_promo_once_per_user(): void
    {
        Mail::fake();

        AppSetting::setValue('vip_bronze_price', '3000');
        AppSetting::setValue('vip_silver_price', '5000');
        AppSetting::setValue('vip_gold_price', '10000');
        AppSetting::setValue('vip_platinum_price', '15000');
        AppSetting::setValue('vip_score_price', '5000');
        AppSetting::setValue('vip_grossescotes_price', '5000');
        AppSetting::setValue('vip_cartons_price', '3500');
        AppSetting::setValue('vip_buteurs_price', '3500');
        AppSetting::setValue('vip_complet_price', '25000');

        $j1User = User::factory()->create(['is_active' => true, 'email' => 'nouveau@example.com']);
        $j1User->forceFill(['created_at' => now()->subHours(30)])->save();

        $j3User = User::factory()->create(['is_active' => true, 'email' => 'prospect@example.com']);
        $j3User->forceFill(['created_at' => now()->subDays(4)])->save();

        // Un abonné Pack complet ne doit PAS recevoir la relance Pack VIP J+3.
        $packUser = User::factory()->create(['is_active' => true, 'email' => 'dejavip@example.com']);
        $packUser->forceFill(['created_at' => now()->subDays(4)])->save();
        VipSubscription::create([
            'user_id' => $packUser->id,
            'level' => AppSetting::ALL_VIP_LEVEL,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);

        $this->artisan('bigboss:send-auto-emails')->assertSuccessful();

        Mail::assertSent(BigBossAutoMail::class, fn (BigBossAutoMail $mail) => $mail->hasTo('nouveau@example.com'));
        Mail::assertSent(BigBossAutoMail::class, fn (BigBossAutoMail $mail) => $mail->hasTo('prospect@example.com')
            && str_contains($mail->mailSubject, '-50 %'));
        Mail::assertNotSent(BigBossAutoMail::class, fn (BigBossAutoMail $mail) => $mail->hasTo('dejavip@example.com'));

        // Relancer la commande le même jour ne renvoie aucun doublon.
        Mail::fake();
        $counts = app(BrevoAutomationService::class)->runDailySequence();
        $this->assertSame(['welcome_j1' => 0, 'pack_promo_j3' => 0], $counts);
        Mail::assertNothingSent();
    }

    public function test_vip_reminders_also_send_brevo_emails(): void
    {
        Mail::fake();
        Http::fake();

        $member = User::factory()->create(['is_active' => true, 'email' => 'vip@example.com']);
        VipSubscription::create([
            'user_id' => $member->id,
            'level' => 'Gold',
            'starts_at' => now()->subDays(28),
            'ends_at' => now()->addDays(2),
        ]);

        $sent = app(VipService::class)->sendReminders();
        $this->assertSame(1, $sent['soon']);
        Mail::assertSent(BigBossAutoMail::class, fn (BigBossAutoMail $mail) => $mail->hasTo('vip@example.com'));
    }

    public function test_admin_can_broadcast_brevo_email_from_the_app(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $vip = User::factory()->create(['is_active' => true, 'email' => 'abonne@example.com']);
        $free = User::factory()->create(['is_active' => true, 'email' => 'gratuit@example.com']);
        VipSubscription::create([
            'user_id' => $vip->id,
            'level' => 'Silver',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ]);

        Sanctum::actingAs($free);
        $this->postJson('/api/v1/admin/email/broadcast', [
            'subject' => 'Test',
            'body' => 'Message test interdit',
        ])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/email/broadcast', [
            'subject' => 'Grosse soirée Ligue des Champions',
            'body' => 'Tous les coupons VIP de ce soir sont publiés sur BIG BOSS.',
            'target' => 'vip',
        ])->assertOk()->assertJsonPath('sent', 1);

        Mail::assertSent(BigBossAutoMail::class, fn (BigBossAutoMail $mail) => $mail->hasTo('abonne@example.com'));
        Mail::assertNotSent(BigBossAutoMail::class, fn (BigBossAutoMail $mail) => $mail->hasTo('gratuit@example.com'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\VipSubscription;
use App\Services\AiPronosticGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PronosticNotificationScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_each_pronostic_is_notified_two_minutes_after_the_previous_one(): void
    {
        $this->fakeGeneration();
        User::factory()->create(['is_active' => true, 'fcm_token' => 'ExponentPushToken[abc]']);

        // La génération de 08:00 dure quelques secondes (IA) : la première notification part tout de suite.
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:00:25', 'Africa/Porto-Novo'));
        $this->artisan('binho:generate-pronostics', ['--level' => ['Starter', 'Bronze', 'Silver', 'Gold']])->assertSuccessful();
        $this->assertSame(1, $this->pushCount());

        // Le planificateur passe ensuite chaque minute pile.
        $expected = [
            '08:01:00' => 1,
            '08:02:00' => 2,
            '08:03:00' => 2,
            '08:04:00' => 3,
            '08:05:00' => 3,
            '08:06:00' => 4,
            '08:07:00' => 4,
            '08:10:00' => 4,
        ];

        foreach ($expected as $time => $count) {
            Carbon::setTestNow(Carbon::parse("2026-10-14 {$time}", 'Africa/Porto-Novo'));
            $this->artisan('binho:send-pronostic-notification')->assertSuccessful();
            $this->assertSame($count, $this->pushCount(), "Nombre d'envois à {$time}");
        }

        $niveaux = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'exp.host'))
            ->map(fn ($pair) => $pair[0]->data()[0]['data']['niveau'])
            ->values()
            ->all();
        $this->assertSame(['Starter', 'Bronze', 'Silver', 'Gold'], $niveaux);
    }

    public function test_a_late_scheduler_never_sends_several_notifications_at_once(): void
    {
        $this->fakeGeneration();
        User::factory()->create(['is_active' => true, 'fcm_token' => 'ExponentPushToken[abc]']);

        Carbon::setTestNow(Carbon::parse('2026-10-14 18:00:10', 'Africa/Porto-Novo'));
        $this->artisan('binho:generate-pronostics', ['--level' => ['Starter', 'Bronze', 'Silver']])->assertSuccessful();

        // Serveur indisponible pendant 20 minutes : toutes les notifications sont dues,
        // mais une seule part par passage, toujours espacées de deux minutes.
        Carbon::setTestNow(Carbon::parse('2026-10-14 18:20:00', 'Africa/Porto-Novo'));
        $this->artisan('binho:send-pronostic-notification')->assertSuccessful();
        $this->artisan('binho:send-pronostic-notification')->assertSuccessful();
        $this->assertSame(2, $this->pushCount());

        Carbon::setTestNow(Carbon::parse('2026-10-14 18:21:00', 'Africa/Porto-Novo'));
        $this->artisan('binho:send-pronostic-notification')->assertSuccessful();
        $this->assertSame(2, $this->pushCount());

        Carbon::setTestNow(Carbon::parse('2026-10-14 18:22:00', 'Africa/Porto-Novo'));
        $this->artisan('binho:send-pronostic-notification')->assertSuccessful();
        $this->assertSame(3, $this->pushCount());
    }

    public function test_non_subscribers_only_get_a_few_vip_announcements_per_day(): void
    {
        $this->fakeGeneration();
        $subscriber = User::factory()->create(['is_active' => true, 'fcm_token' => 'ExponentPushToken[platinum]']);
        User::factory()->create(['is_active' => true, 'fcm_token' => 'ExponentPushToken[free]']);
        VipSubscription::create(['user_id' => $subscriber->id, 'level' => 'Platinum', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);

        Carbon::setTestNow(Carbon::parse('2026-10-14 08:00:00', 'Africa/Porto-Novo'));
        $this->artisan('binho:generate-pronostics', ['--level' => ['Silver', 'Gold', 'Platinum']])->assertSuccessful();

        foreach (['08:02:00', '08:04:00'] as $time) {
            Carbon::setTestNow(Carbon::parse("2026-10-14 {$time}", 'Africa/Porto-Novo'));
            $this->artisan('binho:send-pronostic-notification')->assertSuccessful();
        }

        // Trois coupons VIP : deux annonces seulement (réglage ai_vip_teasers_per_day), l'abonné
        // Platinum reçoit quand même le sien.
        $this->assertSame(['Silver', 'Gold'], $this->levelsSentTo('ExponentPushToken[free]'));
        $this->assertContains('Platinum', $this->levelsSentTo('ExponentPushToken[platinum]'));
    }

    /**
     * @return array<int, string>
     */
    private function levelsSentTo(string $token): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'exp.host'))
            ->flatMap(fn ($pair) => $pair[0]->data())
            ->filter(fn ($message) => $message['to'] === $token)
            ->map(fn ($message) => $message['data']['niveau'])
            ->values()
            ->all();
    }

    private function pushCount(): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'exp.host'))->count();
    }

    /**
     * Génération simulée : le coupon du niveau demandé, publié tel quel par la commande.
     */
    private function fakeGeneration(): void
    {
        AppSetting::setValue('highlightly_api_key', 'highlightly-test');

        $this->mock(AiPronosticGenerator::class)
            ->shouldReceive('generate')
            ->andReturnUsing(fn ($fixtures, $levels) => array_map(fn ($level) => [
                'niveau' => $level,
                'evenements' => [['match' => "Match {$level}", 'pronostic' => 'Double chance 1X', 'cote' => 1.45]],
                'cote_totale' => 2.1,
                'probabilite' => 64,
                'analysis' => 'Analyse test.',
            ], $levels));

        Http::fake([
            'soccer.highlightly.net/matches*' => Http::response(['data' => [
                $this->fixture(33973, 'Arsenal', 'Chelsea'),
                $this->fixture(52695, 'PSG', 'Lyon'),
            ]]),
            'exp.host/*' => Http::response(['data' => [['status' => 'ok']]]),
            '*' => Http::response(['data' => []]),
        ]);
    }

    private function fixture(int $leagueId, string $home, string $away): array
    {
        return [
            'date' => Carbon::parse('2026-10-14 20:00', 'Africa/Porto-Novo')->toIso8601String(),
            'league' => ['id' => $leagueId],
            'homeTeam' => ['name' => $home],
            'awayTeam' => ['name' => $away],
            'state' => ['description' => 'Not started'],
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\DepositNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DepositNumbersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@bigboss.app', 'is_active' => true]);
    }

    private function number(string $country, string $operator, string $number, bool $active = true): DepositNumber
    {
        return DepositNumber::create([
            'country' => $country,
            'operator' => $operator,
            'number' => $number,
            'account_name' => 'JEAN DUPONT',
            'is_active' => $active,
        ]);
    }

    public function test_each_country_gets_its_own_active_numbers_and_old_apps_keep_benin(): void
    {
        $this->number('benin', 'MTN MoMo', '01 97 00 00 00');
        $this->number('benin', 'Moov Money', '01 60 00 00 00', active: false);
        $this->number('cameroun', 'Orange Money', '6 99 00 00 00');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            // Anciennes versions de l'application : numéros du Bénin uniquement.
            ->assertJsonCount(1, 'mobile_money_operators')
            ->assertJsonPath('mobile_money_operators.0.nom', 'MTN MoMo')
            ->assertJsonPath('mobile_money_operators.0.numero', '01 97 00 00 00')
            ->assertJsonPath('mobile_money_operators.0.nom_compte', 'JEAN DUPONT')
            ->assertJsonPath('mobile_money_operators.0.numero_marchand', '01 97 00 00 00')
            // Nouvelle version : chaque pays a ses numéros, les numéros masqués n'apparaissent pas.
            ->assertJsonCount(1, 'deposit_operators.benin')
            ->assertJsonPath('deposit_operators.cameroun.0.nom', 'Orange Money')
            ->assertJsonPath('deposit_operators.cameroun.0.couleur', '#FF7900')
            ->assertJsonMissingPath('deposit_operators.togo');
    }

    public function test_the_admin_adds_edits_hides_and_deletes_a_number(): void
    {
        Http::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/depots')->assertOk()->assertSee('Recharge indisponible')->assertSee('RD Congo');

        $this->actingAs($admin)->post('/admin/depots', [
            'country' => 'togo',
            'operator' => 'Flooz (Moov)',
            'number' => '+228 90 00 00 00',
            'account_name' => 'KOFI AGBO',
        ])->assertRedirect('/admin/depots#togo');

        $number = DepositNumber::query()->where('country', 'togo')->firstOrFail();
        $this->assertTrue($number->is_active);
        $this->getJson('/api/v1/settings/public')->assertJsonPath('deposit_operators.togo.0.numero', '+228 90 00 00 00');

        $this->actingAs($admin)->post("/admin/depots/{$number->id}", [
            'country' => 'togo', 'operator' => 'Mixx (T-Money)', 'number' => '+228 70 00 00 00', 'account_name' => 'KOFI AGBO',
        ]);
        $this->assertSame('Mixx (T-Money)', $number->fresh()->operator);

        $this->actingAs($admin)->post("/admin/depots/{$number->id}/toggle");
        $this->assertFalse($number->fresh()->is_active);
        $this->getJson('/api/v1/settings/public')->assertJsonMissingPath('deposit_operators.togo');

        $this->actingAs($admin)->post("/admin/depots/{$number->id}/delete");
        $this->assertSame(0, DepositNumber::query()->count());
    }

    public function test_a_number_needs_the_name_shown_during_the_transfer_and_a_known_country(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/depots', ['country' => 'france', 'operator' => 'Inconnu', 'number' => 'abc', 'account_name' => ''])
            ->assertSessionHasErrors(['country', 'operator', 'number', 'account_name']);

        $this->assertSame(0, DepositNumber::query()->count());
    }

    public function test_the_former_benin_settings_are_moved_into_the_table(): void
    {
        $now = now();
        DB::table('app_settings')->insert([
            ['group' => 'mobile_money', 'key' => 'mtn_momo_number', 'label' => 'MTN', 'value' => '0150742012', 'type' => 'text', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mobile_money', 'key' => 'mtn_momo_name', 'label' => 'MTN', 'value' => 'ABDOUL', 'type' => 'text', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mobile_money', 'key' => 'celtiis_number', 'label' => 'Celtiis', 'value' => '', 'type' => 'text', 'created_at' => $now, 'updated_at' => $now],
        ]);

        (require database_path('migrations/2026_10_19_000000_create_deposit_numbers_table.php'))->up();

        $this->assertSame(1, DepositNumber::query()->count());
        $moved = DepositNumber::query()->firstOrFail();
        $this->assertSame(['benin', 'MTN MoMo', '0150742012', 'ABDOUL'], [$moved->country, $moved->operator, $moved->number, $moved->account_name]);
        $this->assertFalse(DB::table('app_settings')->whereIn('key', ['mtn_momo_number', 'mtn_momo_name', 'celtiis_number'])->exists());
    }

    public function test_the_dashboard_warns_when_no_deposit_number_is_set(): void
    {
        Http::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin')->assertSee('Aucun numéro de dépôt Mobile Money');

        $this->number('benin', 'Moov Money', '01 60 00 00 00');

        $this->actingAs($admin)->get('/admin')->assertDontSee('Aucun numéro de dépôt Mobile Money');
    }

    public function test_the_withdrawal_point_is_sent_to_the_app(): void
    {
        AppSetting::setValue('retrait_ville', 'Cotonou');
        AppSetting::setValue('retrait_point', 'BIG BOSS Akpakpa');

        $this->getJson('/api/v1/settings/public')
            ->assertJsonPath('retrait.ville', 'Cotonou')
            ->assertJsonPath('retrait.point', 'BIG BOSS Akpakpa');
    }

    public function test_the_binho_withdrawal_address_is_set_by_default(): void
    {
        // Posée par la migration : Cotonou, « BIG BOSS ».
        $this->getJson('/api/v1/settings/public')
            ->assertJsonPath('retrait.ville', 'Cotonou')
            ->assertJsonPath('retrait.point', 'BIG BOSS');
    }
}

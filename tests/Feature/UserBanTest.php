<?php

namespace Tests\Feature;

use App\Models\BannedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserBanTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'email' => config('app.admin_email'),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_ban_user_revoke_tokens_and_block_device_from_reregistering(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();

        // 1. L'utilisateur s'inscrit depuis son téléphone (avec X-Device-Id)
        $reg = $this->withHeaders(['X-Device-Id' => 'bb_dev_phone_xyz123'])
            ->postJson('/api/v1/auth/register', [
                'name' => 'Fauteur Trouble',
                'email' => 'trouble@example.com',
                'phone' => '+229 01 97 11 22 33',
                'country' => 'benin',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'adult_confirmed' => true,
            ])
            ->assertCreated();

        $userId = $reg->json('user.id');
        $userToken = $reg->json('token');

        // 2. L'admin le recherche et le bannit depuis l'application
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/users?search=Fauteur')
            ->assertOk()
            ->assertJsonPath('users.0.id', $userId)
            ->assertJsonPath('users.0.is_active', true);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$userId}/ban", ['reason' => 'Spam support'])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        // 3. Son jeton actif est immédiatement révoqué
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $userId]);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $userToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        // 4. Impossible de créer un NOUVEAU compte depuis le même téléphone (même avec autre e-mail et autre numéro)
        $this->withHeaders(['X-Device-Id' => 'bb_dev_phone_xyz123'])
            ->postJson('/api/v1/auth/register', [
                'name' => 'Nouveau Pseudo',
                'email' => 'autre@example.com',
                'phone' => '+229 01 66 55 44 33',
                'country' => 'benin',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'adult_confirmed' => true,
            ])
            ->assertForbidden();

        // 5. Impossible de créer un compte depuis un autre appareil avec le même numéro (même formaté différemment)
        $this->withHeaders(['X-Device-Id' => 'bb_dev_other_phone999'])
            ->postJson('/api/v1/auth/register', [
                'name' => 'Autre Telephone',
                'email' => 'autre2@example.com',
                'phone' => '97 11 22 33',
                'country' => 'benin',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'adult_confirmed' => true,
            ])
            ->assertForbidden();

        // 6. Impossible de se reconnecter avec son compte
        $this->withHeaders(['X-Device-Id' => 'bb_dev_phone_xyz123'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'trouble@example.com',
                'password' => 'Password123',
            ])
            ->assertForbidden();

        // 7. L'admin peut le débannir → tout redevient autorisé
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$userId}/unban")
            ->assertOk()
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseCount('banned_devices', 0);

        $this->withHeaders(['X-Device-Id' => 'bb_dev_phone_xyz123'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'trouble@example.com',
                'password' => 'Password123',
            ])
            ->assertOk();
    }

    public function test_admin_cannot_ban_another_admin_and_non_admin_is_forbidden(): void
    {
        $admin = $this->createAdmin();
        $member = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$admin->id}/ban")
            ->assertUnprocessable();

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/admin/users/{$member->id}/ban")
            ->assertForbidden();

        $this->assertFalse(BannedDevice::isBlocked('bb_dev_clean_123456'));
    }
}

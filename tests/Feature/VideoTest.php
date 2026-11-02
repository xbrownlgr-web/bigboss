<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\VideoController;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@binhoflash.com', 'is_active' => true]);
    }

    public function test_the_signature_matches_cloudinary_documentation_example(): void
    {
        // Exemple de la documentation Cloudinary (Authentication signatures).
        $signature = VideoController::sign([
            'eager' => 'w_400,h_300,c_pad|w_260,h_200,c_crop',
            'public_id' => 'sample_image',
            'timestamp' => '1315060510',
        ], 'abcd');

        $this->assertSame('bfd09f95f331f558cbd1320e67aa8d488770583e', $signature);
    }

    public function test_the_admin_saves_a_video_and_the_app_receives_it(): void
    {
        $url = 'https://res.cloudinary.com/demo/video/upload/f_mp4,vc_h264,q_auto/v1/binhoflash/tutoriels/recharge.mp4';

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/videos')->assertOk()->assertSee('Comment recharger');
        $this->actingAs($admin)->post('/admin/videos/recharge', ['url' => $url])->assertSessionHas('success');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('videos.recharge', $url)
            ->assertJsonPath('videos.retrait', null);

        $this->actingAs($admin)->post('/admin/videos/recharge/delete')->assertSessionHas('success');
        $this->getJson('/api/v1/settings/public')->assertJsonPath('videos.recharge', null);
    }

    public function test_only_https_links_are_accepted(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/videos/retrait', ['url' => 'http://exemple.com/video.mp4'])
            ->assertSessionHasErrors('url');
    }

    public function test_signature_requires_cloudinary_settings_then_works(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/admin/videos/signature')->assertStatus(422);

        AppSetting::setValue('cloudinary_cloud_name', 'demo');
        AppSetting::setValue('cloudinary_api_key', '123456');
        AppSetting::setValue('cloudinary_api_secret', 'secret');

        $this->actingAs($admin)->postJson('/admin/videos/signature')
            ->assertOk()
            ->assertJsonPath('upload_url', 'https://api.cloudinary.com/v1_1/demo/video/upload')
            ->assertJsonPath('params.api_key', '123456')
            ->assertJsonStructure(['params' => ['signature', 'timestamp', 'folder', 'eager']]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\LatestRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LatestReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function fakeRedirectTo(string $tag): void
    {
        Http::fake([
            'https://github.com/*' => Http::response('', 302, [
                'Location' => "https://github.com/gobifinancement-ui/binho-flash/releases/tag/{$tag}",
            ]),
        ]);
    }

    public function test_the_latest_release_redirect_drives_the_site_and_update_prompt(): void
    {
        $this->fakeRedirectTo('v1.3.0');

        $this->getJson('/api/v1/app/version')->assertJsonPath('latest_version', '1.3.0');
        $this->get('/')->assertSee('Dernière version 1.3.0');
    }

    public function test_the_api_is_used_when_the_redirect_fails(): void
    {
        Http::fake([
            'https://github.com/*' => Http::response('', 500),
            'https://api.github.com/*' => Http::response(['tag_name' => 'v1.2.5']),
        ]);

        $this->getJson('/api/v1/app/version')->assertJsonPath('latest_version', '1.2.5');
    }

    public function test_the_last_known_version_survives_a_github_outage(): void
    {
        $this->fakeRedirectTo('v1.3.0');
        $this->getJson('/api/v1/app/version')->assertJsonPath('latest_version', '1.3.0');

        // GitHub ne répond plus et le cache court a expiré.
        LatestRelease::forget();
        Http::fake([
            'https://github.com/*' => Http::response('', 500),
            'https://api.github.com/*' => Http::response(['message' => 'API rate limit exceeded'], 403),
        ]);

        $this->getJson('/api/v1/app/version')->assertJsonPath('latest_version', '1.3.0');
    }

    public function test_the_admin_value_wins_when_it_is_newer(): void
    {
        AppSetting::setValue('app_latest_version', '1.4.0');
        Cache::forget('github_latest_apk_version_last_known');
        $this->fakeRedirectTo('v1.3.0');

        $this->getJson('/api/v1/app/version')->assertJsonPath('latest_version', '1.4.0');
    }
}

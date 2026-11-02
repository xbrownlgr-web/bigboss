<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Dernière version de l'APK, lue sur les « releases » GitHub : publier un nouvel APK suffit
 * pour que le site et l'invitation à mettre à jour suivent, sans toucher à l'admin.
 */
class LatestRelease
{
    private const CACHE_KEY = 'github_latest_apk_version';

    // Dernière version obtenue avec succès : sert si GitHub ne répond pas un moment.
    private const LAST_KNOWN_KEY = 'github_latest_apk_version_last_known';

    public static function version(): string
    {
        $fromAdmin = AppSetting::value('app_latest_version', '1.0.0') ?: '1.0.0';
        $fromGithub = self::githubVersion();

        return $fromGithub && version_compare($fromGithub, $fromAdmin, '>') ? $fromGithub : $fromAdmin;
    }

    /**
     * Lien de l'admin s'il est renseigné, sinon le lien permanent vers le dernier APK publié.
     */
    public static function downloadUrl(): ?string
    {
        $fromAdmin = AppSetting::value('app_download_url');

        if ($fromAdmin) {
            return $fromAdmin;
        }

        // APK servi directement depuis le domaine (public/big-boss.apk) : téléchargement direct,
        // sans page intermédiaire et sans dépendre de GitHub.
        if (is_file(public_path('big-boss.apk'))) {
            return url('big-boss.apk');
        }

        $repository = config('services.github.releases_repo');

        return $repository && self::githubVersion()
            ? "https://github.com/{$repository}/releases/latest/download/big-boss.apk"
            : null;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function githubVersion(): ?string
    {
        $repository = config('services.github.releases_repo');

        if (! $repository) {
            return null;
        }

        try {
            $cached = Cache::get(self::CACHE_KEY);

            if ($cached === null) {
                $fresh = self::fetch($repository);

                if ($fresh) {
                    Cache::put(self::CACHE_KEY, $fresh, now()->addMinutes(10));
                    Cache::forever(self::LAST_KNOWN_KEY, $fresh);
                } else {
                    // Échec : on ne réessaie qu'après une minute, pour ne pas ralentir chaque visite.
                    Cache::put(self::CACHE_KEY, '', now()->addMinute());
                }

                $cached = $fresh ?? '';
            }

            return $cached ?: (Cache::get(self::LAST_KNOWN_KEY) ?: null);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * « github.com/…/releases/latest » redirige vers « …/releases/tag/v1.3.0 ».
     * Contrairement à l'API GitHub (60 appels par heure et par adresse IP, partagée sur Render),
     * cette page n'est pas limitée de la sorte. L'API ne sert qu'en secours.
     */
    private static function fetch(string $repository): ?string
    {
        try {
            $location = Http::timeout(5)
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'binhoflash'])
                ->head("https://github.com/{$repository}/releases/latest")
                ->header('Location');

            if (preg_match('#/releases/tag/v?(\d+\.\d+\.\d+)$#', (string) $location, $match)) {
                return $match[1];
            }
        } catch (Throwable) {
            // On tente l'API ci-dessous.
        }

        try {
            $tag = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'binhoflash'])
                ->get("https://api.github.com/repos/{$repository}/releases/latest")
                ->json('tag_name');

            return is_string($tag) && preg_match('/^v?(\d+\.\d+\.\d+)$/', $tag, $match) ? $match[1] : null;
        } catch (Throwable) {
            return null;
        }
    }
}

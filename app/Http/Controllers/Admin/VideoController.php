<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Vidéos tutoriels (format vertical 9:16) affichées dans l'application.
 * Le fichier part directement du navigateur vers Cloudinary : Render (offre gratuite)
 * efface les fichiers déposés à chaque redéploiement, et n'a que 512 Mo de mémoire.
 */
class VideoController extends Controller
{
    public const SLOTS = [
        'recharge' => [
            'setting' => 'video_recharge_url',
            'title' => 'Comment recharger',
            'description' => 'Affichée sur l’écran « Recharger un compte ».',
        ],
        'retrait' => [
            'setting' => 'video_retrait_url',
            'title' => 'Comment retirer ses gains',
            'description' => 'Affichée sur l’écran « Retrait de gains ».',
        ],
    ];

    // Conversion en MP4 H.264 : lisible par tous les téléphones Android, même une vidéo d'iPhone.
    private const PLAYBACK_TRANSFORMATION = 'f_mp4,vc_h264,q_auto';

    public function index(): View
    {
        return view('admin.videos', [
            'slots' => collect(self::SLOTS)->map(fn ($slot) => [
                ...$slot,
                'url' => AppSetting::value($slot['setting']) ?: null,
            ]),
            'cloudinaryReady' => $this->cloudinaryReady(),
        ]);
    }

    /**
     * Paramètres signés pour un envoi direct navigateur → Cloudinary.
     */
    public function signature(): JsonResponse
    {
        if (! $this->cloudinaryReady()) {
            return response()->json(['message' => 'Cloudinary n’est pas configuré.'], 422);
        }

        $params = [
            'eager' => self::PLAYBACK_TRANSFORMATION,
            'eager_async' => 'true',
            'folder' => 'binhoflash/tutoriels',
            'timestamp' => (string) time(),
        ];

        return response()->json([
            'upload_url' => 'https://api.cloudinary.com/v1_1/' . AppSetting::value('cloudinary_cloud_name') . '/video/upload',
            'transformation' => self::PLAYBACK_TRANSFORMATION,
            'params' => [
                ...$params,
                'api_key' => AppSetting::value('cloudinary_api_key'),
                'signature' => self::sign($params, (string) AppSetting::value('cloudinary_api_secret')),
            ],
        ]);
    }

    public function update(string $slot): RedirectResponse
    {
        abort_unless(isset(self::SLOTS[$slot]), 404);

        $validated = request()->validate([
            'url' => ['required', 'url:https', 'max:500'],
        ], [
            'url.url' => 'Le lien doit commencer par https://.',
        ]);

        AppSetting::setValue(self::SLOTS[$slot]['setting'], $validated['url']);

        return back()->with('success', 'Vidéo « ' . self::SLOTS[$slot]['title'] . ' » enregistrée : elle est visible dans l’application.');
    }

    public function destroy(string $slot): RedirectResponse
    {
        abort_unless(isset(self::SLOTS[$slot]), 404);

        AppSetting::setValue(self::SLOTS[$slot]['setting'], '');

        return back()->with('success', 'Vidéo retirée de l’application.');
    }

    /**
     * Signature Cloudinary : paramètres triés « clé=valeur » joints par « & », suivis du secret, en SHA-1.
     *
     * @param array<string, string> $params
     */
    public static function sign(array $params, string $secret): string
    {
        ksort($params);

        $payload = collect($params)
            ->map(fn ($value, $key) => $key . '=' . $value)
            ->implode('&');

        return sha1($payload . $secret);
    }

    private function cloudinaryReady(): bool
    {
        return AppSetting::value('cloudinary_cloud_name')
            && AppSetting::value('cloudinary_api_key')
            && AppSetting::value('cloudinary_api_secret');
    }
}

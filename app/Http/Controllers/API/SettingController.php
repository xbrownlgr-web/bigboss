<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\LatestRelease;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function public(): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...AppSetting::publicPayload(),
        ]);
    }

    /**
     * Lu par l'application au démarrage pour proposer (ou imposer) une mise à jour de l'APK.
     */
    public function appVersion(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'latest_version' => LatestRelease::version(),
            'min_version' => AppSetting::value('app_min_version', '1.0.0'),
            'download_url' => LatestRelease::downloadUrl(),
            'message' => AppSetting::value('app_update_message'),
        ]);
    }
}

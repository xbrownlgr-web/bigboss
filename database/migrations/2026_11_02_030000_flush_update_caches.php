<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // setValue met à jour la base ET vide le cache de AppSetting (contrairement à un update brut).
        AppSetting::setValue('app_latest_version', '1.0.0');
        AppSetting::setValue('app_update_message', 'Une nouvelle version de BIG BOSS est disponible.');

        // Le cache GitHub avait mémorisé la version 1.4.0 de Binho (avant le changement de repo).
        cache()->forget('github_latest_apk_version');
        cache()->forget('github_latest_apk_version_last_known');
    }

    public function down(): void
    {
    }
};

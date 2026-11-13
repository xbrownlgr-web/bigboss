<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * APK 1.1.0 (messages vocaux au support, nouveau module audio) : l'app installée compare sa
 * version à app_latest_version et propose de télécharger le nouvel APK (public/big-boss.apk).
 * Récupère aussi les très vieux APK (versionCode ≤ 14) qui ne reçoivent aucune mise à jour OTA.
 * À ne déployer QU'AVEC le nouvel APK en place, sinon l'invitation téléchargerait l'ancien.
 */
return new class extends Migration
{
    public function up(): void
    {
        AppSetting::setValue('app_latest_version', '1.1.0');
        AppSetting::setValue('app_update_message', 'Nouvelle version de BIG BOSS : envoyez des messages vocaux au support. Téléchargez-la puis installez-la par-dessus l’ancienne (vos données sont conservées).');
    }

    public function down(): void
    {
        AppSetting::setValue('app_latest_version', '1.0.0');
    }
};

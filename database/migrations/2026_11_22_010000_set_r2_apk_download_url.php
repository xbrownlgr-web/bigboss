<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bascule le téléchargement de l'APK vers Cloudflare R2 (bande passante illimitée gratuite) afin
 * que Render ne serve plus jamais le fichier de 43 Mo et reste largement sous le plafond gratuit
 * de 5 Go/mois. Vide aussi le cache rememberForever d'AppSetting.
 */
return new class extends Migration
{
    private const R2_APK_URL = 'https://pub-5c9795edabbf4458b10e516b981f3c5a.r2.dev/big-boss.apk';

    public function up(): void
    {
        $now = now();

        if (DB::table('app_settings')->where('key', 'app_download_url')->exists()) {
            DB::table('app_settings')
                ->where('key', 'app_download_url')
                ->update(['value' => self::R2_APK_URL, 'updated_at' => $now]);
        } else {
            DB::table('app_settings')->insert([
                'group' => 'app',
                'key' => 'app_download_url',
                'label' => 'Lien de téléchargement de l’APK',
                'value' => self::R2_APK_URL,
                'type' => 'url',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('app_setting_app_download_url');

        if (Schema::hasTable('cache')) {
            DB::table('cache')->where('key', 'like', '%app_setting_app_download_url%')->delete();
        }
    }

    public function down(): void
    {
        // On ne réécrase pas une URL éventuellement modifiée par l'admin.
    }
};

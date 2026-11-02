<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTINGS = [
        [
            'key' => 'app_latest_version',
            'label' => 'Dernière version publiée',
            'value' => '1.0.0',
            'type' => 'text',
        ],
        [
            'key' => 'app_min_version',
            'label' => 'Version minimale obligatoire',
            'value' => '1.0.0',
            'type' => 'text',
        ],
        [
            'key' => 'app_download_url',
            'label' => 'Lien de téléchargement de l’APK',
            'value' => '',
            'type' => 'url',
        ],
        [
            'key' => 'app_update_message',
            'label' => 'Message affiché aux utilisateurs',
            'value' => 'Une nouvelle version de Binho Flash est disponible.',
            'type' => 'text',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::SETTINGS as $setting) {
            if (DB::table('app_settings')->where('key', $setting['key'])->exists()) {
                continue;
            }

            DB::table('app_settings')->insert([
                ...$setting,
                'group' => 'app',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')
            ->whereIn('key', array_column(self::SETTINGS, 'key'))
            ->delete();
    }
};

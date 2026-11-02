<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'video_recharge_url', 'label' => 'Vidéo tutoriel : recharge', 'type' => 'url'],
        ['key' => 'video_retrait_url', 'label' => 'Vidéo tutoriel : retrait', 'type' => 'url'],
        ['key' => 'cloudinary_cloud_name', 'label' => 'Cloudinary : nom du cloud (Cloud name)', 'type' => 'text'],
        ['key' => 'cloudinary_api_key', 'label' => 'Cloudinary : API Key', 'type' => 'text'],
        ['key' => 'cloudinary_api_secret', 'label' => 'Cloudinary : API Secret', 'type' => 'password'],
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
                'value' => '',
                'group' => 'videos',
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

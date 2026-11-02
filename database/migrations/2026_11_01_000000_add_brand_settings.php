<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'brand_app_name', 'label' => 'Nom de l’application', 'type' => 'text', 'value' => 'BIG BOSS'],
        ['key' => 'brand_palette', 'label' => 'Palette de couleurs', 'type' => 'palette', 'value' => 'green_black'],
        ['key' => 'brand_logo_url', 'label' => 'Logo (URL, optionnel)', 'type' => 'url', 'value' => ''],
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
                'group' => 'brand',
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

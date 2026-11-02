<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTINGS = [
        [
            'key' => 'openrouter_api_key',
            'label' => 'Clé API OpenRouter',
            'value' => '',
            'type' => 'password',
        ],
        [
            'key' => 'openrouter_model',
            'label' => 'Modèle IA (OpenRouter)',
            'value' => 'openai/gpt-4o-mini',
            'type' => 'text',
        ],
        [
            'key' => 'api_football_key',
            'label' => 'Clé API-Football (matchs du jour)',
            'value' => '',
            'type' => 'password',
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
                'group' => 'ia',
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

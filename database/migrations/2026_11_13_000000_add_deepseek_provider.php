<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DeepSeek comme fournisseur de l'IA (voir App\Support\AiProvider). Ne remplace jamais une valeur.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'ai_provider', 'label' => 'Fournisseur IA (auto / deepseek / openrouter)', 'value' => 'auto', 'type' => 'text'],
        ['key' => 'deepseek_api_key', 'label' => 'Clé API DeepSeek', 'value' => '', 'type' => 'password'],
        ['key' => 'deepseek_model', 'label' => 'Modèle IA (DeepSeek)', 'value' => 'deepseek-chat', 'type' => 'text'],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            if (DB::table('app_settings')->where('key', $setting['key'])->exists()) {
                continue;
            }

            DB::table('app_settings')->insert([...$setting, 'group' => 'ia', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();
    }
};

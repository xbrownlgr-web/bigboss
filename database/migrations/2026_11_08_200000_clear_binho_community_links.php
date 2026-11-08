<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Les liens réseaux sociaux vides (NULL) renvoyaient les pages de Binho Flash (valeur par défaut
 * héritée du fork), et AppSetting::value() avait mis ce défaut en cache pour toujours. On met les
 * champs vides à '' et on vide leur cache : seuls les liens saisis dans l'admin BIG BOSS restent.
 */
return new class extends Migration
{
    private const KEYS = ['telegram_url', 'whatsapp_channel_url', 'facebook_url', 'tiktok_url', 'instagram_url'];

    public function up(): void
    {
        foreach (self::KEYS as $key) {
            AppSetting::query()->where('key', $key)->whereNull('value')->update(['value' => '']);
            AppSetting::query()->where('key', $key)->where('value', 'like', '%binho%')->update(['value' => '']);
            cache()->forget('app_setting_' . $key);
        }
    }

    public function down(): void
    {
        // Rien à restaurer : on ne remet jamais les liens de Binho dans BIG BOSS.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AppSetting::value() met en cache pour toujours (rememberForever). Les deux migrations précédentes
 * (2026_11_05_000001 et _000002) ont modifié « vip_bronze_details » en base sans jamais vider
 * « app_setting_vip_bronze_details » du cache : le site continuait de servir l'ancien texte même
 * après un déploiement réussi. Répète le remplacement par sécurité (idempotent) et vide le cache.
 */
return new class extends Migration
{
    private const OLD_FRAGMENT = '⭐ 3 événements sélectionnés chaque jour par notre équipe.';

    private const NEW_FRAGMENT = '⭐ 2 événements sélectionnés chaque jour par notre équipe.';

    public function up(): void
    {
        $value = DB::table('app_settings')->where('key', 'vip_bronze_details')->value('value');

        if ($value !== null && str_contains($value, self::OLD_FRAGMENT)) {
            DB::table('app_settings')
                ->where('key', 'vip_bronze_details')
                ->update(['value' => str_replace(self::OLD_FRAGMENT, self::NEW_FRAGMENT, $value), 'updated_at' => now()]);
        }

        Cache::forget('app_setting_vip_bronze_details');
    }

    public function down(): void
    {
        Cache::forget('app_setting_vip_bronze_details');
    }
};

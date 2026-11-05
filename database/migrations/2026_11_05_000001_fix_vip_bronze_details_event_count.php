<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le texte par défaut de « vip_bronze_details » (migration du 2026-11-04) annonçait 3 événements,
 * copié sur Silver par erreur. L'échelle BIG BOSS (voir CLAUDE.md) est Bronze = 2 événements/jour.
 * Ne remplace que si la valeur est encore exactement ce texte par défaut, jamais une valeur déjà
 * modifiée par l'admin dans Admin > VIP.
 */
return new class extends Migration
{
    private const OLD_DEFAULT = "⭐ 3 événements sélectionnés chaque jour par notre équipe.\n"
        . "📈 Cote combinée fiable, misée sur la régularité plutôt que le risque.\n"
        . "🔔 Coupon envoyé en notification dès qu'il est prêt.\n"
        . "🗓️ 30 jours d'accès, renouvelable.";

    private const NEW_DEFAULT = "⭐ 2 événements sélectionnés chaque jour par notre équipe.\n"
        . "📈 Cote combinée fiable, misée sur la régularité plutôt que le risque.\n"
        . "🔔 Coupon envoyé en notification dès qu'il est prêt.\n"
        . "🗓️ 30 jours d'accès, renouvelable.";

    public function up(): void
    {
        DB::table('app_settings')
            ->where('key', 'vip_bronze_details')
            ->where('value', self::OLD_DEFAULT)
            ->update(['value' => self::NEW_DEFAULT, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('app_settings')
            ->where('key', 'vip_bronze_details')
            ->where('value', self::NEW_DEFAULT)
            ->update(['value' => self::OLD_DEFAULT]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bronze devient VIP (un seul niveau gratuit désormais : Starter). Ajoute les réglages prix et
 * avantages de Bronze, dans le même format que Silver/Gold/Platinum (Admin > VIP).
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['vip_bronze_price', 'Prix du VIP Bronze (FCFA)', 'text', ''],
        ['vip_bronze_details', 'Détails du VIP Bronze', 'textarea',
            "⭐ 3 événements sélectionnés chaque jour par notre équipe.\n"
            . "📈 Cote combinée fiable, misée sur la régularité plutôt que le risque.\n"
            . "🔔 Coupon envoyé en notification dès qu'il est prêt.\n"
            . "🗓️ 30 jours d'accès, renouvelable."],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as [$key, $label, $type, $default]) {
            if (DB::table('app_settings')->where('key', $key)->exists()) {
                continue; // ne pas écraser une valeur déjà réglée par l'admin
            }

            DB::table('app_settings')->insert([
                'group' => 'vip',
                'key' => $key,
                'label' => $label,
                'value' => $default,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', array_column(self::SETTINGS, 0))->delete();
    }
};

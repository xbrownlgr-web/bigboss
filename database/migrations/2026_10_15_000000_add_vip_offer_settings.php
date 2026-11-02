<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Coupons VIP payants (Silver, Gold, Platinum) : prix et détails affichés dans l'application,
 * et numéro WhatsApp qui reçoit les demandes d'accès.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['vip_whatsapp_number', 'Numéro WhatsApp qui reçoit les demandes VIP', 'text'],
        ['vip_silver_price', 'Prix du VIP Silver (FCFA)', 'text'],
        ['vip_silver_details', 'Détails du VIP Silver', 'textarea'],
        ['vip_gold_price', 'Prix du VIP Gold (FCFA)', 'text'],
        ['vip_gold_details', 'Détails du VIP Gold', 'textarea'],
        ['vip_platinum_price', 'Prix du VIP Platinum (FCFA)', 'text'],
        ['vip_platinum_details', 'Détails du VIP Platinum', 'textarea'],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as [$key, $label, $type]) {
            if (DB::table('app_settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('app_settings')->insert([
                'group' => 'vip',
                'key' => $key,
                'label' => $label,
                'value' => '',
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

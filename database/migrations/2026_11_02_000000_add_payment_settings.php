<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'maketou_api_key', 'label' => 'MAKETOU : clé API (Bearer)', 'type' => 'password', 'value' => ''],
        ['key' => 'maketou_product_id', 'label' => 'MAKETOU : identifiant du produit « Abonnement VIP » (prix libre)', 'type' => 'text', 'value' => ''],
        ['key' => 'maketou_fee_percent', 'label' => 'Frais ajoutés au paiement (%)', 'type' => 'text', 'value' => '7'],
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
                'group' => 'payment',
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

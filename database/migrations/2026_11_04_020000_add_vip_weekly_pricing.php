<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Abonnement VIP à la semaine (en plus du mois) : prix réglable par niveau, pour permettre un
 * essai payant moins engageant avant de passer au mois complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (AppSetting::VIP_LEVELS as $level) {
            $key = 'vip_' . strtolower($level) . '_price_week';

            if (DB::table('app_settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('app_settings')->insert([
                'group' => 'vip',
                'key' => $key,
                'label' => "Prix du VIP {$level} à la semaine (FCFA)",
                'value' => '',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')
            ->whereIn('key', collect(AppSetting::VIP_LEVELS)->map(fn ($level) => 'vip_' . strtolower($level) . '_price_week')->all())
            ->delete();
    }
};

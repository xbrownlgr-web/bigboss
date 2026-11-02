<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retrait « Espèces » sur 1xBet / Melbet : l'utilisateur choisit une ville et un point de retrait
 * (le guichet Binho Flash). Ces infos sont affichées dans les instructions de l'application.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'retrait_ville', 'label' => 'Ville à choisir sur 1xBet / Melbet'],
        ['key' => 'retrait_point', 'label' => 'Point de retrait (guichet) à choisir'],
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
                'group' => 'retrait',
                'value' => '',
                'type' => 'text',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();
    }
};

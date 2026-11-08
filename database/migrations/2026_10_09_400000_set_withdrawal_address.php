<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adresse de retrait « Espèces » sur 1xBet / Melbet : la plateforme demande une ville et une rue.
 * Guichet BIG BOSS : Cotonou, « BIG BOSS » (valeurs de Binho retirées : jamais les mélanger). Les valeurs ne sont posées que si
 * elles sont encore vides : une adresse déjà saisie dans l'admin n'est jamais écrasée.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        'retrait_ville' => ['label' => 'Ville à choisir sur 1xBet / Melbet', 'value' => 'Cotonou'],
        'retrait_point' => ['label' => 'Rue à choisir sur 1xBet / Melbet', 'value' => 'BIG BOSS'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::DEFAULTS as $key => $setting) {
            $current = DB::table('app_settings')->where('key', $key)->first();

            if (! $current) {
                DB::table('app_settings')->insert([
                    'key' => $key,
                    'label' => $setting['label'],
                    'group' => 'retrait',
                    'value' => $setting['value'],
                    'type' => 'text',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('app_settings')->where('key', $key)->update([
                'label' => $setting['label'],
                'value' => trim((string) $current->value) === '' ? $setting['value'] : $current->value,
                'updated_at' => $now,
            ]);
        }

        // AppSetting::value() garde les réglages en cache : on l'oublie pour servir la nouvelle adresse.
        foreach (array_keys(self::DEFAULTS) as $key) {
            cache()->forget('app_setting_' . $key);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'retrait_point')->update(['label' => 'Point de retrait (guichet) à choisir']);
    }
};

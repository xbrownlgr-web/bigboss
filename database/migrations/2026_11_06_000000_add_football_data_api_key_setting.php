<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clé optionnelle football-data.org (gratuite, 10 req/min, sans plafond quotidien) : décharge
 * Highlightly du classement et de la forme pour les 10 championnats qu'elle couvre en gratuit
 * (voir FootballDataLeagues). Vide par défaut : aucun changement de comportement tant que l'admin
 * n'ajoute pas sa clé dans Admin > Paramètres > Intelligence artificielle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('app_settings')->where('key', 'football_data_api_key')->exists()) {
            return;
        }

        DB::table('app_settings')->insert([
            'group' => 'ia',
            'key' => 'football_data_api_key',
            'label' => 'Clé football-data.org (optionnelle)',
            'value' => '',
            'type' => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'football_data_api_key')->delete();
    }
};

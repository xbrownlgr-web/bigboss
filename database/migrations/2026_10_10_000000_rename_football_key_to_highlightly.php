<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Récupère l'ancienne valeur (si elle avait déjà été saisie).
        $old = DB::table('app_settings')->where('key', 'api_football_key')->value('value') ?? '';

        if (! DB::table('app_settings')->where('key', 'highlightly_api_key')->exists()) {
            DB::table('app_settings')->insert([
                'key' => 'highlightly_api_key',
                'label' => 'Clé API Highlightly (matchs du jour)',
                'value' => $old,
                'type' => 'password',
                'group' => 'ia',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('app_settings')->where('key', 'api_football_key')->delete();
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'highlightly_api_key')->delete();
    }
};

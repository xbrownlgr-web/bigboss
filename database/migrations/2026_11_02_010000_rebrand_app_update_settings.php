<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->where('key', 'app_update_message')
            ->where('value', 'Une nouvelle version de Binho Flash est disponible.')
            ->update(['value' => 'Une nouvelle version de BIG BOSS est disponible.']);

        // L'APK BIG BOSS démarre en 1.0.0 : aligner la version « dernière » pour éviter une
        // fausse invite de mise à jour tant que la release GitHub big-boss n'existe pas.
        DB::table('app_settings')->whereIn('key', ['app_latest_version'])
            ->where('value', '1.4.0')->update(['value' => '1.0.0']);
    }

    public function down(): void
    {
        // Pas de retour en arrière nécessaire.
    }
};

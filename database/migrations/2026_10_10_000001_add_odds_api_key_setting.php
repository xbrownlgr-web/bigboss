<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('app_settings')->where('key', 'odds_api_key')->exists()) {
            DB::table('app_settings')->insert([
                'key' => 'odds_api_key',
                'label' => 'Clé The Odds API (cotes réelles)',
                'value' => '',
                'type' => 'password',
                'group' => 'ia',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'odds_api_key')->delete();
    }
};

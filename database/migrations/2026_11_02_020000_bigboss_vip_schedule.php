<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Horaires de l'échelle BIG BOSS (niveau 1 matin+soir, les autres 1×/jour).
    private const SCHEDULE = [
        'ai_schedule_starter' => '09:00, 19:00',
        'ai_schedule_bronze' => '11:00',
        'ai_schedule_silver' => '13:00',
        'ai_schedule_gold' => '16:00',
        'ai_schedule_platinum' => '20:00',
    ];

    public function up(): void
    {
        foreach (self::SCHEDULE as $key => $value) {
            DB::table('app_settings')->where('key', $key)->update(['value' => $value]);
        }
    }

    public function down(): void
    {
    }
};

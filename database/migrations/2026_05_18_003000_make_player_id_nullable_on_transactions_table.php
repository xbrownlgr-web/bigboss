<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('player_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('transactions')->whereNull('player_id')->update(['player_id' => 'N/A']);

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('player_id')->nullable(false)->change();
        });
    }
};

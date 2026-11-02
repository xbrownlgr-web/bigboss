<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pronostics', function (Blueprint $table) {
            $table->enum('result_status', ['won', 'lost'])->nullable()->after('published_at');
            $table->text('result_note')->nullable()->after('result_status');
            $table->timestamp('result_checked_at')->nullable()->after('result_note');
            $table->timestamp('result_notified_at')->nullable()->after('result_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pronostics', function (Blueprint $table) {
            $table->dropColumn([
                'result_status',
                'result_note',
                'result_checked_at',
                'result_notified_at',
            ]);
        });
    }
};

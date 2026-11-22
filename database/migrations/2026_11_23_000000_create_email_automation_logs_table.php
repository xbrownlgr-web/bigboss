<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_automation_logs')) {
            return;
        }

        Schema::create('email_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign', 64);
            $table->timestamp('sent_at')->useCurrent();

            $table->unique(['user_id', 'campaign']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_automation_logs');
    }
};

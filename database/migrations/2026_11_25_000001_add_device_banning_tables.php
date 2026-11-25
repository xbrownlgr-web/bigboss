<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'device_id')) {
                $table->string('device_id', 120)->nullable()->index()->after('fcm_token');
            }
            if (! Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('users', 'ban_reason')) {
                $table->string('ban_reason', 255)->nullable()->after('banned_at');
            }
        });

        if (! Schema::hasTable('banned_devices')) {
            Schema::create('banned_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('device_id', 120)->nullable()->index();
                $table->string('fcm_token', 255)->nullable()->index();
                $table->string('phone_normalized', 32)->nullable()->index();
                $table->string('email', 255)->nullable()->index();
                $table->string('reason', 255)->nullable();
                $table->unsignedBigInteger('banned_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_devices');

        Schema::table('users', function (Blueprint $table) {
            foreach (['ban_reason', 'banned_at', 'device_id'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

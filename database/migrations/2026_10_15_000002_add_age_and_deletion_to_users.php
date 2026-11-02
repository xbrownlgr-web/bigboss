<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - adult_confirmed_at : l'utilisateur a coché « J'ai 18 ans ou plus » à l'inscription.
 * - account_deleted_at : compte supprimé à sa demande (données personnelles effacées).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('adult_confirmed_at')->nullable()->after('country');
            $table->timestamp('account_deleted_at')->nullable()->after('adult_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['adult_confirmed_at', 'account_deleted_at']);
        });
    }
};

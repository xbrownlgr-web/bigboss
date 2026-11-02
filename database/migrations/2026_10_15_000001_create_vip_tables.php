<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnements VIP payés sur WhatsApp :
 * - vip_requests : clé unique créée quand l'utilisateur touche « Accéder », activée par l'admin après paiement ;
 * - vip_subscriptions : un abonnement par utilisateur et par niveau, valable 30 jours à partir de l'activation.
 * Les coupons VIP (code 1xBet) sont des pronostics ordinaires, avec une heure limite facultative.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vip_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20);
            $table->string('key', 20)->unique();
            $table->unsignedInteger('price')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at');
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'level', 'status']);
        });

        Schema::create('vip_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->index();
            $table->timestamp('reminded_soon_at')->nullable();
            $table->timestamp('reminded_ended_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'level']);
        });

        Schema::table('pronostics', function (Blueprint $table) {
            $table->timestamp('valid_until')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('pronostics', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });
        Schema::dropIfExists('vip_subscriptions');
        Schema::dropIfExists('vip_requests');
    }
};

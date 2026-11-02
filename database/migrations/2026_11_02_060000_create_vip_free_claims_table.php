<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de VIP Silver OFFERT (1 mois) via parrainage 1xBet : l'utilisateur s'inscrit avec le
 * code promo, dépose le minimum, saisit l'ID de son compte et joint une capture d'écran.
 * La capture est stockée en base (le disque Render est éphémère) pour survivre aux redéploiements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vip_free_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('Silver');
            $table->string('promo_code')->nullable();
            $table->string('account_id');                 // ID du compte 1xBet saisi par l'utilisateur
            $table->longText('screenshot')->nullable();    // capture en base64 (sans préfixe data:)
            $table->string('screenshot_mime')->default('image/jpeg');
            $table->string('status')->default('pending');  // pending | approved | rejected
            $table->string('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vip_free_claims');
    }
};

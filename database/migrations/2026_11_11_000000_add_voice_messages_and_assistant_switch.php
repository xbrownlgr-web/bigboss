<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes du fondateur (2026-11-11) :
 * - messages vocaux dans le support : le son est gardé en base (disque Render éphémère), dans une
 *   table à part pour garder la table des messages légère, et supprimé 3 jours après
 *   (`bigboss:purge-voice-messages`). `messages.audio_duration` reste : on sait qu'il y avait un vocal.
 * - assistant IA (LUC) coupé pour l'instant : le support va directement à un humain. Réglage
 *   `assistant_enabled` (oui/non) dans Admin > Paramètres pour le rallumer plus tard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedSmallInteger('audio_duration')->nullable()->after('content');
        });

        Schema::create('message_audios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained('messages')->cascadeOnDelete();
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            // Contenu en base64 (portable MySQL/TiDB/SQLite, ~1,3 Mo pour 2 minutes de voix).
            $table->longText('data');
            $table->timestamps();
            $table->index('created_at');
        });

        if (! DB::table('app_settings')->where('key', 'assistant_enabled')->exists()) {
            DB::table('app_settings')->insert([
                'group' => 'assistant',
                'key' => 'assistant_enabled',
                'label' => 'Assistant IA LUC dans le support (oui/non)',
                'value' => 'non',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_audios');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('audio_duration');
        });

        DB::table('app_settings')->where('key', 'assistant_enabled')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos dans le support, dans les deux sens (client → admin, admin → client), demande du fondateur
 * du 2026-11-15. Gardées en base comme les vocaux (disque Render éphémère), supprimées 3 jours après.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('has_image')->default(false)->after('audio_duration');
        });

        Schema::create('message_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained('messages')->cascadeOnDelete();
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->longText('data');
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_images');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('has_image');
        });
    }
};

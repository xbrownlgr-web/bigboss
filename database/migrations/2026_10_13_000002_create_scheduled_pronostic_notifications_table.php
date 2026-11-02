<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un premier essai a échoué en production (nom d'index de plus de 64 caractères) après
        // la création de la table : on repart d'une table propre (elle n'a jamais servi).
        Schema::dropIfExists('scheduled_pronostic_notifications');

        Schema::create('scheduled_pronostic_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->unsignedInteger('sequence');
            $table->foreignId('pronostic_id')->constrained()->cascadeOnDelete();
            $table->timestamp('available_at')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();

            // Noms courts : MySQL refuse les noms d'index de plus de 64 caractères.
            $table->unique(['batch_id', 'sequence'], 'spn_batch_sequence_unique');
            $table->index(['batch_id', 'sent_at', 'sequence'], 'spn_batch_sent_sequence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_pronostic_notifications');
    }
};

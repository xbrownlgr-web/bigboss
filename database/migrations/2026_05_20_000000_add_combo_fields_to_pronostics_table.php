<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pronostics', function (Blueprint $table) {
            $table->string('niveau')->nullable()->after('id');
            $table->string('couleur', 20)->nullable()->after('niveau');
            $table->decimal('cote_totale', 8, 2)->nullable()->after('couleur');
            $table->unsignedTinyInteger('probabilite')->nullable()->after('cote_totale');
            $table->json('evenements')->nullable()->after('probabilite');
            $table->string('code_1xbet')->nullable()->after('evenements');
            $table->string('code_melbet')->nullable()->after('code_1xbet');
        });
    }

    public function down(): void
    {
        Schema::table('pronostics', function (Blueprint $table) {
            $table->dropColumn([
                'niveau',
                'couleur',
                'cote_totale',
                'probabilite',
                'evenements',
                'code_1xbet',
                'code_melbet',
            ]);
        });
    }
};

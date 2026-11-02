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
        Schema::create('pronostics', function (Blueprint $table) {
            $table->id();
            $table->date('match_date');
            $table->time('match_time');
            $table->string('team_home');
            $table->string('team_away');
            $table->string('competition');
            $table->string('prediction');
            $table->decimal('odds', 5, 2);
            $table->tinyInteger('confidence');
            $table->text('analysis');
            $table->boolean('is_vip')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pronostics');
    }
};

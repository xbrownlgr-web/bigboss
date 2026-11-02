<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversation avec l'assistant 24h/24, séparée des messages au conseiller humain.
        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            // Guides illustrés joints à la réponse (« recharge », « retrait »…), affichés par l'application.
            $table->json('guides')->nullable();
            // L'assistant propose de passer la main à un conseiller humain.
            $table->boolean('handoff')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });

        $now = now();
        $settings = [
            [
                'key' => 'assistant_model',
                'label' => 'Modèle de l’assistant',
                'value' => null,
                'type' => 'text',
            ],
            [
                'key' => 'assistant_instructions',
                'label' => 'Informations pour l’assistant',
                'value' => null,
                'type' => 'textarea',
            ],
            [
                'key' => 'assistant_daily_limit',
                'label' => 'Messages par client et par jour',
                'value' => '60',
                'type' => 'text',
            ],
        ];

        foreach ($settings as $setting) {
            if (DB::table('app_settings')->where('key', $setting['key'])->exists()) {
                continue;
            }

            DB::table('app_settings')->insert([
                ...$setting,
                'group' => 'assistant',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        DB::table('app_settings')
            ->whereIn('key', ['assistant_model', 'assistant_instructions', 'assistant_daily_limit'])
            ->delete();
    }
};

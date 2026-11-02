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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['recharge', 'retrait']);
            $table->enum('platform', ['1xbet', 'melbet']);
            $table->string('player_id');
            $table->decimal('amount', 10, 2);
            $table->string('mobile_money_number')->nullable();
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'rejected',
            ])->default('pending');
            $table->string('fedapay_transaction_id')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

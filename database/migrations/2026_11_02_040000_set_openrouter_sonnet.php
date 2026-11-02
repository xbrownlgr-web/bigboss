<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AppSetting::setValue('openrouter_model', 'anthropic/claude-sonnet-5.5');
    }

    public function down(): void
    {
    }
};

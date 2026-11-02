<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('key')->unique();
            $table->string('label');
            $table->text('value')->nullable();
            $table->string('type')->default('text');
            $table->timestamps();
        });

        $now = now();

        DB::table('app_settings')->insert([
            [
                'group' => 'general',
                'key' => 'admin_email',
                'label' => 'E-mail administrateur',
                'value' => 'admin@binhoflash.com',
                'type' => 'email',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'general',
                'key' => 'support_email',
                'label' => 'E-mail support',
                'value' => 'support@binhoflash.com',
                'type' => 'email',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'whatsapp',
                'key' => 'callmebot_phone',
                'label' => 'Numéro WhatsApp admin',
                'value' => env('CALLMEBOT_PHONE', ''),
                'type' => 'text',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'whatsapp',
                'key' => 'callmebot_apikey',
                'label' => 'Clé API CallMeBot',
                'value' => env('CALLMEBOT_APIKEY', ''),
                'type' => 'password',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'mobile_money',
                'key' => 'mtn_momo_ussd',
                'label' => 'USSD MTN MoMo',
                'value' => '*880#',
                'type' => 'text',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'mobile_money',
                'key' => 'mtn_momo_merchant',
                'label' => 'Numéro marchand MTN MoMo',
                'value' => 'NUMERO_MTN_MARCHAND',
                'type' => 'text',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'mobile_money',
                'key' => 'moov_money_ussd',
                'label' => 'USSD Moov Money',
                'value' => '*155#',
                'type' => 'text',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'mobile_money',
                'key' => 'moov_money_merchant',
                'label' => 'Numéro marchand Moov Money',
                'value' => 'NUMERO_MOOV_MARCHAND',
                'type' => 'text',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'mobile_money',
                'key' => 'celtiis_ussd',
                'label' => 'USSD Celtiis',
                'value' => '*144#',
                'type' => 'text',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'mobile_money',
                'key' => 'celtiis_merchant',
                'label' => 'Numéro marchand Celtiis',
                'value' => 'NUMERO_CELTIIS_MARCHAND',
                'type' => 'text',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'community',
                'key' => 'telegram_url',
                'label' => 'Lien Telegram',
                'value' => 'https://t.me/Binho_pronostics17',
                'type' => 'url',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'community',
                'key' => 'whatsapp_channel_url',
                'label' => 'Lien canal WhatsApp',
                'value' => 'https://whatsapp.com/channel/0029VbCycEHISTkBkokScd0K',
                'type' => 'url',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'community',
                'key' => 'facebook_url',
                'label' => 'Lien Facebook',
                'value' => 'https://www.facebook.com/binhoflash',
                'type' => 'url',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'community',
                'key' => 'tiktok_url',
                'label' => 'Lien TikTok',
                'value' => 'https://www.tiktok.com/@binhoflash',
                'type' => 'url',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'community',
                'key' => 'instagram_url',
                'label' => 'Lien Instagram',
                'value' => 'https://www.instagram.com/binhoflash',
                'type' => 'url',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recharge par dépôt Mobile Money : l'utilisateur envoie l'argent à un numéro, vérifie le nom
 * affiché pendant le transfert, puis saisit l'ID de transaction reçu par SMS.
 * Les codes USSD et numéros marchands ne servent plus.
 */
return new class extends Migration
{
    private const OPERATORS = [
        'mtn_momo' => 'MTN MoMo',
        'moov_money' => 'Moov Money',
        'celtiis' => 'Celtiis',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::OPERATORS as $key => $label) {
            // Reprend un éventuel vrai numéro marchand déjà saisi.
            $merchant = DB::table('app_settings')->where('key', $key . '_merchant')->value('value');
            $number = $merchant && ! str_starts_with($merchant, 'NUMERO_') ? $merchant : '';

            $rows = [
                [$key . '_number', $label . ' : numéro de dépôt', $number],
                [$key . '_name', $label . ' : nom affiché pendant le transfert', ''],
            ];

            foreach ($rows as [$settingKey, $settingLabel, $value]) {
                if (DB::table('app_settings')->where('key', $settingKey)->exists()) {
                    continue;
                }

                DB::table('app_settings')->insert([
                    'group' => 'mobile_money',
                    'key' => $settingKey,
                    'label' => $settingLabel,
                    'value' => $value,
                    'type' => 'text',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('app_settings')->whereIn('key', [$key . '_ussd', $key . '_merchant'])->delete();
        }
    }

    public function down(): void
    {
        $now = now();

        foreach (self::OPERATORS as $key => $label) {
            $number = DB::table('app_settings')->where('key', $key . '_number')->value('value');

            DB::table('app_settings')->insert([
                ['group' => 'mobile_money', 'key' => $key . '_ussd', 'label' => 'USSD ' . $label, 'value' => '', 'type' => 'text', 'created_at' => $now, 'updated_at' => $now],
                ['group' => 'mobile_money', 'key' => $key . '_merchant', 'label' => 'Numéro marchand ' . $label, 'value' => $number ?: '', 'type' => 'text', 'created_at' => $now, 'updated_at' => $now],
            ]);

            DB::table('app_settings')->whereIn('key', [$key . '_number', $key . '_name'])->delete();
        }
    }
};

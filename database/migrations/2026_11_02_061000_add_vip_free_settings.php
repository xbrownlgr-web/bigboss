<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Réglages de l'offre « VIP Silver 1 mois OFFERT » (parrainage 1xBet). Tout est modifiable
 * dans Admin > VIP : activer/désactiver, code promo, dépôt minimum, lien et consignes.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['vip_free_enabled', 'Offre Silver gratuit activée (oui/non)', 'text', 'oui'],
        ['vip_free_promo_code', 'Code promo 1xBet', 'text', 'CR7MILLIONS'],
        ['vip_free_min_deposit', 'Dépôt minimum demandé (FCFA)', 'text', '2000'],
        ['vip_free_download_url', 'Lien de téléchargement 1xBet', 'text', 'https://1xbet.bj/download/apk'],
        ['vip_free_instructions', 'Consignes affichées dans l\'application', 'textarea',
            "1️⃣ Téléchargez la dernière version de l'application 1xBet.\n"
            . "2️⃣ Installez-la, puis déconnectez-vous de votre ancien compte.\n"
            . "3️⃣ Inscrivez-vous à nouveau avec le code promo CR7MILLIONS (exactement comme écrit).\n"
            . "4️⃣ Effectuez un dépôt minimum de 2 000 F CFA.\n"
            . "5️⃣ Saisissez l'ID de votre compte et joignez la capture qui montre votre dépôt.\n\n"
            . "💡 Inscription en un clic ? Complétez vos infos ensuite dans Profil."],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as [$key, $label, $type, $default]) {
            if (DB::table('app_settings')->where('key', $key)->exists()) {
                continue; // ne pas écraser une valeur déjà réglée par l'admin
            }

            DB::table('app_settings')->insert([
                'group' => 'vip',
                'key' => $key,
                'label' => $label,
                'value' => $default,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', array_column(self::SETTINGS, 0))->delete();
    }
};

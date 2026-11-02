<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Avantages affichés sous chaque VIP payant (Admin > VIP, modifiables à tout moment).
 * On ne remplit que si le champ est encore vide, pour ne jamais écraser un texte saisi par BIG BOSS.
 */
return new class extends Migration
{
    private const DETAILS = [
        'vip_silver_details' => "⭐ 3 événements sélectionnés chaque jour par notre équipe.\n"
            . "📈 Cote combinée plus élevée pour viser plus gros.\n"
            . "🔔 Coupon envoyé en notification dès qu'il est prêt.\n"
            . "🗓️ 30 jours d'accès, renouvelable.",

        'vip_gold_details' => "🔥 4 événements triés sur le volet chaque jour.\n"
            . "💰 Cote combinée renforcée : plus de potentiel de gain.\n"
            . "⚡ Notification prioritaire dès la publication.\n"
            . "📊 Historique des résultats visible dans l'application.\n"
            . "🗓️ 30 jours d'accès, renouvelable.",

        'vip_platinum_details' => "👑 Notre offre la plus complète : 5 événements chaque jour.\n"
            . "🚀 La cote combinée la plus élevée, pour les gros coups.\n"
            . "⚡ Notifications prioritaires matin et soir.\n"
            . "📊 Suivi des performances + support privilégié.\n"
            . "🗓️ 30 jours d'accès — le meilleur de BIG BOSS.",
    ];

    public function up(): void
    {
        foreach (self::DETAILS as $key => $value) {
            $current = AppSetting::query()->where('key', $key)->value('value');

            if ($current === null || trim((string) $current) === '') {
                AppSetting::setValue($key, $value);
            }
        }
    }

    public function down(): void
    {
        // Pas de retour en arrière : on ne touche pas à un texte que BIG BOSS aurait pu modifier.
    }
};

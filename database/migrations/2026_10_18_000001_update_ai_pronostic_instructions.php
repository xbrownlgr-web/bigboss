<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Consignes de l'admin : la génération automatique doit publier chaque jour Starter, Bronze et Silver.
 * Les phrases qui imposaient « aucun pari sans valeur positive » bloquaient toute publication (la
 * valeur est très rare sur les grands championnats) : elles sont assouplies, l'analyse reste la même.
 * Ajout de la consigne sur les marchés : pas trop de victoires, double chance, buts, mi-temps,
 * cartons et corners.
 */
return new class extends Migration
{
    private const MARKETS = 'Marchés : ne fais pas trop de victoires (1 ou 2) : une victoire sèche au plus par coupon, et seulement pour un favori net. La double chance (1X ou X2), les plus/moins de buts, les marchés de la mi-temps, les cartons jaunes et les corners sont aussi très bons : utilise-les dès que leurs cotes réelles sont fournies, sans jamais les inventer.';

    private const REPLACEMENTS = [
        "Ne retiens un pari que si sa probabilité estimée dépasse la probabilité implicite ajustée d'au moins 3 à 5 points et si sa valeur calculée (probabilité × cote − 1) est positive. Si tu ne peux pas le vérifier, ne prétends pas qu'il y a de la valeur."
            => "Un pari dont la probabilité estimée dépasse la probabilité implicite ajustée d'au moins 3 à 5 points (valeur positive) est prioritaire. Sans valeur vérifiable, retiens les sélections les plus probables que les données confirment (sélections « solides »), sans prétendre qu'il y a de la valeur.",
        'Privilégie les paris simples ou les coupons de 2 à 3 sélections maximum. Aucune sélection à confiance faible ou sans valeur; évite les cotes sous 1,30,'
            => 'Privilégie les coupons courts : 1 à 2 sélections en Starter, jusqu’à 3 en Bronze et en Silver (le coupon VIP le plus sûr, cote 2,10 ou plus). Aucune sélection à confiance faible; évite les cotes sous 1,15,',
        "Si les données clés manquent, si une composition ou une absence majeure reste incertaine, ou si aucune sélection n'a une valeur positive vérifiable, ne force pas le coupon : ne propose aucun pari."
            => "Si les données clés d'un match manquent ou si une absence majeure reste incertaine, écarte ce match et choisis-en un autre mieux couvert : chaque jour, propose les coupons Starter, Bronze et Silver avec les sélections les plus solides disponibles.",
    ];

    public function up(): void
    {
        $value = DB::table('app_settings')->where('key', 'ai_pronostic_instructions')->value('value');

        if ($value === null) {
            return;
        }

        $value = strtr((string) $value, self::REPLACEMENTS);

        if (! str_contains($value, 'Marchés : ne fais pas trop de victoires')) {
            $value = rtrim($value) . "\n\n" . self::MARKETS;
        }

        DB::table('app_settings')
            ->where('key', 'ai_pronostic_instructions')
            ->update(['value' => mb_substr($value, 0, 5000), 'updated_at' => now()]);
        Cache::forget('app_setting_ai_pronostic_instructions');
    }

    public function down(): void
    {
        $value = DB::table('app_settings')->where('key', 'ai_pronostic_instructions')->value('value');

        if ($value === null) {
            return;
        }

        $value = strtr((string) $value, array_flip(self::REPLACEMENTS));
        $value = rtrim(str_replace("\n\n" . self::MARKETS, '', $value));

        DB::table('app_settings')->where('key', 'ai_pronostic_instructions')->update(['value' => $value]);
        Cache::forget('app_setting_ai_pronostic_instructions');
    }
};

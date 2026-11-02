<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('app_settings')->where('key', 'ai_pronostic_instructions')->exists()) {
            return;
        }

        DB::table('app_settings')->insert([
            'group' => 'ia',
            'key' => 'ai_pronostic_instructions',
            'label' => 'Consignes personnalisées pour les pronostics',
            'value' => <<<'INSTRUCTIONS'
Avant toute analyse, vérifie les informations disponibles sur le match : compétition, date, classement et enjeu; forme récente (6 à 10 matchs, domicile/extérieur séparés), xG/xGA, tirs et occasions; buts par période; blessures, suspensions et compositions; repos, calendrier, entraîneur, déplacements, H2H (5 matchs maximum, poids faible), météo et pelouse; cotes d'ouverture et actuelles, consensus et mouvements.

N'utilise que les données réellement fournies dans la demande. Tu ne disposes pas d'un outil de navigation ni d'accès automatique à ces sources pendant la génération. N'affirme donc jamais avoir consulté Understat, FBref, SofaScore, Transfermarkt, des sites de clubs ou des bookmakers. Pour toute information absente, écris « donnée manquante », baisse la confiance et ne la remplace pas par une supposition. Les compositions indisponibles et les absences majeures inconnues doivent être signalées. Les informations critiques devraient être confirmées par deux sources; si elles ne le sont pas, signale-le.

Pèse davantage les données récentes et les échantillons larges; moins de 5 matchs = faible fiabilité. Compare buts réels et xG pour repérer une éventuelle sur/sous-performance, sans la présenter comme certaine. Estime 1/N/2, Over/Under 2,5 et BTTS uniquement quand les données le permettent. Retire la marge des cotes avant de comparer les probabilités. Ne retiens un pari que si sa probabilité estimée dépasse la probabilité implicite ajustée d'au moins 3 à 5 points et si sa valeur calculée (probabilité × cote − 1) est positive. Si tu ne peux pas le vérifier, ne prétends pas qu'il y a de la valeur.

Privilégie les paris simples ou les coupons de 2 à 3 sélections maximum. Aucune sélection à confiance faible ou sans valeur; évite les cotes sous 1,30, les outsiders à l'extérieur, les derbys, retours de trêve, fins de saison sans enjeu, compétitions sans données fiables, marchés exotiques et paris en direct. Pour les coupes, compétitions européennes et sélections nationales, tiens compte de la rotation, du format aller-retour et de la faible stabilité des effectifs.

Si les données clés manquent, si une composition ou une absence majeure reste incertaine, ou si aucune sélection n'a une valeur positive vérifiable, ne force pas le coupon : ne propose aucun pari. Ne présente jamais un pronostic comme sûr ou garanti. Rappelle que les pertes sont normales et conseille 1 à 3 % de bankroll par pari, sans augmenter les mises pour se refaire.
INSTRUCTIONS,
            'type' => 'textarea',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'ai_pronostic_instructions')->delete();
    }
};

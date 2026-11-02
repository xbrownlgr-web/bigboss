<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pronostics à créneaux (consignes principales du 2026-10-19) : chaque niveau, gratuit ou VIP, a
 * ses heures de publication, les seuils de probabilité sont réglables, et chaque créneau laisse
 * une trace (publié, rien de fiable, erreur) pour l'admin. Les consignes de l'IA deviennent le
 * document « Binho Flash — instructions principales » rédigé par le fondateur.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['ai_schedule_starter', 'Heures de publication Starter', '09:00'],
        ['ai_schedule_bronze', 'Heures de publication Bronze', '16:30'],
        ['ai_schedule_silver', 'Heures de publication VIP Silver', '10:00, 15:00, 20:00'],
        ['ai_schedule_gold', 'Heures de publication VIP Gold', '09:00, 13:00, 18:00, 23:30'],
        ['ai_schedule_platinum', 'Heures de publication VIP Platinum', '08:00, 12:00, 16:00, 21:00'],
        ['ai_min_probability_free', 'Probabilité minimale par sélection, gratuits (%)', '75'],
        ['ai_min_probability_vip', 'Probabilité minimale par sélection, VIP (%)', '80'],
        ['ai_vip_teasers_per_day', 'Annonces VIP aux non-abonnés par jour', '2'],
    ];

    private const INSTRUCTIONS = <<<'TXT'
BINHO FLASH — INSTRUCTIONS PRINCIPALES

1. OBJECTIF
Agis comme un analyste sportif professionnel pour BINHO FLASH. Analyse les matchs à partir de données fiables et récentes afin de proposer des pronostics argumentés, avec des cotes réelles et une gestion prudente du risque. La priorité est la qualité, jamais la quantité. Ne garantis aucun gain.

2. GROUPES ET PUBLICATIONS
- STARTER (gratuit) : maximum 1 coupon/jour, idéalement le matin avant 11 h, 1 à 2 sélections.
- BRONZE (gratuit) : maximum 1 coupon/jour, vers 16 h–17 h, 1 à 3 sélections.
- VIP SILVER : jusqu'à 3 coupons/jour, vers 10 h, 14 h–16 h et 20 h, maximum 3 sélections par coupon.
- VIP GOLD : jusqu'à 4 coupons/jour, idéalement le matin, l'après-midi, en début de soirée et vers minuit.
- VIP PLATINUM : jusqu'à 4 coupons/jour, répartis dans la journée, avec analyses approfondies.
Les horaires sont indicatifs. Ne publie pas un coupon si les données ou les sélections ne sont pas suffisamment fiables.

3. SPORTS ET COMPÉTITIONS
Priorité au football, puis au basketball, au tennis et au handball. Ligues de football prioritaires : Premier League anglaise, Liga espagnole, Bundesliga allemande, Ligue 1 française, Eredivisie néerlandaise, première division belge, Série A brésilienne, Süper Lig turque, MLS américaine et Saudi Pro League. D'autres compétitions sont possibles si les données sont fiables.

4. COTES ET DIFFÉRENCIATION
- Chaque coupon doit avoir une cote totale minimale de 2,00 ; une sélection unique doit valoir au moins 2,00.
- Pour VIP Silver, viser 2,10 ou plus lorsque c'est justifié. Proposer occasionnellement un coupon proche de 5,00 en VIP, sans sacrifier la fiabilité.
- Privilégier les coupons courts. Proposer des coupons différents entre les groupes lorsque c'est possible, sans jamais ajouter une sélection risquée uniquement pour différencier.
- Les objectifs de cotes ne justifient jamais un mauvais pronostic.

5. MARCHÉS AUTORISÉS
Football : plus/moins de buts, les deux équipes marquent ou non, double chance 1X, X2 ou 12, chaque équipe marque, marchés de mi-temps et victoire d'une équipe sur au moins une mi-temps si les données le justifient. La victoire simple est exceptionnelle et réservée aux favoris très nets. Les scores exacts sont rares et risqués.
Basketball : vainqueur, handicap de points, total de points. Tennis : vainqueur, handicap de jeux ou de sets, total de jeux. Handball : vainqueur, handicap de buts, total de buts.
Éviter les marchés exotiques, corners, cartons, fautes, hors-jeu et paris en direct. Éviter les cotes individuelles inférieures à 1,15.

6. ANALYSE OBLIGATOIRE
Vérifier autant que possible : forme sur les 6 à 10 derniers matchs (domicile et extérieur), classement, motivation, enjeux et calendrier, buts marqués/encaissés, xG/xGA, tirs et occasions, blessures, suspensions, compositions et rotations, repos, fatigue, déplacements, entraîneur, météo et terrain, confrontations directes (poids limité), cotes d'ouverture et actuelles, mouvements et consensus du marché.
Éviter les matchs déjà commencés, les équipes diminuées, les compositions incertaines, les déplacements difficiles des outsiders, les derbies imprévisibles et les matchs aux enjeux flous. Pour les coupes et compétitions internationales, tenir compte des rotations et du format.

7. PROBABILITÉS ET FIABILITÉ
Estimer les probabilités 1N2, plus/moins de 2,5 buts et les deux équipes marquent uniquement si les données le permettent, en tenant compte de la marge des bookmakers. Une valeur potentielle existe si la probabilité estimée dépasse d'au moins 3 à 5 points la probabilité implicite corrigée de la marge. Seuils visés : 75 % par sélection pour Starter/Bronze et 80 % pour les VIP, uniquement s'ils sont réellement défendables. Ne jamais inventer ou gonfler une probabilité ; si elle n'est pas vérifiable, le préciser. Si aucune sélection ne respecte les critères, ne publie rien.

8. SOURCES ET HONNÊTETÉ
Utiliser uniquement les informations fournies ou réellement obtenues. Ne jamais prétendre avoir consulté une source sans l'avoir fait. Toute donnée absente est une « donnée manquante ». Ne jamais inventer de statistiques, de cotes, de blessures ou de compositions.

9. FORMAT D'UN COUPON
BINHO FLASH — [NOM DU GROUPE] : date, heure, sport et compétition ; pour chaque sélection : match, marché, pronostic, cote réelle, probabilité estimée, justification statistique et principaux risques ; bilan : nombre de sélections, cote totale, probabilité du coupon, niveau de confiance, risques, sources réellement consultées, heure de dernière vérification et données manquantes.

10. GESTION DU RISQUE
Miser environ 1 à 3 % de sa bankroll. Ne jamais augmenter les mises pour récupérer une perte, emprunter pour parier ou miser de l'argent indispensable.

RÈGLE FINALE : la fiabilité passe avant les quotas, les cotes élevées et les objectifs commerciaux.
TXT;

    public function up(): void
    {
        if (! Schema::hasTable('pronostic_runs')) {
            // Un créneau = un niveau à une heure donnée : publié, rien de fiable, ou erreur.
            Schema::create('pronostic_runs', function (Blueprint $table) {
                $table->id();
                $table->date('run_date');
                $table->string('level', 20);
                $table->string('slot', 5);
                $table->string('status', 20);
                $table->text('message')->nullable();
                $table->foreignId('pronostic_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();

                $table->unique(['run_date', 'level', 'slot']);
            });
        }

        $now = now();

        foreach (self::SETTINGS as [$key, $label, $value]) {
            if (! DB::table('app_settings')->where('key', $key)->exists()) {
                DB::table('app_settings')->insert([
                    'group' => 'ia',
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'type' => 'text',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('app_settings')->where('key', 'ai_pronostic_instructions')->update(['value' => self::INSTRUCTIONS, 'updated_at' => $now]);
        Cache::forget('app_setting_ai_pronostic_instructions');
    }

    public function down(): void
    {
        Schema::dropIfExists('pronostic_runs');
        DB::table('app_settings')->whereIn('key', array_column(self::SETTINGS, 0))->delete();
    }
};

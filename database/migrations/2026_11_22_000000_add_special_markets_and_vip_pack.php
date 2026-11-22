<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nouvelles offres VIP séparées (Grosses cotes, Cartons jaunes, Buteurs) + Pack « Accès VIP complet »
 * (un seul paiement qui débloque toutes les catégories VIP), plus horaires et prompt IA dédié
 * rédigé par le fondateur. Ne remplace jamais une valeur déjà réglée.
 */
return new class extends Migration
{
    private const INSTRUCTIONS = <<<'TXT'
# RÔLE
Tu es un analyste football professionnel, spécialisé dans les marchés de paris : résultats, combinés, cartons jaunes et buteurs. Tu raisonnes avec des probabilités et des données, jamais avec l'intuition ni l'enthousiasme. Tu écris en français correct, sans franglais.

# MISSION
À partir des données fournies, analyse les matchs de plusieurs championnats et génère quatre types de pronostics :
1. Des combinés à grosse cote (cible : environ 30, 50 ou 60).
2. Des pronostics de cartons jaunes avec une cote proche de 2.
3. Des pronostics de buteurs.
4. Une analyse de chaque match retenu, qui justifie les choix.

# RÈGLES ABSOLUES
1. Utilise UNIQUEMENT les données fournies dans {{DONNEES}} (ou celles que tes outils de recherche te permettent de vérifier). N'invente jamais une statistique, une composition, une blessure, une cote ou un arbitre.
2. Si une donnée essentielle manque, écris « donnée non disponible » et écarte le match ou le marché concerné. Mieux vaut moins de pronostics que des pronostics faux.
3. Ne promets jamais un gain et n'écris jamais « sûr », « garanti » ou « 100 % ». Une grosse cote correspond toujours à une faible probabilité : dis-le clairement.
4. Sépare toujours les faits (statistiques) de ton estimation (probabilité).
5. Si la composition officielle n'est pas confirmée, signale-le et baisse la confiance de tous les pronostics concernant des joueurs.
6. N'ajoute aucun texte en dehors du format de sortie demandé.

# DONNÉES FOURNIES
- Date d'analyse : {{DATE}}
- Championnats : {{CHAMPIONNATS}}
- Matchs, cotes et statistiques : {{DONNEES}}
- Cible de cote pour le combiné : {{COTE_CIBLE}}
- Nombre de pronostics demandés : {{NOMBRE}}

# MÉTHODE D'ANALYSE (à appliquer à chaque match)
Étape 1 – Contexte : enjeu du match (titre, maintien, qualification), derby, fatigue, calendrier chargé, rotation probable, météo si disponible.
Étape 2 – Forme : 6 derniers matchs de chaque équipe, séparément à domicile et à l'extérieur.
Étape 3 – Statistiques offensives et défensives : buts marqués et encaissés, xG et xGA si disponibles, tirs cadrés, clean sheets, matchs avec les deux équipes qui marquent.
Étape 4 – Effectif : blessés, suspendus, retours, gardien titulaire, composition probable ou officielle.
Étape 5 – Confrontations directes : à pondérer faiblement si elles datent de plus de 2 ans.
Étape 6 – Estimation : pour chaque marché envisagé, donne ta probabilité estimée (en %), puis compare-la à la probabilité implicite de la cote (1 / cote).
Étape 7 – Valeur : valeur = (probabilité estimée × cote) − 1. Ne retiens un pari que si la valeur est supérieure à 0,05 et si la confiance est au moins moyenne.

# MODULE A – COMBINÉS À GROSSE COTE
Objectif : construire des combinés dont la cote totale se situe dans la fourchette demandée (30 : entre 27 et 35 ; 50 : entre 45 et 56 ; 60 : entre 55 et 68).
Règles de construction :
- Utilise entre 5 et 9 sélections, chacune avec une cote entre 1,25 et 2,10. Ne mets pas de grosse cote isolée pour « gonfler » le total.
- Choisis chaque sélection pour sa probabilité estimée élevée, pas seulement pour sa cote.
- Évite les sélections corrélées (par exemple « victoire de X » et « X marque plus de 2,5 buts »). Si une corrélation existe, signale-la et retire une des deux sélections.
- Une seule sélection par match, sauf corrélation nulle.
- Diversifie les championnats pour limiter le risque.
- Marchés autorisés : double chance, victoire avec handicap, plus/moins de buts, les deux équipes marquent, but dans une mi-temps, nombre de corners ou de cartons.
- Calcule la cote totale (produit des cotes) et la probabilité globale estimée (produit des probabilités). Affiche ces deux chiffres.
- Propose trois niveaux : « équilibré » (cote proche de 30), « ambitieux » (cote proche de 50) et « très risqué » (cote proche de 60).
- Classe le risque : élevé ou très élevé. Ne classe jamais un combiné à grosse cote en risque faible.

# MODULE B – CARTONS JAUNES (cote cible : environ 2,00, tolérance de 1,80 à 2,30)
Analyse pour chaque match :
- Arbitre : moyenne de cartons jaunes par match sur la saison, sévérité, historique avec les deux équipes.
- Intensité du match : derby, enjeu fort, rivalité, match à tension.
- Équipes : cartons jaunes reçus et provoqués par match, fautes par match.
Pour les cartons de joueurs :
- Joueurs avec un ratio élevé de cartons par 90 minutes (milieux défensifs, latéraux, défenseurs centraux).
- Joueur opposé à un ailier ou à un attaquant très dribbleur.
- Titularisation confirmée ou très probable, et risque de remplacement tôt dans le match.
- Joueur déjà averti et menacé de suspension (il peut jouer plus prudemment : tiens-en compte).
Marchés possibles : un joueur reçoit un carton jaune, plus de X,5 cartons dans le match, une équipe reçoit plus de X,5 cartons.
Ne retiens que les paris dont la valeur est positive.

# MODULE C – BUTEURS
Pour chaque match retenu :
- Candidats : buteurs réguliers titulaires ou probables titulaires.
- Critères : buts et tirs cadrés par 90 minutes, xG par 90 minutes, tireur de penalty, temps de jeu attendu, forme récente, faiblesses de la défense adverse (xGA, buts encaissés).
- Écarte tout joueur incertain (blessure, rotation, absence de composition).
- Marchés : buteur à tout moment, premier buteur, deux buts ou plus (uniquement pour les attaquants très en forme face à une défense fragile).
- Fourchette de cote pour « buteur à tout moment » : 1,70 à 3,50. Au-delà, mentionne explicitement le risque.

# FORMAT DE SORTIE
Réponds uniquement avec du JSON valide, sans texte avant ni après, selon cette structure :
{
  "date": "AAAA-MM-JJ",
  "combines": [
    {
      "niveau": "equilibre | ambitieux | tres_risque",
      "cote_totale": 0.00,
      "probabilite_globale_estimee": "0.0 %",
      "risque": "eleve | tres_eleve",
      "selections": [
        {
          "championnat": "",
          "match": "",
          "marche": "",
          "pronostic": "",
          "cote": 0.00,
          "probabilite_estimee": "0 %",
          "justification": "2 phrases maximum"
        }
      ],
      "points_de_vigilance": ""
    }
  ],
  "cartons_jaunes": [
    {
      "match": "",
      "pronostic": "",
      "cote": 0.00,
      "probabilite_estimee": "0 %",
      "valeur": 0.00,
      "confiance": "faible | moyenne | elevee",
      "justification": ""
    }
  ],
  "buteurs": [
    {
      "match": "",
      "joueur": "",
      "marche": "",
      "cote": 0.00,
      "probabilite_estimee": "0 %",
      "valeur": 0.00,
      "confiance": "faible | moyenne | elevee",
      "composition_confirmee": true,
      "justification": ""
    }
  ],
  "matchs_ecartes": [
    { "match": "", "raison": "" }
  ],
  "avertissement": "Les paris comportent un risque de perte. Les cotes élevées ont une faible probabilité de réussite. Joue de manière responsable et réservé aux personnes majeures."
}

# CONTRÔLE QUALITÉ (à faire avant de répondre)
- Le produit des cotes de chaque combiné correspond-il bien à la cote totale affichée ?
- Chaque donnée citée provient-elle bien de {{DONNEES}} ?
- Y a-t-il des sélections corrélées dans un même combiné ?
- Chaque pari a-t-il une valeur positive et une probabilité estimée réaliste ?
- Le JSON est-il valide et complet ?
Si une vérification échoue, corrige avant de répondre.
TXT;

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private function settings(): array
    {
        return [
            ['vip', 'vip_grossescotes_price', 'Prix du VIP Grosses cotes (FCFA)', 'text', '5000'],
            ['vip', 'vip_grossescotes_price_week', 'Prix du VIP Grosses cotes à la semaine (FCFA)', 'text', ''],
            ['vip', 'vip_grossescotes_details', 'Détails du VIP Grosses cotes', 'textarea',
                "Combinés à grosse cote sur trois niveaux : Équilibré (~30), Ambitieux (~50) et Très risqué (~60).\n"
                . "5 à 9 sélections décorrélées (cotes 1,25 à 2,10), diversification multi-championnats.\n"
                . "Risque élevé à très élevé : probabilité globale faible, misez petit et de manière responsable.\n"
                . "30 jours d'accès, renouvelable."],

            ['vip', 'vip_cartons_price', 'Prix du VIP Cartons jaunes (FCFA)', 'text', '3500'],
            ['vip', 'vip_cartons_price_week', 'Prix du VIP Cartons jaunes à la semaine (FCFA)', 'text', ''],
            ['vip', 'vip_cartons_details', 'Détails du VIP Cartons jaunes', 'textarea',
                "Sélections ciblées sur les cartons jaunes (cote cible autour de 2,00 · 1,80 à 2,30).\n"
                . "Analyse de l'arbitre, de l'intensité du match, des fautes et des profils à risque.\n"
                . "Uniquement les paris à valeur positive confirmée par les données.\n"
                . "30 jours d'accès, renouvelable."],

            ['vip', 'vip_buteurs_price', 'Prix du VIP Buteurs (FCFA)', 'text', '3500'],
            ['vip', 'vip_buteurs_price_week', 'Prix du VIP Buteurs à la semaine (FCFA)', 'text', ''],
            ['vip', 'vip_buteurs_details', 'Détails du VIP Buteurs', 'textarea',
                "Sélections de buteurs (à tout moment 1,70 à 3,50, premier buteur, doublé).\n"
                . "Critères : buts/90 min, tirs cadrés, xG, tireurs de penalty et faiblesses défensives adverses.\n"
                . "Confiance ajustée automatiquement selon la confirmation des compositions.\n"
                . "30 jours d'accès, renouvelable."],

            ['vip', 'vip_complet_price', 'Prix du pack Accès VIP complet (FCFA)', 'text', '25000'],
            ['vip', 'vip_complet_price_week', 'Prix du pack Accès VIP complet à la semaine (FCFA)', 'text', ''],
            ['vip', 'vip_complet_details', 'Détails du pack Accès VIP complet', 'textarea',
                "Un seul paiement pour débloquer TOUTES les catégories VIP pendant 30 jours :\n"
                . "• Parieurs VIP (Bronze, Silver, Gold, Platinum)\n"
                . "• Score exact (au moins 4 scores analysés par jour)\n"
                . "• Grosses cotes (combinés ~30, ~50 et ~60)\n"
                . "• Cartons jaunes (cotes autour de 2,00)\n"
                . "• Buteurs (analyses ciblées avec valeur positive)"],

            ['ia', 'ai_schedule_special', 'Heures de recherche Grosses cotes / Cartons / Buteurs', 'text', '10:30, 15:30'],
            ['ia', 'ai_special_markets_instructions', 'Méthode d’analyse Grosses cotes / Cartons / Buteurs (consignes de l’IA)', 'textarea', self::INSTRUCTIONS],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->settings() as [$group, $key, $label, $type, $value]) {
            if (DB::table('app_settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('app_settings')->insert([
                'group' => $group,
                'key' => $key,
                'label' => $label,
                'value' => $value,
                'type' => $type,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', array_column($this->settings(), 1))->delete();
    }
};

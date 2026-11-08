# BIG BOSS — backend (fork de Binho Flash)

⚠️ **Lire ce fichier en entier avant toute modification des pronostics, du VIP ou du déploiement.**
Il existe parce que des règles établies avec le fondateur ont déjà été oubliées/écrasées par erreur
en cours de route (ex. l'échelle d'événements par niveau VIP, remplacée par accident en copiant les
réglages de Binho pour la fiabilité). Ne refais pas cette erreur : si un changement touche à l'un des
points ci-dessous, relis cette section avant de modifier quoi que ce soit.

## Identité du projet — NE JAMAIS CONFONDRE avec Binho Flash

- Marque : **BIG BOSS** (slogan « PARIS SPORTIFS »), couleurs **vert & noir**.
- C'est un **fork white-label** de Binho Flash (même base de code à l'origine), revendu à un client.
- Avant toute modification : vérifie que tu es bien dans le dossier **big-boss-backend**, pas
  **binho-flash-backend** / `c:\Binho Flash\backend`. Les deux coexistent et se ressemblent.
- Assistant IA interne = **« LUC »** (pas « George », c'est le nom de Binho).

## Règles VIP / pronostics (établies avec le fondateur — ne pas réinventer)

**Niveaux** : `Starter` (gratuit, seul niveau gratuit) → `Bronze`, `Silver`, `Gold`, `Platinum` (VIP).
`AppSetting::VIP_LEVELS` et `PronosticRules::FREE_LEVELS` doivent rester synchronisés avec ça.

**Échelle progressive du nombre d'événements par niveau** (`PronosticRules::LIMITS`) :
chaque niveau VIP a **plus d'événements que le précédent**, dans cet ordre :
- Starter : 1 événement/jour
- Bronze : 2 événements/jour
- Silver : 3 événements/jour
- Gold : 4 événements/jour
- Platinum : 5 événements/jour (le maximum)

C'est une règle **business** (différenciation des offres), distincte de la fiabilité des pronostics.

⚠️ **C'est un nombre EXACT, pas un plafond.** Jusqu'au 2026-11-08, `buildCombo()` cherchait la
combinaison la plus probable avec `max_selections` comme simple plafond : une seule sélection étant
toujours « plus probable » que 5, les coupons VIP sortaient à 1 sélection et ~1,28 de cote (plainte
du fondateur, à juste titre). La consigne IA disait aussi « Privilégie les coupons courts ». Corrigé :
`minSelections()` = nombre de l'échelle à tous les paliers, la consigne IA exige « exactement N
sélections », un coupon IA incomplet est complété par le serveur (choix de l'IA gardés en priorité),
et le réservoir est limité à 2 sélections par match pour garder assez de matchs différents. Seul le
dernier palier (pas assez de matchs du tout) accepte moins, en prenant alors le plus grand coupon
possible. **Ne jamais réintroduire de logique qui réduit le nombre de sélections pour gagner en
probabilité** : c'est la probabilité minimale par sélection qui s'assouplit, pas la taille du coupon.
⚠️ **Diversité du réservoir** (2026-11-08) : malgré ce correctif, Gold sortait encore à 2 sélections
en « coupon de secours ». Cause : `ValueAnalyzer::candidates()`/`fallbackCandidates()` gardaient
les 40 paris les plus probables, qui venaient de 4-5 matchs seulement (~10 paris par match : 1X, 12,
lignes de buts…) ; une fois retirés les matchs pris par les autres coupons, pas assez de matchs
différents. Désormais **au plus 3 paris par match** dans ces réserves (`MAX_PER_MATCH`) → les 40
couvrent au moins 13 matchs. Test : `test_gold_keeps_four_events_when_the_most_probable_bets_crowd_on_a_few_matches`.
⚠️ **2e limite : les cotes** (offre gratuite The Odds API, 500 crédits/mois) : seulement
`OddsService::MAX_SPORTS = 4` championnats relevés (payants) par génération. Le soir, ces 4
championnats n'ont presque plus de matchs (Platinum 20:00 du 2026-11-08 = 1 sélection MÊME avec le
correctif ci-dessus). Depuis : tous les championnats déjà relevés dans la journée (cache < 8 h)
servent en plus, gratuitement (`cachedFeaturedOdds`, test `OddsCoverageTest`). La vraie solution
reste l'abonnement The Odds API ≥ 5 000 crédits/mois (~30 $/mois pour 20 000) : le code passe
alors tout seul à 16 championnats + marchés supplémentaires (`extended()`). Décision du fondateur.
**Ne jamais écraser cette échelle** en copiant une autre config (ex. celle de Binho), même si on copie
par ailleurs ses réglages de fiabilité (voir ci-dessous). Les deux sont indépendants :
- Fiabilité d'une sélection individuelle → `MIN_SELECTION_ODDS` (1,15) + seuils de probabilité
  `ai_min_probability_free`/`ai_min_probability_vip` (75 %/80 %) : **peut** s'inspirer de Binho.
- Taille du combiné par niveau (1 à 5 événements) → `LIMITS` : **ne vient pas** de Binho, c'est
  une règle BIG BOSS propre, à ne jamais remplacer.

**Une génération par niveau et par jour** (pas plusieurs créneaux qui s'empilent) : le nombre
d'événements du jour pour un niveau doit correspondre à son échelle (ex. Gold = 4, pas plus).
`PronosticRules::DEFAULT_SCHEDULE` doit donc avoir **un seul créneau par niveau VIP**, Starter ayant
exceptionnellement 2 créneaux (matin + soir) puisqu'il reste à 1 événement à chaque fois.

**Fusion en une seule offre par jour** (déjà implémenté côté mobile, `PronosticsScreen.tsx` —
`couponsToShow` / `mapApiPronostic`) : si plusieurs pronostics du même niveau sont publiés le même
jour (génération manuelle supplémentaire par l'admin, etc.), l'app les fusionne en **une seule carte**,
jamais en plusieurs cartes qui se dupliquent. La **cote totale affichée** est le produit de toutes les
cotes du jour pour ce niveau (plus grande, plus attractive qu'une petite cote isolée). Les cotes
individuelles de chaque événement ne s'affichent **plus** sur les coupons VIP — seule la cote totale
compte. Ne pas revenir en arrière sur ce point sans qu'on en reparle explicitement.

**Onglet « Du jour » = coupons du jour SEULEMENT** (depuis le 2026-11-08) : un niveau sans coupon
aujourd'hui n'affiche plus son coupon de la veille (le fondateur l'a pris pour « pas de pronostics
aujourd'hui, rien que les résultats d'hier »). La veille est dans « Historique » ; l'app affiche
« Prochains coupons aujourd'hui : Platinum à 20:00… » à partir de `pronostic_schedule` (heures du
Bénin envoyées par `/settings/public`).

## Offre « Score exact » (niveau VIP `Score`, depuis le 2026-11-08)

Demandée par le fondateur : abonnement **à part** à **5 000 FCFA/mois** (`vip_score_price`), au moins
**4 scores exacts par jour** (`GenerateExactScores::DAILY_MIN`, plafond 6), **notification push dès
qu'un score est publié**, avec l'avertissement « ça peut perdre » partout.
- `Score` est dans `AppSetting::VIP_LEVELS` (abonnement, paiement Maketou, clé WhatsApp, verrouillage,
  admin VIP marchent tels quels) mais **PAS** dans `PronosticRules::LEVELS` (jamais un combiné, pas
  d'échelle d'événements). Chaque score exact est un pronostic séparé avec 1 événement
  (`selection_key = exact_score`, `score_home`/`score_away`) : 4 scores indépendants, jamais fusionnés.
- Méthode du fondateur dans `ai_exact_score_instructions` (Admin > Paramètres). Le **serveur** fait les
  calculs (`ExactScoreGenerator` : buts attendus modèle + marché, Poisson + Dixon-Coles 0-0 à 5-5,
  écart au marché, confiance) ; l'**IA** fait seulement l'étape 2 (ajustements bornés à ±0,40 but,
  justifiés par le dossier) et la rédaction. Le score publié = le plus probable du résultat 1N2 le
  plus probable (règle de cohérence). Confiance ÉLEVÉE impossible (compositions jamais fournies).
- Pas de cote de score exact dans l'API : `cote_totale` null, `odds` 0 — **jamais de cote inventée**.
- Heures : `ai_schedule_score` (défaut 08:00, 12:00, 16:00, 20:00), lancées par `RunPronosticSlots`.
- **Historique** : un score exact perdu n'apparaît jamais dans l'historique ; gagné, il y est ajouté.
  Les scores exacts sont exclus du taux de réussite global (ni gagnés ni perdus) pour ne pas le
  fausser, et `VipService::stats()` renvoie `settled = 0` pour `Score` (l'app n'affiche pas de taux).
- Côté mobile, carte dédiée (`ScoreCard` dans `PronosticsScreen.tsx`, pas la fusion « offre du
  jour » des combinés, qui multiplierait des cotes de scores indépendants).
- Un score exact **gagné** est dévoilé à tous (match + score, jamais l'analyse) dans
  `PronosticController::publicPayload` : le match est fini, c'est la preuve de l'offre pour les
  non-abonnés. Un score en attente ou perdu reste verrouillé.
- Nom affiché : `AppSetting::offerName()` → « Score exact », jamais « VIP Score » (notifications
  d'activation/rappel, message WhatsApp, assistant LUC).

## Déploiement — infrastructure actuelle (2026-11)

- **GitHub** : compte `gobifinancement-ui` **SUSPENDU définitivement** (ToS violation, repo public
  distribuant un APK de paris). Ne plus jamais l'utiliser. Nouveau compte : **`xbrownlgr-web`**, repo
  backend = `xbrownlgr-web/bigboss` (à garder **privé**). Pousser avec un jeton d'accès personnel
  (le `gh` CLI local reste cassé, lié à l'ancien compte).
- **Ne plus jamais distribuer l'APK via une release GitHub** — c'est exactement ce qui a fait
  suspendre le premier compte. L'APK est servi **directement par ce backend**
  (`public/big-boss.apk`, voir `LatestRelease::downloadUrl()`), plus un mirroir gofile.io en secours.
- **Render** : service `big-boss` (id `srv-dbjv1oe7bikc738l4520`), domaine
  `https://big-boss-gure.onrender.com`, auto-déploie depuis `xbrownlgr-web/bigboss` branche `main`.
- **Base de données : TiDB Cloud Serverless** (pas Aiven — Aiven free a déjà causé 2 pannes complètes
  en éteignant la base ET en faisant disparaître son adresse DNS, sans préavis). Hôte
  `gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`, base `bigboss`. ⚠️ **TiDB exige que tout nom
  d'utilisateur soit préfixé par l'identifiant du cluster** (ex. `3fuJ1Pda1LFUwWa.bigboss_app`), sinon
  erreur 1468 à la création. CA = certificat public ISRG Root X1 (Let's Encrypt), transmis via la
  variable d'environnement `DB_CA_CERT` (voir `docker/start.sh`, qui l'écrit dans un fichier local
  au démarrage) — ne jamais dépendre d'un Secret File Render spécifique à un fournisseur.
- **Garde-fou anti-coupure** : `routes/console.php` a une tâche planifiée toutes les 3 min qui fait
  un `SELECT 1` sur la base — ne pas la supprimer, c'est une protection peu coûteuse même si TiDB
  Serverless ne devrait jamais s'éteindre (contrairement à Aiven).
- **Sauvegardes automatiques** : tâche planifiée Windows (« Sauvegarde BIG BOSS et Binho ») qui
  exporte la base toutes les 6 h vers `E:\Sauvegardes-BIGBOSS-Binho\` (hors dépôt, sur la machine du
  fondateur). Si cette tâche est désactivée un jour, le rappeler.
- **Compte admin** : lu via `config('app.admin_email')` (env `ADMIN_EMAIL`), **jamais codé en dur**.
- **Alertes WhatsApp** : `WhatsAppNotifier` (CallMeBot, réglages `callmebot_phone`/`callmebot_apikey`
  dans Admin > Paramètres) reste la source pour les alertes pronostics, messages support et le
  bouton de test générique. **Seule l'alerte « nouvelle recharge/retrait »** (`TransactionController`)
  passe par `WhatsAppCloudService` (API Cloud officielle Meta, variables d'environnement
  `WHATSAPP_*`/`ADMIN_WHATSAPP_NUMBER`, jamais dans Admin > Paramètres) : envoyée via
  `dispatch(...)->afterResponse()` (jamais de queue/worker, inexistant dans cette archi Render à un
  seul service) pour ne jamais bloquer la réponse à l'app, avec repli automatique sur CallMeBot si la
  Cloud API échoue après ses 2 nouvelles tentatives. Avant d'étendre ce repli à d'autres alertes :
  un template Meta approuvé est figé (variables fixes), il ne couvre PAS le texte libre des 3 autres
  déclencheurs — ne jamais y toucher sans faire approuver un nouveau template adapté.
- **Sources de données foot** : Highlightly (100 req/jour gratuit, souvent épuisé en journée — cause
  principale des coupons vides/trop petits, voir le reste de ce fichier) reste la source de **tous**
  les championnats pour le classement/forme, SAUF les 10 couverts par **football-data.org**
  (`FootballDataService`/`FootballDataLeagues`, 10 req/min gratuit, sans plafond quotidien, vérifié en
  direct le 2026-11-06 : PL, Liga, Bundesliga, Ligue 1, Serie A, Eredivisie, Liga Portugal,
  Championship, Brésil, Ligue des champions). Clé optionnelle dans Admin > Paramètres > IA
  (`football_data_api_key`), vide par défaut = aucun changement. ⚠️ **Ne jamais utiliser
  football-data.org pour régler un combiné (gagné/perdu)** : ses identifiants de match ne
  correspondent pas à ceux de Highlightly (`fixture_id`), un rapprochement par nom serait risqué avec
  de l'argent réel — `FixtureService::resultsForEvents` reste 100 % Highlightly, volontairement.

## Règles générales

- Ne jamais committer de secret (mots de passe DB, clés API, compte de service Firebase).
- Avant de pousser un changement de pronostics/VIP, relancer la suite de tests
  (`vendor/bin/phpunit`) : **tout doit passer** (145/145 au 2026-11-08 — les anciens « échecs
  préexistants » étaient des restes de Binho : George, palette bleu/or, admin@binhoflash.com).

## Séparation stricte BIG BOSS / Binho Flash (incident du 2026-11-08)

Le fondateur exige que rien de Binho n'apparaisse dans BIG BOSS (et inversement). Nettoyé le
2026-11-08 : liens réseaux sociaux par défaut (pages Facebook/TikTok/Instagram de Binho affichées
dans l'app BIG BOSS), protection de l'admin principal liée à admin@binhoflash.com, palette par
défaut bleu/or, guichet de retrait « Natitingou / Chez Binho17 », dossier Cloudinary
`binhoflash/`, tests « George ». `AdminNotifier` filtre d'office toute adresse `@binhoflash.com`.
Seuls restent, **volontairement**, des identifiants techniques invisibles : le préfixe des
commandes artisan `binho:*` (planificateur) et le canal Android `binho_alerts` (les APK installés
ne connaissent que lui). Avant de copier quoi que ce soit depuis `c:\Binho Flash`, vérifier qu'aucune
URL, adresse, nom ou valeur de Binho n'est importé.
- Les prix et avantages VIP (`vip_{niveau}_price`, `_price_week`, `_details`) sont éditables dans
  Admin > Paramètres > VIP, jamais codés en dur dans les vues.

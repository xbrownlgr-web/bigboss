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
  (`vendor/bin/phpunit`) — une dizaine d'échecs préexistants sans rapport sont normaux (anciens
  tests hérités de Binho jamais adaptés), mais **aucun nouvel échec** ne doit apparaître.
- Les prix et avantages VIP (`vip_{niveau}_price`, `_price_week`, `_details`) sont éditables dans
  Admin > Paramètres > VIP, jamais codés en dur dans les vues.

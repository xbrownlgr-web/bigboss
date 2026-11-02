# Binho Flash en production (hébergement gratuit)

| Élément | Où | Adresse |
|---|---|---|
| Site + page de téléchargement | Render (gratuit) | https://binhoflash.com |
| Panneau admin | Render | https://binhoflash.com/admin (`admin@binhoflash.com`) |
| API de l'app | Render | https://binhoflash.com/api/v1 |
| Base de données MySQL | Aiven (gratuit) | service `binhoflash-db` |
| E-mails | Brevo (API, 300/jour) | clé `BREVO_KEY` dans Render |
| Fichier APK | GitHub, dépôt public `gobifinancement-ui/binho-flash` | https://github.com/gobifinancement-ui/binho-flash/releases/latest/download/binho-flash.apk |
| Mises à jour OTA + compilation APK | Expo EAS, compte `achille62` | canal `production` |
| Réveil du serveur | UptimeRobot, toutes les 5 min | `/up` |

Code source (dépôts privés) : `gobifinancement-ui/binho-flash-backend` et `gobifinancement-ui/binho-flash-mobile`.

Les secrets (mot de passe Aiven, clé Brevo, mot de passe admin, certificat `aiven-ca.pem`)
sont uniquement dans Render → service *binhoflash* → *Environment*. Jamais dans le code.

## Publier une modification

### 1. Serveur / admin / API (dossier `backend`)
```bash
cd "C:\Binho Flash\backend"
git add -A && git commit -m "Ce qui a changé"
git push
```
Render redéploie tout seul (2 à 5 min). Suivi : dashboard.render.com → *binhoflash* → *Deploys*.

### 2. Écrans, textes, logique de l'app (dossier `mobile/src`) : mise à jour OTA
```bash
cd "C:\Binho Flash\mobile"
git add -A && git commit -m "Ce qui a changé" && git push
npx eas-cli update --channel production --message "Ce qui a changé"
```
Les téléphones la reçoivent au lancement suivant (« Mise à jour prête → Redémarrer »). Pas de nouvel APK.

### 3. Changement natif : nouvel APK
À faire si vous modifiez `app.json` (icône, splash, nom, permissions), ajoutez un module natif
(`npx expo install …`) ou changez de version d'Expo.

1. Dans `mobile/app.json`, augmenter `"version"` : `1.0.0` → `1.1.0`.
2. Compiler sur les serveurs Expo (n'utilise ni le disque ni la mémoire du PC) :
   ```bash
   npx eas-cli build --platform android --profile production
   ```
3. Télécharger l'APK depuis le lien donné par Expo, le renommer `binho-flash.apk`, puis le publier :
   ```bash
   gh release create v1.1.0 binho-flash.apk --repo gobifinancement-ui/binho-flash --title "Binho Flash 1.1.0" --notes "Nouveautés…"
   ```
   Le lien de téléchargement du site pointe automatiquement vers cette nouvelle version.
4. C'est tout : le site et l'invitation « Nouvelle version disponible » lisent la dernière release GitHub
   (délai maximal de 10 minutes). Mettre *Version minimale obligatoire* (Admin → Paramètres) à la nouvelle
   version seulement si l'ancienne ne doit plus fonctionner.

Règle d'or : **toute modification native = nouvelle `version`** dans `app.json`. Une mise à jour OTA ne vise
que les APK de la même version : un APK sans un module natif ne recevra jamais du code qui en a besoin.

## Services à configurer dans l'admin
| Fonction | Où | Compte gratuit |
|---|---|---|
| Vidéos tutoriels (9:16) | Paramètres → Vidéos tutoriels, puis Admin → Vidéos | cloudinary.com (Cloud name, API Key, API Secret) |
| Pronostics IA | Paramètres → Intelligence artificielle (génération automatique à 08:00 et 18:00) | openrouter.ai (clé + quelques $ de crédit), highlightly.net, the-odds-api.com |
| Alertes WhatsApp admin | Paramètres → Notifications WhatsApp | CallMeBot |

## Limites de l'offre gratuite (à surveiller)
- **Render** : 512 Mo de RAM, se met en veille sans visite (UptimeRobot l'en empêche), SMTP bloqué (d'où Brevo).
- **Aiven** : 1 Go de stockage pour la base.
- **Brevo** : 300 e-mails par jour.
- **Cloudinary** : 25 crédits par mois (environ 25 Go de stockage + bande passante cumulés).
- **Highlightly** : 100 requêtes par jour (une par génération IA).
- **Expo** : un nombre limité de compilations gratuites par mois, et OTA gratuit jusqu'à 1 000 utilisateurs actifs par mois.

Quand l'app grandit : acheter un domaine (environ 10 €/an) et passer Render en payant.
L'adresse de l'API se change ensuite par une simple mise à jour OTA, sans réinstallation.

## À ne jamais perdre
- La **clé de signature Android**, stockée chez Expo (`npx eas-cli credentials` → Android → production → télécharger une sauvegarde).
  Sans elle, aucune nouvelle version ne pourra s'installer par-dessus l'ancienne.
- L'accès aux comptes GitHub (`gobifinancement-ui`), Render, Aiven, Brevo et Expo (`achille62`).

## Plus tard : Play Store
- `buildType` → `app-bundle` dans `mobile/eas.json`, et importer la clé Expo dans Play App Signing.
- Exigés : politique de confidentialité, suppression de compte dans l'app, formulaire « Sécurité des données ».
- À vérifier : la politique Google sur les applications liées aux jeux d'argent.

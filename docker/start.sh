#!/bin/sh
set -e
cd /var/www/html

# Render "generateValue" fournit une clé base64 brute ; Laravel attend le préfixe "base64:".
case "$APP_KEY" in
    base64:*|"") ;;
    *) export APP_KEY="base64:$APP_KEY" ;;
esac

# Certificat de la base, copié là où Apache (www-data) peut le lire. Deux sources possibles :
# - DB_CA_CERT : le contenu PEM dans une variable d'environnement (prioritaire, se change sans
#   toucher aux fichiers secrets, pratique pour changer de base) ;
# - MYSQL_ATTR_SSL_CA : le chemin d'un Secret File Render.
if [ -n "$DB_CA_CERT" ]; then
    printf '%s\n' "$DB_CA_CERT" > storage/db-ca.pem
    chown www-data:www-data storage/db-ca.pem
    chmod 0444 storage/db-ca.pem
    export MYSQL_ATTR_SSL_CA=/var/www/html/storage/db-ca.pem
elif [ -n "$MYSQL_ATTR_SSL_CA" ]; then
    if [ -r "$MYSQL_ATTR_SSL_CA" ]; then
        cp "$MYSQL_ATTR_SSL_CA" storage/db-ca.pem
        chown www-data:www-data storage/db-ca.pem
        chmod 0444 storage/db-ca.pem
        export MYSQL_ATTR_SSL_CA=/var/www/html/storage/db-ca.pem
    else
        echo "ERREUR : certificat de la base introuvable : $MYSQL_ATTR_SSL_CA" >&2
        echo "Ajoutez-le dans Render > Environment > Secret Files, avec le nom exact du fichier." >&2
        echo "Fichiers secrets présents :" >&2
        ls -la /etc/secrets >&2 2>&1 || echo "(aucun)" >&2
        exit 1
    fi
fi

# Render est un conteneur unique : cache et sessions sur disque local économisent ~90 % des
# Request Units TiDB Cloud (sinon cache_locks / sessions / throttle saturent les 50 M RU gratuits).
if [ "${CACHE_STORE:-}" = "database" ]; then
    export CACHE_STORE=file
fi
if [ "${SESSION_DRIVER:-}" = "database" ]; then
    export SESSION_DRIVER=file
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force

# S'assure que storage/ (cache/data pour throttle/CACHE_STORE=file, sessions, vues, logs) appartient
# à www-data avant de lancer le planificateur et Apache (sinon mkdir/écriture échoue en HTTP 500).
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

# Planificateur Laravel exécuté sous www-data (même utilisateur qu'Apache) pour ne jamais créer
# de dossiers de cache/verrous appartenant à root dans storage/framework/cache/data.
# On se cale sur le début de chaque minute (sleep jusqu'à la prochaine minute pile) :
# un simple « sleep 60 » dérive avec le temps d'exécution et peut sauter la minute 08:00/18:00.
( while true; do
    su -p -s /bin/sh www-data -c 'php artisan schedule:run --no-interaction >> storage/logs/scheduler.log 2>&1'
    sleep $((60 - $(date +%s) % 60))
  done ) &

# Garde le service éveillé sans outil externe : toutes les 10 minutes, il s'appelle lui-même par
# son adresse publique (RENDER_EXTERNAL_URL, fournie par Render), ce qui empêche la mise en veille
# du plan gratuit (15 min sans visite). Un seul service par compte : 24 h × 31 j = 744 h < 750 h.
if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
  ( sleep 120; while true; do
      php -r '@file_get_contents(getenv("RENDER_EXTERNAL_URL") . "/up");' > /dev/null 2>&1
      sleep 600
    done ) &
fi

exec apache2-foreground

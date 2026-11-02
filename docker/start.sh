#!/bin/sh
set -e
cd /var/www/html

# Render "generateValue" fournit une clé base64 brute ; Laravel attend le préfixe "base64:".
case "$APP_KEY" in
    base64:*|"") ;;
    *) export APP_KEY="base64:$APP_KEY" ;;
esac

# Certificat de la base (Secret File Render) : copié là où Apache (www-data) peut le lire.
if [ -n "$MYSQL_ATTR_SSL_CA" ]; then
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

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force

# Planificateur Laravel : exécute les tâches planifiées (génération des pronostics à 08:00).
# Le service reste éveillé grâce au ping UptimeRobot sur /up.
# On se cale sur le début de chaque minute (sleep jusqu'à la prochaine minute pile) :
# un simple « sleep 60 » dérive avec le temps d'exécution et peut sauter la minute 08:00/18:00.
( while true; do
    php artisan schedule:run --no-interaction >> storage/logs/scheduler.log 2>&1
    sleep $((60 - $(date +%s) % 60))
  done ) &

exec apache2-foreground

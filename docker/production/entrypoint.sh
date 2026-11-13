#!/usr/bin/env bash
# Démarrage d'un conteneur EVENT TRANS selon son rôle :
#   web (nginx + PHP-FPM), horizon (files), scheduler (tâches planifiées), reverb (temps réel),
#   backup (sauvegarde ponctuelle), ou toute autre commande (ex. « php artisan migrate --force »).
set -euo pipefail
cd /var/www/html

# Les caches de configuration sont construits au démarrage, à partir des variables d'environnement.
# Tout tourne sous www-data, propriétaire de storage/ et bootstrap/cache (nginx et supervisord exceptés).
as_app() { su-exec www-data "$@"; }

as_app php artisan optimize --quiet
as_app php artisan storage:link --quiet 2>/dev/null || true

if [ "${RUN_MIGRATIONS:-0}" = "1" ] && [ "${1:-web}" = "web" ]; then
    as_app php artisan migrate --force
fi

case "${1:-web}" in
    web) exec supervisord -c /etc/supervisord.conf ;;
    horizon) exec su-exec www-data php artisan horizon ;;
    scheduler) exec su-exec www-data php artisan schedule:work ;;
    reverb) exec su-exec www-data php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8081}" ;;
    backup) exec scripts/backup-database.sh ;;
    *) exec su-exec www-data "$@" ;;
esac

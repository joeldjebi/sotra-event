#!/usr/bin/env bash
# Sauvegarde quotidienne de la base EVENT TRANS (NF-08), conservée 30 jours.
# À planifier chaque nuit (cron de l'hôte ou service « backup » de docker-compose.prod.yml) :
#   0 1 * * * /chemin/vers/scripts/backup-database.sh >> /var/log/eventtrans-backup.log 2>&1
# Variables : DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, BACKUP_DIR (défaut ./storage/backups),
# BACKUP_RETENTION_DAYS (défaut 30). Le mot de passe passe par PGPASSWORD, jamais en argument.
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-$(dirname "$0")/../storage/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M)"
FILE="${BACKUP_DIR}/eventtrans-${STAMP}.dump"

mkdir -p "${BACKUP_DIR}"
export PGPASSWORD="${DB_PASSWORD:?DB_PASSWORD manquant}"

pg_dump --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-5432}" --username="${DB_USERNAME:?DB_USERNAME manquant}" \
    --format=custom --compress=9 --no-owner --file="${FILE}.partial" "${DB_DATABASE:?DB_DATABASE manquant}"
mv "${FILE}.partial" "${FILE}"
sha256sum "${FILE}" > "${FILE}.sha256" 2>/dev/null || shasum -a 256 "${FILE}" > "${FILE}.sha256"

find "${BACKUP_DIR}" -name 'eventtrans-*.dump*' -type f -mtime +"${RETENTION_DAYS}" -delete

echo "$(date -Iseconds) sauvegarde OK : ${FILE} ($(du -h "${FILE}" | cut -f1))"

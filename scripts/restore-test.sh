#!/usr/bin/env bash
# Restauration testée (NF-08) : restaure la dernière sauvegarde (ou celle passée en argument) dans une base
# temporaire, vérifie quelques tables clés, puis supprime la base. À lancer au moins une fois par mois.
# Variables : mêmes que backup-database.sh ; l'utilisateur doit pouvoir créer une base.
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-$(dirname "$0")/../storage/backups}"
FILE="${1:-$(ls -1t "${BACKUP_DIR}"/eventtrans-*.dump | head -n 1)}"
TARGET="eventtrans_restore_test_$(date +%s)"
export PGPASSWORD="${DB_PASSWORD:?DB_PASSWORD manquant}"
PG=(--host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-5432}" --username="${DB_USERNAME:?DB_USERNAME manquant}")

if [ -f "${FILE}.sha256" ]; then
    (cd "$(dirname "${FILE}")" && (sha256sum -c "$(basename "${FILE}").sha256" 2>/dev/null || shasum -a 256 -c "$(basename "${FILE}").sha256")) >/dev/null
fi

cleanup() { dropdb "${PG[@]}" --if-exists "${TARGET}" >/dev/null 2>&1 || true; }
trap cleanup EXIT

createdb "${PG[@]}" "${TARGET}"
psql "${PG[@]}" --dbname="${TARGET}" --quiet --command="CREATE EXTENSION IF NOT EXISTS postgis;" >/dev/null
pg_restore "${PG[@]}" --dbname="${TARGET}" --no-owner --exit-on-error "${FILE}" 2>/dev/null || pg_restore "${PG[@]}" --dbname="${TARGET}" --no-owner "${FILE}"

for TABLE in events passes orders payments audit_logs; do
    COUNT=$(psql "${PG[@]}" --dbname="${TARGET}" --tuples-only --no-align --command="SELECT count(*) FROM ${TABLE};")
    echo "  ${TABLE} : ${COUNT} lignes"
done

echo "$(date -Iseconds) restauration OK : ${FILE}"

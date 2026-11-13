#!/bin/bash
# Crée la base de tests "<base>_testing" avec PostGIS, à côté de la base principale.
set -e
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-SQL
    CREATE EXTENSION IF NOT EXISTS postgis;
    CREATE DATABASE ${POSTGRES_DB}_testing;
SQL
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "${POSTGRES_DB}_testing" -c "CREATE EXTENSION IF NOT EXISTS postgis;"

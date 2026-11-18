#!/bin/sh
set -eu

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -f /opt/requesty-sql/schema.sql

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
  -v app_pw="$DB_PASSWORD" \
  -c "ALTER ROLE app_restaurante_rw WITH LOGIN PASSWORD :'app_pw';"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
  -v enc_key="$APP_ENCRYPTION_KEY" <<'EOSQL'
SELECT set_config('restaurante.encryption_key', :'enc_key', false);
\i /opt/requesty-sql/seed_demo.sql
EOSQL

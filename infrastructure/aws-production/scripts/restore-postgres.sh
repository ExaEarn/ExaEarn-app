#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT/compose.production.yaml"
ENV_FILE="/srv/exaearn/.env.production"
BACKUP_URI="${1:?Usage: restore-postgres.sh s3://bucket/postgres/backup.dump new_database_name production_database_name}"
TARGET_DB="${2:?Restore requires a new, empty database name; existing databases are never overwritten}"
PRODUCTION_DB="${3:?Pass the current production database name as the third argument}"

if ! [[ "$TARGET_DB" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ ]]; then
  echo "Invalid PostgreSQL database name." >&2
  exit 2
fi

if [[ "$TARGET_DB" == "$PRODUCTION_DB" ]]; then
  echo "Refusing to restore over the configured production database." >&2
  exit 2
fi

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
aws s3 cp "$BACKUP_URI" "$tmp" --region "${AWS_REGION:-us-east-1}" --only-show-errors
test -s "$tmp"

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" cp "$tmp" postgres:/tmp/exaearn-restore.dump
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T postgres \
  sh -c 'createdb --username="$POSTGRES_USER" "$1" && pg_restore --exit-on-error --no-owner --no-acl --username="$POSTGRES_USER" --dbname="$1" /tmp/exaearn-restore.dump && rm -f /tmp/exaearn-restore.dump' \
  sh "$TARGET_DB"
printf 'Restore completed to new database %s; verify it before any cutover.\n' "$TARGET_DB"

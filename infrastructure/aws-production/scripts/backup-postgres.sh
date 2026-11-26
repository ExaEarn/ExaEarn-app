#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT/compose.production.yaml"
ENV_FILE="/srv/exaearn/.env.production"
BUCKET="${BACKUP_BUCKET:?Set BACKUP_BUCKET to the approved S3 backup bucket}"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
object="s3://${BUCKET}/postgres/${timestamp}.dump"
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T postgres \
  sh -c 'pg_dump --format=custom --no-owner --no-acl --username="$POSTGRES_USER" "$POSTGRES_DB"' > "$tmp"
test -s "$tmp"
aws s3 cp "$tmp" "$object" --region "${AWS_REGION:-us-east-1}" --sse AES256 --only-show-errors
printf 'PostgreSQL backup uploaded: %s\n' "$object"

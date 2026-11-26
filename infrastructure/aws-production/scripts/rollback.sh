#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT/compose.production.yaml"
ENV_FILE="/srv/exaearn/.env.production"

if [[ "${ENABLE_API_TRAFFIC:-false}" != "true" ]]; then
  echo "Refusing rollback: set ENABLE_API_TRAFFIC=true after an approved rollback decision." >&2
  exit 2
fi

if [[ -z "${API_IMAGE:-}" || "$API_IMAGE" != *@sha256:* ]]; then
  echo "Set API_IMAGE to the previously approved immutable image digest." >&2
  exit 2
fi

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --quiet
aws ecr get-login-password --region "${AWS_REGION:?Set AWS_REGION}" |
  docker login --username AWS --password-stdin "${API_IMAGE%%/*}"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" pull api
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d api edge

for attempt in $(seq 1 30); do
  container="$(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" ps -q api)"
  if [[ -n "$container" ]] && [[ "$(docker inspect --format '{{.State.Health.Status}}' "$container")" == "healthy" ]]; then
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T edge nginx -t
    printf 'Previous API image is healthy. Database migrations were not reversed.\n'
    exit 0
  fi
  sleep 5
done

echo "Rollback API did not become healthy. Keep DNS/financial workers disabled and escalate." >&2
exit 1

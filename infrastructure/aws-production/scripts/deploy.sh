#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT/compose.production.yaml"
ENV_FILE="/srv/exaearn/.env.production"

if [[ "${ENABLE_API_TRAFFIC:-false}" != "true" ]]; then
  echo "Refusing deployment: set ENABLE_API_TRAFFIC=true only after the production release is explicitly approved." >&2
  exit 2
fi

if [[ ! -f "$ENV_FILE" || "$(stat -c '%a' "$ENV_FILE")" != "600" ]]; then
  echo "Expected $ENV_FILE with mode 600, sourced from the approved secret manager." >&2
  exit 2
fi

for file in origin.crt origin.key cloudflare-origin-pull-ca.pem; do
  if [[ ! -s "/srv/exaearn/tls/$file" ]]; then
    echo "Missing required Cloudflare TLS file: /srv/exaearn/tls/$file" >&2
    exit 2
  fi
done

if [[ -z "${API_IMAGE:-}" || "$API_IMAGE" == *:latest ]]; then
  echo "Set API_IMAGE to an immutable ECR image reference (never latest)." >&2
  exit 2
fi

for image_name in API_IMAGE BLOCKCHAIN_IMAGE POSTGRES_IMAGE REDIS_IMAGE NGINX_IMAGE; do
  image="${!image_name:-}"
  if [[ "$image" != *@sha256:* ]]; then
    echo "$image_name must be pinned to a verified SHA-256 digest." >&2
    exit 2
  fi
done

if [[ -z "${AWS_REGION:-}" || -z "${BACKUP_BUCKET:-}" || -z "${APPLICATION_ENVIRONMENT_SECRET_ARN:-}" ]]; then
  echo "Set AWS_REGION, BACKUP_BUCKET, and APPLICATION_ENVIRONMENT_SECRET_ARN from approved configuration." >&2
  exit 2
fi

if ! grep -Eq '^BLOCKCHAIN_EVENT_LISTENER_ENABLED=false$' "$ENV_FILE"; then
  echo "BLOCKCHAIN_EVENT_LISTENER_ENABLED=false must be present in the production environment." >&2
  exit 2
fi

for key in PRIVATE_KEY DEPLOYER_PRIVATE_KEY WITHDRAWAL_SIGNING_KEY HD_WALLET_MNEMONIC HD_WALLET_PASSPHRASE \
  BITCOIN_HOT_WALLET_WIF ETHEREUM_HOT_WALLET_PRIVATE_KEY BASE_HOT_WALLET_PRIVATE_KEY \
  BSC_HOT_WALLET_PRIVATE_KEY POLYGON_HOT_WALLET_PRIVATE_KEY TRON_HOT_WALLET_PRIVATE_KEY \
  SOLANA_HOT_WALLET_SECRET_KEY TON_HOT_WALLET_MNEMONIC XRPL_HOT_WALLET_SECRET; do
  value="$(sed -n "s/^${key}=//p" "$ENV_FILE" | tail -n 1 | tr -d "\"'[:space:]")"
  if [[ -n "$value" ]]; then
    echo "Refusing deployment: signing material $key must remain unset." >&2
    exit 2
  fi
done

if ! grep -Eq '^TRUSTED_PROXIES=.+$' "$ENV_FILE"; then
  echo "Set TRUSTED_PROXIES to the reviewed Cloudflare proxy CIDRs before enabling traffic." >&2
  exit 2
fi

if ! grep -Eq '^[[:space:]]*set_real_ip_from[[:space:]]+[0-9a-fA-F:.]+/[0-9]+;' "$ROOT/nginx/cloudflare-realip.conf"; then
  echo "Populate nginx/cloudflare-realip.conf with current Cloudflare CIDR ranges." >&2
  exit 2
fi

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --quiet
aws ecr get-login-password --region "$AWS_REGION" |
  docker login --username AWS --password-stdin "${API_IMAGE%%/*}"

export API_IMAGE
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" pull api postgres redis edge

# No worker, scheduler, or blockchain profile is started by this script.
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d postgres redis

for attempt in $(seq 1 60); do
  container="$(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" ps -q postgres)"
  if [[ -n "$container" ]] && [[ "$(docker inspect --format '{{.State.Health.Status}}' "$container")" == "healthy" ]]; then
    break
  fi
  if [[ "$attempt" -eq 60 ]]; then
    echo "PostgreSQL did not become healthy; migration and deployment aborted." >&2
    exit 1
  fi
  sleep 5
done

# Require a verified pre-migration backup. The migration itself must be separately reviewed.
BACKUP_BUCKET="$BACKUP_BUCKET" "$ROOT/scripts/backup-postgres.sh"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm api php artisan migrate --force
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d api edge

cat >/etc/systemd/system/exaearn-postgres-backup.service <<UNIT
[Unit]
Description=Encrypted ExaEarn PostgreSQL backup to S3
After=docker.service

[Service]
Type=oneshot
Environment=BACKUP_BUCKET=$BACKUP_BUCKET
Environment=AWS_REGION=$AWS_REGION
ExecStart=$ROOT/scripts/backup-postgres.sh
UNIT

cat >/etc/systemd/system/exaearn-postgres-backup.timer <<'UNIT'
[Unit]
Description=Daily ExaEarn PostgreSQL backup

[Timer]
OnCalendar=*-*-* 02:15:00 UTC
Persistent=true
RandomizedDelaySec=15m

[Install]
WantedBy=timers.target
UNIT

systemctl daemon-reload
systemctl enable --now exaearn-postgres-backup.timer

for attempt in $(seq 1 30); do
  container="$(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" ps -q api)"
  if [[ -n "$container" ]] && [[ "$(docker inspect --format '{{.State.Health.Status}}' "$container")" == "healthy" ]]; then
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T edge nginx -t
    printf '\nLocal API and Nginx configuration are healthy. Independently verify Cloudflare Full (strict) and mTLS before DNS changes. Financial workers remain disabled.\n'
    exit 0
  fi
  sleep 5
done

echo "API did not become healthy; do not enable DNS or financial workers." >&2
exit 1

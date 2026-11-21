#!/usr/bin/env bash
set -euo pipefail

destination="/srv/exaearn/.env.production"
secret_arn="${APPLICATION_ENVIRONMENT_SECRET_ARN:?Set APPLICATION_ENVIRONMENT_SECRET_ARN to the approved Secrets Manager ARN}"
tmp="$(mktemp /srv/exaearn/.env.production.XXXXXX)"
trap 'rm -f "$tmp"' EXIT
umask 077

aws secretsmanager get-secret-value \
  --region "${AWS_REGION:-us-east-1}" \
  --secret-id "$secret_arn" \
  --query 'SecretString' \
  --output text > "$tmp"

test -s "$tmp"
grep -Eq '^APP_ENV=production$' "$tmp"
grep -Eq '^APP_DEBUG=false$' "$tmp"
chmod 600 "$tmp"
mv "$tmp" "$destination"
trap - EXIT
printf 'Loaded approved production environment to %s (values not displayed).\n' "$destination"

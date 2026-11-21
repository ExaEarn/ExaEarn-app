#!/usr/bin/env bash
set -euo pipefail

destination="/srv/exaearn/.env.production"
parameter_path="/exaearn/production/app"
tmp_json="$(mktemp /srv/exaearn/.ssm-env.XXXXXX)"
tmp_env="$(mktemp /srv/exaearn/.env.production.XXXXXX)"
trap 'rm -f "$tmp_json" "$tmp_env"' EXIT
umask 077

aws ssm get-parameters-by-path \
  --region "${AWS_REGION:-us-east-1}" \
  --path "$parameter_path" \
  --recursive \
  --with-decryption \
  --query 'Parameters[].{Name:Name,Value:Value}' \
  --output json > "$tmp_json"

python3 - "$tmp_json" "$tmp_env" "$parameter_path" <<'PY'
import json
import re
import sys

source, destination, parameter_path = sys.argv[1:]
prefix = parameter_path.rstrip("/") + "/"
with open(source, encoding="utf-8") as file:
    parameters = json.load(file)

values = {}
for parameter in parameters:
    name = parameter["Name"]
    if not name.startswith(prefix):
        raise SystemExit("Unexpected SSM parameter path")
    key = name[len(prefix):]
    if not re.fullmatch(r"[A-Z][A-Z0-9_]*", key):
        raise SystemExit("SSM environment parameter names must be direct uppercase keys")
    value = parameter["Value"]
    if "\x00" in value:
        raise SystemExit("SSM environment parameter contains an invalid NUL byte")
    values[key] = value

for key, expected in (("APP_ENV", "production"), ("APP_DEBUG", "false")):
    if values.get(key) != expected:
        raise SystemExit(f"Required production setting is missing or invalid: {key}")

with open(destination, "w", encoding="utf-8", newline="\n") as file:
    for key in sorted(values):
        value = values[key].replace("\\", "\\\\").replace('"', '\\"').replace("\r", "\\r").replace("\n", "\\n").replace("$", "$$")
        file.write(f'{key}="{value}"\n')
PY

test -s "$tmp_env"
chmod 600 "$tmp_env"
mv "$tmp_env" "$destination"
trap - EXIT
rm -f "$tmp_json"
printf 'Loaded production SSM parameters to %s (values not displayed).\n' "$destination"

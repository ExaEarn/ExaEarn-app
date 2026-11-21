#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"

if [[ "${PUBLISH_RELEASE_IMAGES:-false}" != "true" || "${RELEASE_SCAN_PASSED:-false}" != "true" ]]; then
  echo "Refusing publication without explicit approval and a successful release image scan." >&2
  exit 2
fi

release_sha="${RELEASE_SHA:?Set RELEASE_SHA to the reviewed 40-character commit SHA}"
if [[ ! "$release_sha" =~ ^[0-9a-f]{40}$ ]]; then
  echo "RELEASE_SHA must be a full lowercase 40-character Git SHA." >&2
  exit 2
fi

if [[ "$(git -C "$ROOT" rev-parse HEAD)" != "$release_sha" ]]; then
  echo "RELEASE_SHA must match the checked-out commit." >&2
  exit 2
fi
if [[ -n "$(git -C "$ROOT" status --porcelain)" ]]; then
  echo "Refusing publication from a dirty working tree." >&2
  exit 2
fi

profile="${AWS_PROFILE:?Set AWS_PROFILE to a separately approved ECR publisher profile}"
region="${AWS_REGION:-us-east-1}"
repository="${ECR_REPOSITORY_URI:?Set ECR_REPOSITORY_URI from the approved ECR output}"
api_tag="${release_sha}-api"
blockchain_tag="${release_sha}-blockchain"

account_id="$(aws sts get-caller-identity --profile "$profile" --region "$region" --query Account --output text)"
registry="${repository%%/*}"
if [[ "$registry" != "$account_id.dkr.ecr.$region.amazonaws.com" ]]; then
  echo "ECR registry account/region does not match the authenticated publisher identity." >&2
  exit 2
fi

aws ecr get-login-password --profile "$profile" --region "$region" |
  docker login --username AWS --password-stdin "$registry"

docker buildx build \
  --platform linux/arm64 \
  --file "$ROOT/backend/api-gateway/Dockerfile" \
  --tag "$repository:$api_tag" \
  --push \
  "$ROOT/backend/api-gateway"

docker buildx build \
  --platform linux/arm64 \
  --file "$ROOT/backend/services/blockchain-service/Dockerfile" \
  --tag "$repository:$blockchain_tag" \
  --push \
  "$ROOT"

api_digest="$(aws ecr describe-images --profile "$profile" --region "$region" --repository-name exaearn/production --image-ids "imageTag=$api_tag" --query 'imageDetails[0].imageDigest' --output text)"
blockchain_digest="$(aws ecr describe-images --profile "$profile" --region "$region" --repository-name exaearn/production --image-ids "imageTag=$blockchain_tag" --query 'imageDetails[0].imageDigest' --output text)"

if [[ "$api_digest" != sha256:* || "$blockchain_digest" != sha256:* ]]; then
  echo "ECR did not return valid immutable image digests." >&2
  exit 1
fi

printf 'API_IMAGE=%s@%s\n' "$repository" "$api_digest"
printf 'BLOCKCHAIN_IMAGE=%s@%s\n' "$repository" "$blockchain_digest"

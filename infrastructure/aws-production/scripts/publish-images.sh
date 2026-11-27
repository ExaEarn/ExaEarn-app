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
api_repository="${API_ECR_REPOSITORY_URI:?Set API_ECR_REPOSITORY_URI from the approved CloudFormation output}"
blockchain_repository="${BLOCKCHAIN_ECR_REPOSITORY_URI:?Set BLOCKCHAIN_ECR_REPOSITORY_URI from the approved CloudFormation output}"
api_tag="${release_sha}-api"
blockchain_tag="${release_sha}-blockchain"

account_id="$(aws sts get-caller-identity --profile "$profile" --region "$region" --query Account --output text)"
registry="${api_repository%%/*}"
blockchain_registry="${blockchain_repository%%/*}"
if [[ "$registry" != "$account_id.dkr.ecr.$region.amazonaws.com" || "$blockchain_registry" != "$registry" ]]; then
  echo "ECR registry account/region does not match the authenticated publisher identity." >&2
  exit 2
fi
if [[ "${api_repository##*/}" != "exaearn-production-api" || "${blockchain_repository##*/}" != "exaearn-production-blockchain" ]]; then
  echo "The image URIs must reference the existing production API and blockchain repositories." >&2
  exit 2
fi

aws ecr get-login-password --profile "$profile" --region "$region" |
  docker login --username AWS --password-stdin "$registry"

docker buildx build \
  --platform linux/arm64 \
  --file "$ROOT/backend/api-gateway/Dockerfile" \
  --tag "$api_repository:$api_tag" \
  --push \
  "$ROOT/backend/api-gateway"

docker buildx build \
  --platform linux/arm64 \
  --file "$ROOT/backend/services/blockchain-service/Dockerfile" \
  --tag "$blockchain_repository:$blockchain_tag" \
  --push \
  "$ROOT"

api_digest="$(aws ecr describe-images --profile "$profile" --region "$region" --repository-name exaearn-production-api --image-ids "imageTag=$api_tag" --query 'imageDetails[0].imageDigest' --output text)"
blockchain_digest="$(aws ecr describe-images --profile "$profile" --region "$region" --repository-name exaearn-production-blockchain --image-ids "imageTag=$blockchain_tag" --query 'imageDetails[0].imageDigest' --output text)"

if [[ "$api_digest" != sha256:* || "$blockchain_digest" != sha256:* ]]; then
  echo "ECR did not return valid immutable image digests." >&2
  exit 1
fi

printf 'API_IMAGE=%s@%s\n' "$api_repository" "$api_digest"
printf 'BLOCKCHAIN_IMAGE=%s@%s\n' "$blockchain_repository" "$blockchain_digest"

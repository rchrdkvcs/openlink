#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

kubectl apply -k "$ROOT_DIR/infra/kubernetes/overlays/local"

kubectl rollout status deployment/postgres \
  --namespace openlink \
  --timeout=120s

kubectl rollout status deployment/redis \
  --namespace openlink \
  --timeout=120s

kubectl get pods,services,pvc \
  --namespace openlink

#!/usr/bin/env bash

set -euo pipefail

NAMESPACE="openlink"
SECRET_NAME="openlink-secrets"

APP_KEY="base64:$(openssl rand -base64 32)"
POSTGRES_PASSWORD="$(openssl rand -hex 24)"

kubectl create namespace "$NAMESPACE" \
  --dry-run=client \
  -o yaml \
  | kubectl apply -f -

kubectl create secret generic "$SECRET_NAME" \
  --namespace "$NAMESPACE" \
  --from-literal=APP_KEY="$APP_KEY" \
  --from-literal=POSTGRES_DB="openlink" \
  --from-literal=POSTGRES_USER="openlink" \
  --from-literal=POSTGRES_PASSWORD="$POSTGRES_PASSWORD" \
  --dry-run=client \
  -o yaml \
  | kubectl apply -f -

echo "Secret $SECRET_NAME créé dans le namespace $NAMESPACE."

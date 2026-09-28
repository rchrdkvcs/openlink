#!/bin/sh
set -u

request=/app/storage/app/update-request
status=/app/storage/app/update-status
heartbeat=/app/storage/app/update-heartbeat

while :; do
  touch "$heartbeat"
  if [ -f "$request" ]; then
    rm -f "$request"
    printf 'running\n' > "$status"

    if [ "$OPENLINK_IMAGE_TAG" = latest ] && \
      docker compose -p openlink --env-file /deploy/.env -f /deploy/compose.yml pull app worker scheduler migrate && \
      docker compose -p openlink --env-file /deploy/.env -f /deploy/compose.yml up -d --wait app worker scheduler; then
      printf 'succeeded\n' > "$status"
    else
      printf 'failed\n' > "$status"
    fi
  fi

  sleep 5
done

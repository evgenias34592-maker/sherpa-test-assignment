#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
test -f .env || cp .env.example .env
docker compose up -d --build
echo "Приложение: http://localhost:${APP_PORT:-8080}"
echo "Swagger: http://localhost:${APP_PORT:-8080}/api/docs"

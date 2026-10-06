#!/usr/bin/env bash
# Runs pint --quiet on the edited PHP file. Best-effort, silent on failure.

INPUT=$(cat)
FILE=$(echo "$INPUT" | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
    print(d.get('file_path') or d.get('path') or '')
except Exception:
    print('')
" 2>/dev/null)

[[ "$FILE" != *.php ]] && exit 0

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(git -C "$(dirname "$FILE")" rev-parse --show-toplevel 2>/dev/null)}"
[[ -z "$PROJECT_DIR" ]] && exit 0

CONTAINER=$(docker compose -f "$PROJECT_DIR/docker-compose.yml" ps -q php 2>/dev/null)
[[ -z "$CONTAINER" ]] && exit 0

REL_PATH="${FILE#$PROJECT_DIR/}"

docker compose -f "$PROJECT_DIR/docker-compose.yml" exec -T php \
  sh -c "cd /var/www/app && ./vendor/bin/pint --quiet '$REL_PATH'" 2>/dev/null || true

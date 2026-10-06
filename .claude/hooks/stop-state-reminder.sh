#!/usr/bin/env bash
# Reminds to update docs/state.md when app/ has uncommitted changes.

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null)}"
[[ -z "$PROJECT_DIR" ]] && exit 0

CHANGED=$(git -C "$PROJECT_DIR" diff --name-only HEAD 2>/dev/null | grep -E '^(app|src)/' | head -1)

if [[ -n "$CHANGED" ]]; then
  # Не напоминать, если state.md сам тоже изменён (значит уже обновлён в этой сессии).
  STATE_CHANGED=$(git -C "$PROJECT_DIR" diff --name-only HEAD 2>/dev/null | grep 'docs/state.md')
  if [[ -z "$STATE_CHANGED" ]]; then
    echo "REMINDER: есть незакоммиченные изменения в app/ — обнови docs/state.md перед остановкой." >&2
    exit 2
  fi
fi

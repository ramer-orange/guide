#!/bin/sh
# claude-board: reports this Claude Code session's state (working / waiting / done) to the
# session dashboard. Runs in the background and never blocks or fails the session.
#
#   claude-board.sh <HookEventName> <scope>
#     scope "project": installed in a repository's .claude/settings.json, reports cloud sessions only
#     scope "user":    installed in ~/.claude/settings.json on a Mac, reports local sessions only
#
# Token: cloud sessions get it injected by the environment's network secret for the ingest host;
# local sessions read it from ~/.claude/claude-board-token (or $CLAUDE_BOARD_TOKEN).

EVENT="${1:-}"
SCOPE="${2:-project}"
URL="${CLAUDE_BOARD_URL:-https://claude-board-ingest.ra-mer-web1111.workers.dev/ingest}"

if [ "${CLAUDE_CODE_REMOTE:-}" = "true" ]; then PLACE=cloud; else PLACE=local; fi
if [ "$SCOPE" = "project" ] && [ "$PLACE" != "cloud" ]; then cat >/dev/null 2>&1; exit 0; fi
if [ "$SCOPE" = "user" ] && [ "$PLACE" != "local" ]; then cat >/dev/null 2>&1; exit 0; fi

TOKEN="${CLAUDE_BOARD_TOKEN:-}"
if [ -z "$TOKEN" ] && [ -r "$HOME/.claude/claude-board-token" ]; then TOKEN=$(tr -d ' \r\n' < "$HOME/.claude/claude-board-token"); fi
if [ -z "$TOKEN" ] && [ "$PLACE" = "cloud" ]; then TOKEN=proxy-injected; fi
if [ -z "$TOKEN" ]; then cat >/dev/null 2>&1; exit 0; fi

if [ "$EVENT" = "PostToolUse" ]; then
  # Tool results can be large and contain code: send only the session id.
  HEAD=$(head -c 2000)
  cat >/dev/null 2>&1
  SID=$(printf '%s' "$HEAD" | grep -o '"session_id" *: *"[^"]*"' | head -n 1 | sed 's/.*"\([^"]*\)"$/\1/')
  BODY="{\"session_id\":\"$SID\",\"hook_event_name\":\"PostToolUse\"}"
else
  BODY=$(head -c 200000)
  cat >/dev/null 2>&1
fi

DIR="${CLAUDE_PROJECT_DIR:-$PWD}"
REPO=$(git -C "$DIR" remote get-url origin 2>/dev/null | sed -E 's#\.git$##; s#^.*[/:]([^/:]+/[^/]+)$#\1#')
BRANCH=$(git -C "$DIR" rev-parse --abbrev-ref HEAD 2>/dev/null)
[ "$BRANCH" = "HEAD" ] && BRANCH=""
HOSTN=$(hostname -s 2>/dev/null || hostname 2>/dev/null)

(
  printf '%s' "$BODY" | curl -sS -m 8 -o /dev/null -X POST "$URL" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -H "X-Board-Event: $EVENT" \
    -H "X-Board-Place: $PLACE" \
    -H "X-Board-Repo: $REPO" \
    -H "X-Board-Branch: $BRANCH" \
    -H "X-Board-Host: $HOSTN" \
    -H "X-Board-Remote-Session: ${CLAUDE_CODE_REMOTE_SESSION_ID:-}" \
    --data-binary @-
) >/dev/null 2>&1 &

exit 0

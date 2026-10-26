#!/usr/bin/env bash
# Cloyne installs its own updates: cron runs this every 15 minutes (added by deploy/dreamhost.sh).
# If GitHub has a newer version, it runs "dreamhost.sh --auto" (no questions) and writes the result
# to storage/app/auto-update.json, which Settings → Updates shows. Off while storage/app/auto-update.off exists.

APP_DIR="$HOME/cloyne-app"
STATUS="$APP_DIR/storage/app/auto-update.json"
LOG="$APP_DIR/storage/logs/auto-update.log"

cd "$APP_DIR" 2>/dev/null || exit 0
mkdir -p storage/app storage/logs storage/framework

status() { # status <state> <message> [updated]
    local now; now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    local updated_at; updated_at="$(sed -n 's/.*"updated_at":"\([^"]*\)".*/\1/p' "$STATUS" 2>/dev/null)"
    [ "${3:-}" = "updated" ] && updated_at="$now"
    local msg="${2//\"/\'}"
    printf '{"state":"%s","message":"%s","checked_at":"%s","updated_at":"%s","commit":"%s"}\n' \
        "$1" "$msg" "$now" "$updated_at" "$(git rev-parse --short HEAD 2>/dev/null)" > "$STATUS"
}

[ -f storage/app/auto-update.off ] && { status off "Automatic updates are off."; exit 0; }
[ -d .git ] || { status manual "Run the update once by hand to switch on automatic updates."; exit 0; }

exec 9>storage/framework/auto-update.lock
flock -n 9 || exit 0 # an update is already running

git fetch -q origin main 2>>"$LOG" || { status error "Couldn't reach GitHub. Will try again in 15 minutes."; exit 0; }
# Up to date, unless the last update stopped part way (the code is new but the rest didn't finish): then try again.
if [ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] && ! grep -q '"state":"failed"' "$STATUS" 2>/dev/null; then
    status current "Up to date."
    exit 0
fi

echo "=== $(date -u) updating to $(git rev-parse --short origin/main)" >> "$LOG"
if bash deploy/dreamhost.sh --auto >> "$LOG" 2>&1; then
    status updated "Installed the newest version." updated
else
    status failed "The last update stopped part way. Run bash ~/cloyne-app/deploy/dreamhost.sh by hand and send Claude a screenshot."
fi

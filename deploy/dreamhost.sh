#!/usr/bin/env bash
# Cloyne on DreamHost shared hosting: first install AND every update.
#
# First time, after signing in with SSH, paste:
#   git clone https://github.com/Aubrelai/Cloyne.git ~/cloyne-app && bash ~/cloyne-app/deploy/dreamhost.sh
# Every update after that:
#   bash ~/cloyne-app/deploy/dreamhost.sh
# Uploaded the files yourself (FileZilla)? Put them in ~/cloyne-app (NOT the
# website folder), then run the update command above.
# Change the database password or add API keys later:
#   bash ~/cloyne-app/deploy/dreamhost.sh settings
#
# Passwords and API keys are typed in when asked (hidden) and saved only in
# ~/cloyne-app/.env on the server. They never go to GitHub.

set -euo pipefail

APP_DIR="$HOME/cloyne-app"
DOMAIN="cloyne.laiqdigital.com"
SITE_DIR="$HOME/$DOMAIN"
REPO="https://github.com/Aubrelai/Cloyne.git"
DB_HOST="mysql.admin.laiqdigital.com"
DB_NAME="cloyne"
DB_USER="cloyne"

step() { echo; echo "=== $* ==="; }
fail() {
    echo
    echo "*** $* ***"
    echo "Take a screenshot of this window and send it to Claude."
    exit 1
}
trap 'fail "Something went wrong (line $LINENO)."' ERR

# ---------------------------------------------------------------------------
step "1/8  Finding PHP 8.3 or newer"
PHP=""
# Lowest version first: packages installed for PHP 8.3 also run on 8.4/8.5, but not the other way
# round, so the command line must never be newer than the website's PHP (set in the DreamHost panel).
for candidate in /usr/local/php83/bin/php /usr/local/php84/bin/php /usr/local/php85/bin/php "$(command -v php || true)"; do
    [ -n "$candidate" ] && [ -x "$candidate" ] || continue
    if "$candidate" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' 2>/dev/null; then
        PHP="$candidate"
        break
    fi
done
[ -n "$PHP" ] || fail "No PHP 8.3 or newer found. In the DreamHost panel, set this website's PHP version to 8.3 or newer, then run this again."
echo "Using $PHP (PHP $("$PHP" -r 'echo PHP_VERSION;'))"

# ---------------------------------------------------------------------------
if [ "${1:-}" = "settings" ]; then
    cd "$APP_DIR" || fail "Cloyne isn't installed yet. Run the install command first."
    step "Change settings (answers are hidden; press Enter to keep the current value)"
    read -rsp "New MySQL password: " CLOYNE_DB_PASSWORD; echo
    read -rsp "Claude API key: " CLOYNE_ANTHROPIC; echo
    read -rsp "Apify API token: " CLOYNE_APIFY; echo
    read -rsp "MillionVerifier key (optional): " CLOYNE_VERIFIER; echo
    export CLOYNE_DB_PASSWORD CLOYNE_ANTHROPIC CLOYNE_APIFY CLOYNE_VERIFIER
    [ -z "$CLOYNE_DB_PASSWORD" ] || "$PHP" deploy/set-env.php DB_PASSWORD=@CLOYNE_DB_PASSWORD
    [ -z "$CLOYNE_ANTHROPIC" ] || "$PHP" deploy/set-env.php ANTHROPIC_API_KEY=@CLOYNE_ANTHROPIC
    [ -z "$CLOYNE_APIFY" ] || "$PHP" deploy/set-env.php APIFY_TOKEN=@CLOYNE_APIFY
    [ -z "$CLOYNE_VERIFIER" ] || "$PHP" deploy/set-env.php MILLIONVERIFIER_KEY=@CLOYNE_VERIFIER
    "$PHP" artisan optimize
    "$PHP" artisan queue:restart >/dev/null || true
    echo "Saved."
    exit 0
fi

# ---------------------------------------------------------------------------
step "2/8  Getting the latest code from GitHub"
if [ -d "$APP_DIR/.git" ]; then
    git -C "$APP_DIR" pull --ff-only
elif [ -f "$APP_DIR/artisan" ]; then
    echo "Using the files uploaded to $APP_DIR (FileZilla). Upload new files there to update."
else
    git clone "$REPO" "$APP_DIR"
fi
cd "$APP_DIR"
FIRST_RUN=0
[ -f .env ] || FIRST_RUN=1

# ---------------------------------------------------------------------------
step "3/8  Installing Laravel packages"
COMPOSER="$HOME/.local/bin/composer.phar"
if [ ! -f "$COMPOSER" ]; then
    mkdir -p "$(dirname "$COMPOSER")"
    curl -sS https://getcomposer.org/installer | "$PHP" -- --quiet --install-dir="$(dirname "$COMPOSER")" --filename=composer.phar
fi
COMPOSER_FLAGS="--no-dev --optimize-autoloader --no-interaction --no-progress"
if ! "$PHP" "$COMPOSER" install $COMPOSER_FLAGS; then
    # The saved package list was made for a different PHP version: pick packages again for this one.
    echo "Choosing packages again for PHP $("$PHP" -r 'echo PHP_VERSION;')..."
    rm -f composer.lock
    "$PHP" "$COMPOSER" update $COMPOSER_FLAGS
fi

# ---------------------------------------------------------------------------
step "4/8  Settings"
if [ "$FIRST_RUN" = "1" ]; then
    cp .env.example .env
    echo "Answers are hidden as you type. Press Enter to skip an optional one; you can add it later."
    read -rsp "MySQL password for the '$DB_USER' database (required): " CLOYNE_DB_PASSWORD; echo
    [ -n "$CLOYNE_DB_PASSWORD" ] || fail "The database password is required."
    read -rsp "Claude API key, starts with sk-ant- (optional): " CLOYNE_ANTHROPIC; echo
    read -rsp "Apify API token (optional): " CLOYNE_APIFY; echo
    export CLOYNE_DB_PASSWORD CLOYNE_ANTHROPIC CLOYNE_APIFY

    "$PHP" deploy/set-env.php \
        APP_ENV=production APP_DEBUG=false "APP_URL=https://$DOMAIN" LOG_LEVEL=warning \
        DB_CONNECTION=mysql "DB_HOST=$DB_HOST" DB_PORT=3306 "DB_DATABASE=$DB_NAME" "DB_USERNAME=$DB_USER" \
        DB_PASSWORD=@CLOYNE_DB_PASSWORD SESSION_SECURE_COOKIE=true
    [ -z "$CLOYNE_ANTHROPIC" ] || "$PHP" deploy/set-env.php ANTHROPIC_API_KEY=@CLOYNE_ANTHROPIC
    [ -z "$CLOYNE_APIFY" ] || "$PHP" deploy/set-env.php APIFY_TOKEN=@CLOYNE_APIFY
    unset CLOYNE_DB_PASSWORD CLOYNE_ANTHROPIC CLOYNE_APIFY
    chmod 600 .env
    "$PHP" artisan key:generate --force
else
    echo "Keeping the settings already in ~/cloyne-app/.env"
fi

# ---------------------------------------------------------------------------
step "5/8  Updating the database"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R u+rwX storage bootstrap/cache
"$PHP" artisan config:clear >/dev/null
"$PHP" artisan migrate --force || fail "Couldn't update the database. Check the MySQL password (in ~/cloyne-app/.env as DB_PASSWORD) and that the 'cloyne' database exists."

# ---------------------------------------------------------------------------
step "6/8  Pointing $DOMAIN at Cloyne"
if [ -L "$SITE_DIR" ]; then
    echo "Already pointing at Cloyne."
else
    if [ -e "$SITE_DIR" ]; then
        BACKUP="$SITE_DIR.before-cloyne-$(date +%Y%m%d%H%M%S)"
        mv "$SITE_DIR" "$BACKUP"
        echo "Moved the old site folder to $BACKUP"
    fi
    ln -s "$APP_DIR/public" "$SITE_DIR"
    echo "$SITE_DIR now shows Cloyne."
fi

# ---------------------------------------------------------------------------
step "7/8  Background worker (runs every minute)"
CRON_LINE="* * * * * cd $APP_DIR && $PHP artisan schedule:run >> /dev/null 2>&1"
if (crontab -l 2>/dev/null | grep -v 'cloyne-app && .*schedule:run'; echo "$CRON_LINE") | crontab - 2>/dev/null; then
    echo "Scheduled: audits, email drafts and Lead Finder results are processed every minute."
else
    echo "Couldn't add the scheduled job automatically. In the DreamHost panel, go to"
    echo "Advanced > Cron Jobs, add one that runs every minute, with this command:"
    echo "  cd $APP_DIR && $PHP artisan schedule:run"
fi

# ---------------------------------------------------------------------------
step "8/8  Finishing up"
"$PHP" artisan optimize
"$PHP" artisan queue:restart >/dev/null || true

if ! "$PHP" artisan cloyne:owner --check >/dev/null 2>&1; then
    echo
    echo "Create the Owner account now, so nobody else can claim it."
    read -rp "Owner email: " OWNER_EMAIL
    read -rp "Owner name: " OWNER_NAME
    "$PHP" artisan cloyne:owner "$OWNER_EMAIL" --name="$OWNER_NAME"
fi

echo
echo "Done. Open https://$DOMAIN"
echo "If the page says 'not secure', turn on the free HTTPS certificate for $DOMAIN in the DreamHost panel."

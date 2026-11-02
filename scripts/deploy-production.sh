#!/usr/bin/env bash
#
# Production deploy for yoksystem.id (Hostinger shared hosting).
#
# Runs ON THE SERVER. GitHub Actions pipes it over SSH:
#   ssh ... 'bash -s' -- <commit-sha> < scripts/deploy-production.sh
# It can also be run by hand from an SSH session:
#   bash ~/domains/yoksystem.id/yokprinting_app/scripts/deploy-production.sh <commit-sha>
#
# Same flow as the manual deploy in docs/deployment-cpanel.md §4, plus a
# database backup before anything changes:
#   preflight -> artisan down -> mysqldump backup -> fast-forward to <sha>
#   -> composer install -> migrate -> caches -> rsync public/ to public_html
#   -> artisan up (always) -> health check
#
# The deploy chain is &&-joined (fail-fast). `artisan up` always runs so the
# site is never left in maintenance mode, and the script still exits with the
# failing step's status so CI reports the failure.

set -uo pipefail

step() { printf '\n==> %s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

# Everything runs inside main() so bash has parsed the whole script before
# any command starts. When the script arrives on stdin (`bash -s`), a child
# process reading stdin could otherwise swallow the rest of the script.
main() {
exec < /dev/null

TARGET_SHA="${1:?usage: deploy-production.sh <commit-sha>}"
APP_DIR="${APP_DIR:-$HOME/domains/yoksystem.id/yokprinting_app}"
PUBLIC_DIR="${PUBLIC_DIR:-$HOME/domains/yoksystem.id/public_html}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/yokprinting}"
BACKUP_KEEP="${BACKUP_KEEP:-14}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || echo /usr/local/bin/composer)}"

# --- Preflight: nothing below touches the live site yet --------------------
step "Preflight"
cd "$APP_DIR" || fail "app dir not found: $APP_DIR"
[ -d "$PUBLIC_DIR" ] || fail "public dir not found: $PUBLIC_DIR"
"$PHP_BIN" -v | head -n 1
"$COMPOSER_BIN" --version 2>/dev/null | head -n 1 || fail "composer not found"
command -v mysqldump >/dev/null || fail "mysqldump not found"
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "$APP_DIR is not a git repo"
[ "$(git rev-parse --abbrev-ref HEAD)" = "main" ] || fail "server checkout is not on main"
[ -z "$(git status --porcelain --untracked-files=no)" ] \
    || fail "server has local changes to tracked files; resolve them by hand first"
git fetch --quiet origin main || fail "git fetch failed"
git cat-file -e "${TARGET_SHA}^{commit}" 2>/dev/null || fail "commit $TARGET_SHA not found after fetch"
echo "current: $(git rev-parse --short HEAD)  target: $(git rev-parse --short "$TARGET_SHA")"

backup_database() {
    local stamp file defaults
    mkdir -p "$BACKUP_DIR" && chmod 700 "$BACKUP_DIR" || return 1
    stamp="$(date +%Y%m%d-%H%M%S)"
    file="$BACKUP_DIR/yokprinting-$stamp-$(git rev-parse --short HEAD).sql.gz"
    defaults="$(mktemp)" || return 1
    chmod 600 "$defaults"

    # Credentials come from the app's own (cached) config, written to a
    # private option file so the password never appears on a command line.
    "$PHP_BIN" -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $c = config("database.connections.".config("database.default"));
        $q = fn ($v) => "\"".addcslashes((string) $v, "\\\"")."\"";
        echo "[client]\nhost=", $q($c["host"]), "\nport=", $q($c["port"]),
            "\nuser=", $q($c["username"]), "\npassword=", $q($c["password"]), "\n";
        file_put_contents("php://stderr", $c["database"]);
    ' > "$defaults" 2> "$defaults.db" || { rm -f "$defaults" "$defaults.db"; return 1; }
    local database
    database="$(cat "$defaults.db")"
    rm -f "$defaults.db"

    mysqldump --defaults-extra-file="$defaults" --single-transaction --quick \
        --no-tablespaces --default-character-set=utf8mb4 "$database" | gzip > "$file"
    local status=$?
    rm -f "$defaults"

    if [ "$status" -ne 0 ] || ! gzip -t "$file" 2>/dev/null; then
        rm -f "$file"
        echo "backup failed" >&2
        return 1
    fi
    echo "backup: $file ($(du -h "$file" | cut -f1))"

    # Keep only the newest $BACKUP_KEEP backups.
    ls -1t "$BACKUP_DIR"/yokprinting-*.sql.gz 2>/dev/null | tail -n +"$((BACKUP_KEEP + 1))" | xargs -r rm -f --
}

# --- Deploy ------------------------------------------------------------------
step "Maintenance mode on"
"$PHP_BIN" artisan down --render="errors::503" --retry=60 || true

(
    step "Database backup" && backup_database &&
    step "Update code" && git merge --ff-only --quiet "$TARGET_SHA" &&
    step "Composer install" && "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction &&
    step "Migrate" && "$PHP_BIN" artisan migrate --force &&
    step "Cache" && "$PHP_BIN" artisan config:cache && "$PHP_BIN" artisan route:cache && "$PHP_BIN" artisan view:cache &&
    step "Sync public/ to public_html" &&
    rsync -a --delete --exclude='index.php' --exclude='storage' "$APP_DIR/public/" "$PUBLIC_DIR/"
)
DEPLOY_STATUS=$?

step "Maintenance mode off"
"$PHP_BIN" artisan up || true

if [ "$DEPLOY_STATUS" -ne 0 ]; then
    printf '\nDEPLOY FAILED (status %s) at %s. Check the step output above.\n' \
        "$DEPLOY_STATUS" "$(git rev-parse --short HEAD)" >&2
    exit "$DEPLOY_STATUS"
fi

step "Health check"
"$PHP_BIN" artisan operations:health --skip-heartbeat || exit $?

printf '\nDeployed %s\n' "$(git log -1 --format='%h %s')"
}

main "$@"

#!/bin/sh
set -eu

# ROLE         = web | worker | all     (default: all)
# SKIP_MIGRATE = 0 | 1                  (default: 1 — safe)
#
# Exactly ONE container in a multi-container deployment must run with
# SKIP_MIGRATE=0. See LanCore docs/mil-std-498/SIP.md §3.4 for the pattern.

ROLE="${ROLE:-all}"
SKIP_MIGRATE="${SKIP_MIGRATE:-1}"
export OCTANE_WORKERS="${OCTANE_WORKERS:-auto}"
export OCTANE_MAX_REQUESTS="${OCTANE_MAX_REQUESTS:-500}"

log() { echo "[entrypoint] $*"; }
die() { echo "[entrypoint] ERROR: $*" >&2; exit 1; }

log "Starting LanBrackets (role=${ROLE}, skip_migrate=${SKIP_MIGRATE}, workers=${OCTANE_WORKERS})"

[ -n "${APP_KEY:-}" ] || die "APP_KEY must be set at runtime (never baked into the image)"

mkdir -p /var/run/supervisor /var/log/supervisor \
         storage/framework/sessions storage/framework/views \
         storage/framework/cache storage/logs bootstrap/cache
chown -R www-data:www-data /var/run/supervisor /var/log/supervisor storage bootstrap/cache

su-exec www-data php artisan config:cache    || die "config:cache failed"
su-exec www-data php artisan route:cache     || die "route:cache failed"
su-exec www-data php artisan view:cache      || die "view:cache failed"
su-exec www-data php artisan event:cache     || die "event:cache failed"

if [ "${SKIP_MIGRATE}" != "1" ]; then
    log "Running database migrations (designated migrator)"
    su-exec www-data php artisan migrate --force || die "migrate --force failed"
else
    log "Skipping migrations (SKIP_MIGRATE=1)"
fi

case "${ROLE}" in
    web)    CONF=/etc/supervisor/conf.d/supervisord-web.conf ;;
    worker) CONF=/etc/supervisor/conf.d/supervisord-worker.conf ;;
    all)    CONF=/etc/supervisor/conf.d/supervisord.conf ;;
    *)      die "Unknown ROLE: ${ROLE} (expected: web | worker | all)" ;;
esac

log "Exec supervisord with ${CONF}"
# supervisord itself must run as root so it can open /dev/stdout and /dev/stderr.
# All supervised programs are configured with `user=www-data`.
exec /usr/bin/supervisord -c "${CONF}"

#!/usr/bin/env bash
# One-command local setup: starts containers, installs WordPress, activates
# the JobHub theme + plugin and loads demo data.
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
set -a; . ./.env; set +a

echo "==> Starting containers"
docker compose up -d

WP="docker compose run --rm wpcli wp"

echo "==> Waiting for WordPress files"
until docker compose exec -T wordpress test -f /var/www/html/wp-config.php; do sleep 2; done

if ! $WP core is-installed >/dev/null 2>&1; then
  echo "==> Installing WordPress"
  $WP core install \
    --url="http://localhost:${WP_PORT}" \
    --title="JobHub" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email
fi

echo "==> Activating theme and plugin"
$WP plugin activate jobhub-core
$WP theme activate jobhub
$WP rewrite structure '/%postname%/' --hard
$WP option update users_can_register 1

echo "==> Loading demo data"
$WP jobhub seed

echo ""
echo "Done!"
echo "  Site:        http://localhost:${WP_PORT}"
echo "  Admin:       http://localhost:${WP_PORT}/wp-admin  (${WP_ADMIN_USER} / ${WP_ADMIN_PASSWORD})"
echo "  Employer:    employer / employer123"
echo "  phpMyAdmin:  http://localhost:${PMA_PORT}"
echo "  Emails:      http://localhost:${MAIL_PORT:-8025}  (Mailpit)"

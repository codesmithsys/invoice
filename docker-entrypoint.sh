#!/bin/bash
set +e

export PORT="${PORT:-8080}"
echo "=== Starting invoice app on port $PORT ===" >&2

# Validate nginx config exists
if [ ! -f /etc/nginx/sites-available/default ]; then
    echo "=== FATAL: nginx config not found ===" >&2
    exit 1
fi

# Replace the port in nginx config
sed -i "s/listen 8080 default_server;/listen $PORT default_server;/" /etc/nginx/sites-available/default

# Show the resulting listen line for debugging
echo "=== nginx listen line: ===" >&2
grep "listen " /etc/nginx/sites-available/default | head -3 >&2

# Test nginx config
echo "=== Testing nginx config ===" >&2
nginx -t 2>&1 | head -5 >&2

# Start php-fpm in background and verify it actually started
echo "=== Starting php-fpm ===" >&2
php-fpm -D
sleep 1

# Verify php-fpm is running
if pgrep php-fpm > /dev/null; then
    echo "=== php-fpm is running (PID $(pgrep php-fpm)) ===" >&2
else
    echo "=== FATAL: php-fpm failed to start ===" >&2
    php-fpm -t 2>&1 | head -10 >&2
    exit 1
fi

# Verify the socket exists
sleep 1
if [ -S /run/php/php8.3-fpm.sock ]; then
    echo "=== PHP socket ready: /run/php/php8.3-fpm.sock ===" >&2
else
    echo "=== WARNING: PHP socket not found at /run/php/php8.3-fpm.sock ===" >&2
    ls -la /run/php/ 2>&1 | head -5 >&2
    # Try alternative socket locations
    find /run -name "*.sock" 2>/dev/null | head -5 >&2
fi

# DB init in background (do not block nginx startup)
(
  if [ -n "$DB_HOST" ] && [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
      echo "=== Waiting for database at $DB_HOST:${DB_PORT:-5432} ===" >&2
      READY=0
      for i in $(seq 1 30); do
          if PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1" >/dev/null 2>&1; then
              echo "=== Database is ready ===" >&2
              READY=1
              break
          fi
          echo "  attempt $i/30..." >&2
          sleep 2
      done

      if [ "$READY" = "1" ]; then
          EXISTS=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT to_regclass('public.invoices');" 2>/dev/null | tr -d ' \n' || echo "")
          if [ -z "$EXISTS" ]; then
              echo "=== Tables not found. Initializing from setup.sql ===" >&2
              PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USER" -d "$DB_NAME" -f /var/www/html/setup.sql 2>&1 | head -20 >&2
              echo "=== Database initialized ===" >&2
          else
              echo "=== Tables already exist (got: $EXISTS). Skipping setup ===" >&2
          fi
      else
          echo "=== WARNING: Database not ready after 60s. ===" >&2
      fi
  else
      echo "=== DB env vars not set. ===" >&2
  fi
) &

echo "=== Starting nginx in foreground ===" >&2
exec nginx -g 'daemon off;'

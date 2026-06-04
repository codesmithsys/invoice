#!/bin/bash
set +e

export PORT="${PORT:-8080}"
echo "=== Starting invoice app on port $PORT ===" >&2

# Update nginx port
sed -i "s/listen 8080 default_server;/listen $PORT default_server;/" /etc/nginx/sites-available/default
sed -i "s/listen 8080;/listen $PORT;/" /etc/nginx/sites-available/default

# Always start the web server first so the port is open for health checks.
# DB init happens in background.
echo "=== Starting php-fpm ===" >&2
php-fpm -D

# Start DB init in background
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
          EXISTS=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT to_regclass('public.invoices');" 2>/dev/null | tr -d ' ' || echo "")
          if [ -z "$EXISTS" ] || [ "$EXISTS" = "" ]; then
              echo "=== Tables not found. Initializing from setup.sql ===" >&2
              PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USER" -d "$DB_NAME" -f /var/www/html/setup.sql
              echo "=== Database initialized ===" >&2
          else
              echo "=== Tables already exist. Skipping setup ===" >&2
          fi
      else
          echo "=== WARNING: Database not ready after 60s. App will retry on request. ===" >&2
      fi
  else
      echo "=== DB env vars not set. Assuming external DB. ===" >&2
  fi
) &

echo "=== Starting nginx ===" >&2
exec nginx -g 'daemon off;'

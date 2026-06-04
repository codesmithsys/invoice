#!/bin/bash
set -e

echo "Starting invoice app..."

if [ -n "$DB_HOST" ] && [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
    echo "Waiting for MySQL at $DB_HOST..."
    READY=0
    for i in $(seq 1 30); do
        if mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" -p"${DB_PASS}" -e "SELECT 1" >/dev/null 2>&1; then
            echo "MySQL is ready."
            READY=1
            break
        fi
        echo "  attempt $i/30..."
        sleep 2
    done

    if [ "$READY" = "1" ]; then
        EXISTS=$(mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" -p"${DB_PASS}" -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DB_NAME'" -sN 2>/dev/null || echo "")

        if [ -z "$EXISTS" ]; then
            echo "Database $DB_NAME not found. Creating from setup.sql..."
            mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" -p"${DB_PASS}" < /var/www/html/setup.sql
            echo "Database initialized."
        else
            echo "Database $DB_NAME already exists. Skipping setup."
        fi
    else
        echo "WARNING: MySQL not ready after 60s. Starting web server anyway - app may show errors until DB is reachable."
    fi
else
    echo "DB_HOST/DB_NAME/DB_USER not set. Skipping DB init."
fi

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx..."
exec nginx -g 'daemon off;'

FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    postgresql-client \
    nginx \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY --chown=www-data:www-data . /var/www/html/

COPY nginx.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/* && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

RUN mkdir -p /var/www/html/uploads /run/php \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]

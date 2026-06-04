FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Aggressive MPM cleanup: remove ALL mpm symlinks and re-enable only mpm_prefork
RUN set -eux; \
    find /etc/apache2 -name 'mpm_*.load' -delete; \
    find /etc/apache2 -name 'mpm_*.conf' -delete; \
    ls /etc/apache2/mods-available/mpm_*.load 2>/dev/null || true; \
    a2enmod mpm_prefork rewrite headers; \
    echo "=== Final MPM state ==="; \
    ls -la /etc/apache2/mods-enabled/ | grep mpm || echo "no mpm symlinks"; \
    apache2ctl -M 2>&1 | grep -i mpm || echo "apache2ctl check done"

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY --chown=www-data:www-data . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

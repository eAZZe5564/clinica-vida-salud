FROM php:8.4-apache-bookworm

# Extensiones reales del proyecto; JSON, PDO, sesiones y contraseñas vienen con PHP.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev ca-certificates \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/clinica
COPY app/ ./app/
COPY public/ ./public/
COPY docker/apache/ports.conf /etc/apache2/ports.conf
COPY docker/apache/clinica.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/clinica-production.ini
COPY docker/start.sh /usr/local/bin/clinica-start

RUN sed -i 's/\r$//' /usr/local/bin/clinica-start \
    && chmod 0755 /usr/local/bin/clinica-start \
    && mkdir -p /var/lib/clinica/sessions \
    && chown www-data:www-data /var/lib/clinica/sessions \
    && chmod 0700 /var/lib/clinica/sessions \
    && a2enmod dir

ENV PORT=10000
EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/clinica-start"]
CMD ["apache2-foreground"]

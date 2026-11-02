FROM php:8.2-apache

# mysqli talks to TiDB Cloud (MySQL compatible), ca-certificates lets it verify TiDB's TLS certificate
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && docker-php-ext-install mysqli \
    && a2enmod rewrite expires deflate headers \
    && rm -rf /var/lib/apt/lists/*

# hide the php version and apache details
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini \
    && printf "ServerTokens Prod\nServerSignature Off\n" > /etc/apache2/conf-available/zz-security.conf \
    && a2enconf zz-security \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Render tells the app which port to listen on through $PORT (default 10000)
ENV PORT=10000
RUN sed -ri 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/
RUN rm -f /var/www/html/config/.env && chown -R www-data:www-data /var/www/html

EXPOSE 10000

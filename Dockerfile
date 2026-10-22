FROM richarvey/nginx-php-fpm:3.1.6

WORKDIR /var/www/html

COPY . .
COPY conf/nginx/nginx-site.conf /etc/nginx/sites-available/default.conf
COPY conf/php/uploads.ini /usr/local/etc/php/conf.d/zzz-uploads.ini

ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=0
ENV REAL_IP_HEADER=1
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN php -m | grep -E '^pdo_pgsql$' \
    && php -m | grep -E '^pgsql$' \
    && composer install --no-dev --optimize-autoloader

EXPOSE 80

CMD ["/start.sh"]

FROM php:8.3-apache
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && docker-php-ext-install curl && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite alias \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf 'ServerName localhost\nRedirectMatch 308 ^/amicao$ /amicao/\nAlias /amicao/ /var/www/html/public/\n<Directory /var/www/html>\n    AllowOverride None\n    Require all denied\n</Directory>\n<Directory /var/www/html/public>\n    AllowOverride None\n    Options -Indexes +FollowSymLinks\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/amicao.conf \
    && a2enconf amicao
COPY . /var/www/html/
RUN printf 'upload_max_filesize=5M\npost_max_size=7M\n' > /usr/local/etc/php/conf.d/amicao.ini \
    && apache2ctl configtest
EXPOSE 80
